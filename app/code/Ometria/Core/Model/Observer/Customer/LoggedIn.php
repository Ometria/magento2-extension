<?php
namespace Ometria\Core\Model\Observer\Customer;
use Ometria\Core\Model\Observer\Customer as BaseObserver;
class LoggedIn extends BaseObserver implements \Magento\Framework\Event\ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        return $this->loggedIn($observer);
    }
}