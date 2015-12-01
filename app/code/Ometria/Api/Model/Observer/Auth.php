<?php
namespace Ometria\Api\Model\Observer;
use Ometria\Api\Controller\V1\Get\Settings;
use Ometria\Api\Model\Hash;

class Auth
{
    protected $config;
    public function __construct(\Ometria\Api\Helper\Config $config)
    {
        $this->config = $config;
    }
    
    public function checkHeader($observer)
    {
        //temporary dev skip
        if(array_key_exists('_skip', $_GET) && (time() < strToTime('2015-12-15')))
        {
            return;
        }
        
        $public_key     = $this->config->get('general/apikey');
        $private_key    = $this->config->get('general/privatekey');                
        $method_name    = $this->getMethodNameFromObserver($observer);
        
        $is_authorized = Hash::checkRequest($method_name,$public_key,$private_key);
        if(!$is_authorized)
        {
            throw new \Exception("Unauthorized");
        }
    }
    
    protected function getMethodNameFromObserver($observer)
    {
        $request = $observer->getRequest();
        return $request->getActionName();
    }       
}