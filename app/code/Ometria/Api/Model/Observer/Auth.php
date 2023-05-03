<?php
namespace Ometria\Api\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Ometria\Api\Helper\Config as ConfigHelper;
use Ometria\Api\Model\Hash;

class Auth implements ObserverInterface
{
    /** @var ConfigHelper */
    private $config;

    /** @var Hash */
    private $hash;

    /**
     * @param ConfigHelper $config
     * @param Hash $hash
     */
    public function __construct(
        ConfigHelper $config,
        Hash $hash
    ) {
        $this->config = $config;
        $this->hash = $hash;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {   print("##################################### Inside EXecute ############################### ")
        return $this->checkHeader($observer);
    }

    /**
     * @param $observer
     */
    public function checkHeader($observer)
    {
        $publicKey  = $this->config->get('general/apikey');
        $privateKey = $this->config->get('general/privatekey');
        $methodName = $this->getMethodNameFromObserver($observer);
        $writer = new \Laminas\Log\Writer\Stream(BP . '/var/log/auth.log');
        $logger = new  \Laminas\Log\Logger();
        $logger->addWriter($writer);
        $logger->info('Inside Checkheader to check if we are getting public key or not !!');
        
        $isAuthorized = $this->hash->checkRequest($methodName,$publicKey,$privateKey);

        if (!$isAuthorized) {
            echo "Forbidden";
            header('HTTP/1.1 403 Forbidden');
            exit;
        }
    }

    /**
     * @param $observer
     * @return mixed
     */
    protected function getMethodNameFromObserver($observer)
    {
        $request = $observer->getRequest();
        return $request->getActionName();
    }
}
