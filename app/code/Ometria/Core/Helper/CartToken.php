<?php
namespace Ometria\Core\Helper;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Math\Random;
use Ometria\Core\Helper\Config as ConfigHelper;
use Psr\Log\LogLevel;

/**
 * Sole owner of the abandoned cart deeplink token.
 *
 * Shared by Ometria_Core (producer: writes the token into the `ommage` cookie) and
 * Ometria_AbandonedCarts (verifier: Controller\Cartlink\Index), so the column name and
 * the comparison rule exist in exactly one place.
 *
 * The token is a per quote random value, generated once and stored on the quote row.
 * It is deliberately NOT derived from any quote data. A token computed from values an
 * attacker can guess - the sequential entity_id and the second granularity created_at -
 * is forgeable no matter which hash is wrapped around it, which is what EC-2010 was.
 */
class CartToken
{
    /** Column added to the `quote` table by Ometria_Core/etc/db_schema.xml */
    const COLUMN = 'ometria_cart_token';

    /** Magento *resource* name owning the quote table - resolves correctly under split database */
    const QUOTE_RESOURCE = 'checkout';

    const QUOTE_TABLE = 'quote';

    /** 12 chars from a 62 char alphabet = 62^12 ~= 71 bits */
    const TOKEN_LENGTH = 12;

    /**
     * Alphanumeric only, on purpose. The token is imploded into the `ommage` cookie using
     * ':' as the field separator and ';' as the command separator (@see Cookiechannel), and
     * it also travels as a URL query parameter. Passing the alphabet explicitly protects
     * both from any future change to Magento's default charset.
     */
    const TOKEN_CHARS = Random::CHARS_LOWERS . Random::CHARS_UPPERS . Random::CHARS_DIGITS;

    /** @var ResourceConnection */
    protected $resource;

    /** @var Random */
    protected $random;

    /** @var ConfigHelper */
    protected $helperConfig;

    /**
     * @param ResourceConnection $resource
     * @param Random $random
     * @param ConfigHelper $helperConfig
     */
    public function __construct(
        ResourceConnection $resource,
        Random $random,
        ConfigHelper $helperConfig
    ) {
        $this->resource     = $resource;
        $this->random       = $random;
        $this->helperConfig = $helperConfig;
    }

    /**
     * Return the quote's deeplink token, generating and persisting one on first use.
     *
     * The value returned is always the value the database actually holds, never a candidate
     * we merely generated - see the read back below. Emitting an unstored token would put a
     * dead link in the recovery email.
     *
     * Never throws. This runs inside checkout_cart_save_after, so any failure must degrade
     * to "no deeplink for this cart" rather than break add to cart for every shopper.
     *
     * @param int|string|null $quoteId
     * @return string '' when no token could be established
     */
    public function getOrCreate($quoteId)
    {
        $quoteId = (int)$quoteId;

        // checkout_cart_save_after can fire with no persisted quote.
        if ($quoteId <= 0) {
            return '';
        }

        try {
            $connection = $this->resource->getConnection(self::QUOTE_RESOURCE);
            $table      = $this->resource->getTableName(self::QUOTE_TABLE, self::QUOTE_RESOURCE);

            $existing = $this->read($connection, $table, $quoteId, false);
            if ($existing !== '') {
                // Write once, never rotate. Rotating would invalidate every recovery email
                // already sent for this cart the moment the shopper touched it again.
                return $existing;
            }

            // Claims the row only if nobody has claimed it yet. InnoDB locks the primary key
            // row and evaluates the WHERE against the current row version, so of two
            // concurrent requests the first writer wins and the second matches zero rows.
            $connection->update(
                $table,
                [self::COLUMN => $this->random->getRandomString(self::TOKEN_LENGTH, self::TOKEN_CHARS)],
                [
                    $connection->quoteInto('entity_id = ?', $quoteId),
                    '(' . self::COLUMN . ' IS NULL OR ' . self::COLUMN . " = '')"
                ]
            );

            // Authoritative re-read. Deliberately not branching on the affected row count:
            // MySQL reports rows CHANGED rather than rows MATCHED, so that number cannot
            // tell us whether we won the claim. A concurrent request may hold the row, and
            // its token - not ours - is what must reach Ometria.
            return $this->read($connection, $table, $quoteId, true);
        } catch (\Throwable $e) {
            // Note the leading backslash. The existing catch in Model/Observer/Cart.php
            // resolves to Ometria\Core\Model\Observer\Exception and therefore never fires.
            $this->helperConfig->log($e->getMessage() . ' in ' . __METHOD__, LogLevel::ERROR);
            return '';
        }
    }

    /**
     * Constant time comparison, failing closed on an absent, empty or non string token.
     *
     * @param mixed $storedToken
     * @param mixed $suppliedToken
     * @return bool
     */
    public function isValid($storedToken, $suppliedToken)
    {
        // A quote with no token provisioned must never be loadable, otherwise NULL becomes
        // a skeleton key for every pre upgrade cart.
        if (!is_string($storedToken) || $storedToken === '') {
            return false;
        }

        // hash_equals() raises a TypeError on non strings in PHP 8, and ?token[]=x arrives
        // here as an array.
        if (!is_string($suppliedToken) || $suppliedToken === '') {
            return false;
        }

        return hash_equals($storedToken, $suppliedToken);
    }

    /**
     * @param AdapterInterface $connection
     * @param string $table
     * @param int $quoteId
     * @param bool $forUpdate
     * @return string
     */
    protected function read(AdapterInterface $connection, $table, $quoteId, $forUpdate)
    {
        $select = $connection->select()
            ->from($table, [self::COLUMN])
            ->where('entity_id = ?', $quoteId);

        if ($forUpdate) {
            // Forces a current read rather than a snapshot read, in case an outer
            // REPEATABLE READ transaction is open. Without it we could read the pre update
            // snapshot and emit a token that is not the one stored.
            $select->forUpdate(true);
        }

        $value = $connection->fetchOne($select);

        return is_string($value) ? $value : '';
    }
}
