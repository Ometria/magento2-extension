<?php
namespace Ometria\Core\Model\Observer; 
class Customer
{
    var $did_register = false;

    protected $helperPing;
    protected $helperCookiechannel;
    protected $customerSession;
    
    public function __construct(
        \Ometria\Core\Helper\Cookiechannel $helperCookiechannel,    
        \Ometria\Core\Helper\Ping $helperPing,
        \Magento\Customer\Model\Session $customerSession
    )
    {
        $this->helperPing           = $helperPing;              
        $this->helperCookiechannel  = $helperCookiechannel;        
        $this->customerSession      = $customerSession;
    }
        
    public function customerSaveAfter($observer) {
        $ometria_ping_helper = $this->helperPing;
        $customer = $observer->getEvent()->getCustomer();
        $ometria_ping_helper->sendPing('customer', $customer->getId(), array(), $customer->getStoreId());

        return $this;
    }

    public function loggedOut($observer){
        $this->identify('logout');
    }

    public function loggedIn($observer){
        $this->identify('login');
    }

    public function registered($observer){
        $this->did_register = true;
        $this->identify('register');
    }

    protected function identify($event){
        //$ometria_cookiechannel_helper = Mage::helper('ometria/cookiechannel');
        $ometria_cookiechannel_helper   = $this->helperCookiechannel;
        if ($this->did_register && $event=='login') {
            $event = 'register';
        }


        //$customer = Mage::getSingleton('customer/session')->getCustomer();
        $customer = $this->customerSession->getCustomer();
        
        //if (!$customer) return;
        $data = array('e'=>$customer->getEmail(),'i'=>$customer->getId());
        $command = array('identify', $event, http_build_query($data));

        $ometria_cookiechannel_helper->addCommand($command, true);
    }
}