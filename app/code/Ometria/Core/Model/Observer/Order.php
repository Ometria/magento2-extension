<?php
namespace Ometria\Core\Model\Observer; 
class Order 
{
    protected $helperPing;
    
    public function __construct(
        \Ometria\Core\Helper\Ping $helperPing
    )
    {    
        $this->helperPing = $helperPing;
    }
    
    /**
     * Sales Order After Save
     *
     * @param Varien_Event_Observer $observer
     * @return Ometria_Core_Model_Observer_Order
     */
    public function salesOrderSaveAfter(\Magento\Framework\Event\Observer $observer) {
        $ometria_ping_helper = $this->helperPing;
        $order = $observer->getEvent()->getOrder();
        $ometria_ping_helper->sendPing('transaction', $order->getIncrementId(), array(), $order->getStoreId());

        return $this;
    }
}
