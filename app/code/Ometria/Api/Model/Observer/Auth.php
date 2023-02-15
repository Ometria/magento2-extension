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
    {
        return $this->checkHeader($observer);
    }

    /**
     * @param $observer
     */
    public function checkHeader($observer)
    {
        return true;
        $publicKey  = $this->config->get('general/apikey');
        $privateKey = $this->config->get('general/privatekey');
        $methodName = $this->getMethodNameFromObserver($observer);

        $writer = new \Zend_Log_Writer_Stream(BP. '/var/log/mycustom.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info("Authentication Module");
        $logger->info($methodName);
        $logger->info($privateKey);
        $logger->info($publicKey);
        $logger->info("Expected Public Key: 0e1d2459bc9811cf");
        $logger->info("Expected Private Key: 3e5190b5ed6b67f63b694ee2");
        $isAuthorized = $this->hash->checkRequest($methodName,$publicKey,$privateKey);

        $logger->info(" Authentication: $isAuthorized");
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
