<?php
namespace Ometria\Api\Model\Observer;
use Ometria\Api\Controller\V1\Get\Settings;
use Ometria\Api\Model\Hash;

class Auth implements \Magento\Framework\Event\ObserverInterface
{
    protected $config;
    public function __construct(\Ometria\Api\Helper\Config $config)
    {
        $this->config = $config;
    }
    
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        return $this->checkHeader($observer);
    }
    
    public function checkHeader($observer)
    {        
        $public_key     = $this->config->get('general/apikey');
        $private_key    = $this->config->get('general/privatekey');                
        $method_name    = $this->getMethodNameFromObserver($observer);
        
        $is_authorized = Hash::checkRequest($method_name,$public_key,$private_key);
        if(!$is_authorized)
        {
            echo "Forbidden";
            header('HTTP/1.1 403 Forbidden');
            exit;
            //             throw new \Exception("Unauthorized");
        }
    }
    
    protected function getMethodNameFromObserver($observer)
    {
        $request = $observer->getRequest();
        return $request->getActionName();
    }       
}