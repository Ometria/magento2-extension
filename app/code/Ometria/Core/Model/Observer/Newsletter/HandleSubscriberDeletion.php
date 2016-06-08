<?php
namespace Ometria\Core\Model\Observer\Newsletter;
use Ometria\Core\Model\Observer\Newsletter as BaseObserver;
class HandleSubscriberDeletion extends BaseObserver implements \Magento\Framework\Event\ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        return $this->handleSubscriberDeletion($observer);
    }
}