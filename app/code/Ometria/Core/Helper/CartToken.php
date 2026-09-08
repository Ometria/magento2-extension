<?php
namespace Ometria\Core\Helper;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Math\Random;
use Ometria\Core\Helper\Config as ConfigHelper;
use Psr\Log\LogLevel;

/**
 * Owns the abandoned cart deeplink token.
 *
 * Shared by Ometria_Core (producer) and Ometria_AbandonedCarts (verifier) so the column
 * name and the comparison rule live in one place.
 */
class CartToken
{
    /** Column added to the `quote` table by Ometria_Core/etc/db_schema.xml */
    const COLUMN = 'ometria_cart_token';

    /** Magento resource name owning the quote table - resolves correctly under split database */
    const QUOTE_RESOURCE = 'checkout';

    const QUOTE_TABLE = 'quote';

    const TOKEN_LENGTH = 12;

    /**
     * Alphanumeric only. Other character classes are not safe to carry through the cookie
     * channel and the deeplink URL, so do not widen this set.
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
     * Always returns the value held in the database. Never throws: this runs during cart
     * save, so any failure degrades to an empty token.
     *
     * @param int|string|null $quoteId
     * @return string '' when no token could be established
     */
    public function getOrCreate($quoteId)
    {
        $quoteId = (int)$quoteId;

        if ($quoteId <= 0) {
            return '';
        }

        try {
            $connection = $this->resource->getConnection(self::QUOTE_RESOURCE);
            $table      = $this->resource->getTableName(self::QUOTE_TABLE, self::QUOTE_RESOURCE);

            $existing = $this->read($connection, $table, $quoteId, false);
            if ($existing !== '') {
                // Generated once per quote and never rotated.
                return $existing;
            }

            // Sets the value only when it is not already set, so concurrent requests cannot
            // overwrite one another.
            $connection->update(
                $table,
                [self::COLUMN => $this->random->getRandomString(self::TOKEN_LENGTH, self::TOKEN_CHARS)],
                [
                    $connection->quoteInto('entity_id = ?', $quoteId),
                    '(' . self::COLUMN . ' IS NULL OR ' . self::COLUMN . " = '')"
                ]
            );

            // Re-read rather than trusting the affected row count, which reports rows
            // changed rather than rows matched.
            return $this->read($connection, $table, $quoteId, true);
        } catch (\Throwable $e) {
            $this->helperConfig->log($e->getMessage() . ' in ' . __METHOD__, LogLevel::ERROR);
            return '';
        }
    }

    /**
     * Constant time comparison. Returns false unless both values are non empty strings.
     *
     * @param mixed $storedToken
     * @param mixed $suppliedToken
     * @return bool
     */
    public function isValid($storedToken, $suppliedToken)
    {
        if (!is_string($storedToken) || $storedToken === '') {
            return false;
        }

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
            // Current read rather than a snapshot read, so the value returned matches what
            // is stored even inside an open transaction.
            $select->forUpdate(true);
        }

        $value = $connection->fetchOne($select);

        return is_string($value) ? $value : '';
    }
}
