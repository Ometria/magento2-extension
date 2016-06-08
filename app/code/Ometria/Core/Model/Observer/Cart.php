<?php

namespace Ometria\Core\Model\Observer; 
use Magento\Framework\Event\Observer;

class Cart   
{
    protected $frontendAreaChecker;
    protected $helperProduct;
    protected $helperCookiechannel;
    protected $cartModel;
    protected $storeManager;
    protected $helperPing;        
    protected $helperSession;
    protected $helperConfig;            
    
    public function __construct(
        \Ometria\Core\Helper\Product $helperProduct,    
        \Ometria\Core\Helper\Cookiechannel $helperCookiechannel,            
        \Ometria\Core\Helper\Is\Frontend $frontendAreaChecker,
        \Magento\Checkout\Model\Cart $cartModel,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Ometria\Core\Helper\Session $helperSession,        
        \Ometria\Core\Helper\Ping $helperPing,
        \Ometria\Core\Helper\Config $helperConfig               
    )
    {
        $this->frontendAreaChecker  = $frontendAreaChecker;
        $this->helperProduct        = $helperProduct;
        $this->helperCookiechannel  = $helperCookiechannel;                
        $this->cartModel            = $cartModel;
        $this->productFactory       = $productFactory;
        $this->storeManager         = $storeManager;
        $this->helperPing           = $helperPing;
        $this->helperSession        = $helperSession;   
        $this->helperConfig         = $helperConfig;     
    }
    
    public function basketUpdated(Observer $observer){
        // Return if admin area or API call
        // if (Mage::app()->getStore()->isAdmin()) return;
        // if (Mage::getSingleton('api/server')->getAdapter() != null) return;
        if(!$this->frontendAreaChecker->check())
        {
            return;
        }

        $this->updateBasketCookie();
    }

    public function updateBasketCookie() {

        //$ometria_product_helper = Mage::helper('ometria/product');
        //$ometria_cookiechannel_helper = Mage::helper('ometria/cookiechannel');
        
        $ometria_product_helper       = $this->helperProduct;
        $ometria_cookiechannel_helper = $this->helperCookiechannel;                
        
        // $cart = Mage::getModel('checkout/cart')->getQuote();
        $cart = $this->cartModel->getQuote();
        $cart_token = substr(md5($cart->getCreatedAt().$cart->getId()),0,12);

        $command = array(
                'basket',
                $cart->getId(),
                $cart->getGrandTotal(),
                $this->storeManager->getStore()->getCurrentCurrencyCode(),
                $cart_token
                );

        $count = 0;
        foreach($cart->getAllVisibleItems() as $item){
            //$product =  Mage::getModel('catalog/product')->load($item->getProductId());
            $product = $this->productFactory->create()->load($item->getProductId());
            $buffer = array(
                'i'=>$ometria_product_helper->getIdentifierForProduct($product),
                //'s'=>$product->getSku(),
                'v'=>$item->getSku(),
                'q'=>(int) $item->getQty(),
                't'=>(float) $item->getRowTotalInclTax()
                );
            $command_part = http_build_query($buffer);
            $command[] = $command_part;

            $count++;
            if ($count>30) break; // Prevent overly long cookies
        }

        $ometria_cookiechannel_helper->addCommand($command, true);

        // Identify if needed
        if ($cart->getCustomerEmail()) {
            $identify_type = 'checkout_billing';
            $data = array('e'=>$cart->getCustomerEmail());
            $command = array('identify', $identify_type, http_build_query($data));
            $ometria_cookiechannel_helper->addCommand($command, true);
        }

        return $this;
    }

    public function orderPlaced(Observer $observer){

        $ometria_session_helper         = $this->helperSession;
        $ometria_cookiechannel_helper   = $this->helperCookiechannel;

        try{
            $ometria_ping_helper = $this->helperPing;
            $order = $observer->getEvent()->getOrder();
            if(!$order) { return; }
            
            $session_id = $ometria_session_helper->getSessionId();
            if ($session_id) {
                $ometria_ping_helper->sendPing('transaction', $order->getIncrementId(), array('session'=>$session_id), $order->getStoreId());
            }
            $ometria_cookiechannel_helper->addCommand(array('trans', $order->getIncrementId()));

            // If via front end, also identify via cookie channel (but do not replace if customer login has done it)
            $is_frontend = $this->frontendAreaChecker->check();
            if ($is_frontend){
                $ometria_cookiechannel_helper = $this->helperCookiechannel;

                //assume guest checkout
                $identify_type = 'guest_checkout';
                $data = array('e'=>$order->getCustomerEmail());
                    
                //if we can get a customer from the order, override above                    
                $customer = $order->getCustomer();
                if ($customer) {
                    $identify_type = 'checkout';
                    $data = array('e'=>$customer->getEmail(),'i'=>$customer->getId());  
                }

                $command = array('identify', $identify_type, http_build_query($data));
                $ometria_cookiechannel_helper->addCommand($command, true);
            }
        } catch(Exception $e){
            $this->helperConfig->log($e->getMessage() . ' in ' . __METHOD__);
        }
    }
}
