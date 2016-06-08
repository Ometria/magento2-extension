<?php
namespace Ometria\Core\Model\Observer\Newsletter;
use Ometria\Core\Model\Observer\Newsletter as BaseObserver;
class HandleSubscriberUpdate extends BaseObserver implements \Magento\Framework\Event\ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        return $this->handleSubscriberUpdate($observer);
    }
}