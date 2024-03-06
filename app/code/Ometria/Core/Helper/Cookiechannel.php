<?php
namespace Ometria\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Ometria\Core\Helper\Is\Frontend as IsFrontend;

class Cookiechannel extends AbstractHelper
{
    const COOKIE_NAME                = 'ommage';
    const COOKIEBOT_COOKIE_NAME      = 'CookieConsent';
    const SEPERATOR_BETWEEN_COMMANDS = ';';
    const SEPERATOR_IN_COMMANDS      = ':';

    /** @var Config */
    private $helperConfig;

    /** @var IsFrontend */
    private $frontendAreaChecker;

    /** @var bool */
    private $cookieDidChange = false;
    protected $context;

    /**
     * @param Context $context
     * @param Config $helperConfig
     * @param IsFrontend $frontendAreaChecker
     */
    public function __construct(
        Context $context,
        Config $helperConfig,
        IsFrontend $frontendAreaChecker
    ) {
        $this->helperConfig         = $helperConfig;
        $this->frontendAreaChecker  = $frontendAreaChecker;

        return parent::__construct($context);
    }

    /**
     * @param $command
     * @param bool $replaceIfExists
     */
    public function addCommand($command, bool $replaceIfExists = false)
    {
        if (!$command || !is_array($command)) {
            return;
        }

        // Return if admin area or API call
        if(!$this->frontendAreaChecker->check()) {
            return;
        }

        if (!$this->helperConfig->isConfigured()) {
            return;
        }

        if (!$this->helperConfig->isUnivarEnabled()) {
            return;
        }

        if ($command[0] === 'identify') {
            $command[1] = '';
        }

        $str = implode(self::SEPERATOR_IN_COMMANDS, $command);

        $this->appendCookieCommand($command[0], $str, $replaceIfExists);
    }

    /**
     * @param $commandName
     * @param $str
     * @param bool $replaceIfExists
     */
    private function appendCookieCommand($commandName, $str, bool $replaceIfExists = false)
    {
        $existingCookie = isset($_COOKIE[self::COOKIE_NAME]) ? $_COOKIE[self::COOKIE_NAME] : '';
        $commands = explode(self::SEPERATOR_BETWEEN_COMMANDS, $existingCookie);
        $newCookie = '';

        if ($replaceIfExists && $commands) {
            $commandsFiltered = array();
            foreach($commands as $command){
                if (strpos($command, $commandName . self::SEPERATOR_IN_COMMANDS) !== 0) {
                    $commandsFiltered[] = $command;
                }
            }
            $commands = $commandsFiltered;
            $commands = array_filter($commands);
        }

        $commands[] = $str;
        if (count($commands) > 6) {
            $commands = array_slice($commands, 0, 6);
        }

        $commands = array_unique($commands);
        $commands = array_filter($commands);
        $commands = array_values($commands);
        sort($commands);
        $commands = array_values($commands);

        $newCookie = implode(self::SEPERATOR_BETWEEN_COMMANDS, $commands);
        if (strlen($newCookie) > 1000) {
            $newCookie = '';
        }

        if (!headers_sent() && ($newCookie != $existingCookie)) {
            $this->cookieDidChange = true;
            $_COOKIE[self::COOKIE_NAME] = $newCookie;

            $this->sendCookie();
        }
    }

    public function sendCookie()
    {
        if (!$this->cookieDidChange) {
            return;
        }

        if ($this->helperConfig->isCookiebotEnabled() && $this->cookiebotCookieAllowed() === false) {
            return;
        }

        $cookie = isset($_COOKIE[self::COOKIE_NAME]) ? $_COOKIE[self::COOKIE_NAME] : '';
        setcookie(self::COOKIE_NAME, $cookie, 0, '/');
        $this->cookieDidChange = false;
    }

    /**
     * @return bool
     */
    private function cookiebotCookieAllowed()
    {
        $cookieAllowed = false;

        if (isset($_COOKIE[self::COOKIEBOT_COOKIE_NAME])) {
            switch ($_COOKIE[self::COOKIEBOT_COOKIE_NAME]) {
                case "-1":
                    //The user is not within a region that requires consent - all cookies are accepted
                    $cookieAllowed = true;
                    break;

                default:
                    // The user must give their consent
                    $cookieConsent = $this->getCookiebotConsent();
                    if ($cookieConsent) {
                        $cookieClass = $this->helperConfig->getCookiebotClass();
                        // Consent cookie was found
                        if (isset($cookieConsent[$cookieClass]) && filter_var($cookieConsent[$cookieClass], FILTER_VALIDATE_BOOLEAN)) {
                            //Current user accepts Ometria cookies
                            $cookieAllowed = true;
                        }
                    }
                    break;
            }
        }

        return $cookieAllowed;
    }

    /**
     * Read current user consent in encoded JavaScript format from Cookiebot cookie
     *
     * @see https://www.cookiebot.com/en/developer/
     * @return mixed
     */
    private function getCookiebotConsent()
    {
        $json = preg_replace('/\s*:\s*([a-zA-Z0-9_]+?)([}\[,])/',
            ':"$1"$2',
            preg_replace('/([{\[,])\s*([a-zA-Z0-9_]+?):/',
                '$1"$2":',
                str_replace("'",
                    '"',
                    stripslashes($_COOKIE[self::COOKIEBOT_COOKIE_NAME])
                )
            )
        );
        return json_decode($json, true);
    }
}
