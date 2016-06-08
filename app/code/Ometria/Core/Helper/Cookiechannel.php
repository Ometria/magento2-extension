<?php
namespace Ometria\Core\Helper; 
use Magento\Framework\App\Helper\AbstractHelper; 
use Magento\Framework\App\Helper\Context; 
class Cookiechannel extends AbstractHelper 
{

    const COOKIE_NAME = 'ommage';
    const SEPERATOR_BETWEEN_COMMANDS = ';';
    const SEPERATOR_IN_COMMANDS = ':';

    protected $helperConfig;
    
    protected $frontendAreaChecker;       
    
    protected $cookie_did_change = false;        
    
    public function __construct(
        Context $context,
        \Ometria\Core\Helper\Config $helperConfig,
        \Ometria\Core\Helper\Is\Frontend $frontendAreaChecker             
    )        
    {
        $this->helperConfig         = $helperConfig;    
        $this->frontendAreaChecker  = $frontendAreaChecker;        
        return parent::__construct($context);
    }
        
    public function addCommand($command, $replace_if_exists=false, $set_cookie=true){
        if (!$command || !is_array($command)) return;


        // Return if admin area or API call
        // if (Mage::app()->getStore()->isAdmin()) return;
        // if (Mage::getSingleton('api/server')->getAdapter() != null) return;
        if(!$this->frontendAreaChecker->check())
        {
            return;
        }

        //$ometria_config_helper = Mage::helper('ometria/config');
        $ometria_config_helper = $this->helperConfig;
        if (!$ometria_config_helper->isConfigured()) return;
        if (!$ometria_config_helper->isUnivarEnabled()) return;

        if ($command[0]=='identify') $command[1] = '';
        
        $str = implode(self::SEPERATOR_IN_COMMANDS, $command);

        $this->appendCookieCommand($command[0], $str, $replace_if_exists);
    }

    private function appendCookieCommand($command_name, $str, $replace_if_exists=false, $set_cookie=true){
        $existing_cookie = isset($_COOKIE[self::COOKIE_NAME]) ? $_COOKIE[self::COOKIE_NAME] : '';
        $commands = explode(self::SEPERATOR_BETWEEN_COMMANDS, $existing_cookie);        
        $new_cookie = '';

        if ($replace_if_exists && $commands) {
            $commands_filtered = array();
            foreach($commands as $command){
                if (strpos($command, $command_name.self::SEPERATOR_IN_COMMANDS)!==0) {
                    $commands_filtered[] = $command;
                }
            }
            $commands = $commands_filtered;
            $commands = array_filter($commands);
        }

        $commands[] = $str;
        if (count($commands)>6) $commands = array_slice($commands, 0, 6);

        $commands = array_unique($commands);
        $commands = array_filter($commands);
        $commands = array_values($commands);
        sort($commands);
        $commands = array_values($commands);

        $new_cookie = implode(self::SEPERATOR_BETWEEN_COMMANDS, $commands);
        if (strlen($new_cookie)>1000) $new_cookie = '';

        if (!headers_sent() && ($new_cookie!=$existing_cookie)) {
            $this->cookie_did_change = true;
            $_COOKIE[self::COOKIE_NAME] = $new_cookie;
            //setcookie(self::COOKIE_NAME, $new_cookie, 0, '/');
            //if ($set_cookie) Mage::getModel('core/cookie')
            //                    ->set(self::COOKIE_NAME, $new_cookie, 0, '/');
            if ($set_cookie) $this->sendCookie();
        }
    }
    
    public function sendCookie(){
        if (!$this->cookie_did_change) return;
        $cookie = isset($_COOKIE[self::COOKIE_NAME]) ? $_COOKIE[self::COOKIE_NAME] : '';
        setcookie(self::COOKIE_NAME, $cookie, 0, '/');
        $this->cookie_did_change = false;
    }    
}