<?php
namespace Ometria\Core\Model\Observer\Cart;
use Ometria\Core\Model\Observer\Cart as BaseObserver; 
class OrderPlaced extends BaseObserver  implements \Magento\Framework\Event\ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        return $this->orderPlaced($observer);
    }
}
