<?php
namespace Ometria\Core\Model\Observer\Order;
use Ometria\Core\Model\Observer\Order as BaseObserver;
class SalesOrderSaveAfter extends BaseObserver implements \Magento\Framework\Event\ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        return $this->salesOrderSaveAfter($observer);
    }
}