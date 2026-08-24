<?php

namespace Ometria\Core\Model\Observer;
use Magento\Framework\Event\Observer;
use Ometria\Core\Helper\CartToken;

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
    protected $productFactory;
    protected $helperCartToken;

    public function __construct(
        \Ometria\Core\Helper\Product $helperProduct,
        \Ometria\Core\Helper\Cookiechannel $helperCookiechannel,
        \Ometria\Core\Helper\Is\Frontend $frontendAreaChecker,
        \Magento\Checkout\Model\Cart $cartModel,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Ometria\Core\Helper\Session $helperSession,
        \Ometria\Core\Helper\Ping $helperPing,
        \Ometria\Core\Helper\Config $helperConfig,
        ?CartToken $helperCartToken = null
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

        // Appended as optional and resolved lazily on purpose. Cart\BasketUpdated and
        // Cart\OrderPlaced are constructor-less subclasses that inherit this signature, so a
        // merchant deploying with a stale generated/ and no setup:di:compile would otherwise
        // hit an ArgumentCountError on the ORDER PLACEMENT path.
        $this->helperCartToken      = $helperCartToken
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(CartToken::class);
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

        // For newly created carts, reload the model to get created_at value added by database
        if ($cart->getCreatedAt() == null) {
            $cart = $cart->load($cart->getId());
        }

        // Per quote random token, generated once on first cart save and stored on the quote
        // row. Must stay after the reload above: Quote::load() resets _data and would
        // silently discard the setData() below.
        $cart_token = $this->helperCartToken->getOrCreate($cart->getId());

        if ($cart_token !== '') {
            // Keep the in memory quote in step with the row we just wrote. AbstractDb
            // includes any field where hasData() is true, so a quote loaded before our write
            // still holds NULL here and a later $quote->save() in this request would write
            // that NULL straight back over the token.
            $cart->setData(CartToken::COLUMN, $cart_token);
        }

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
            $product = $this->productFactory->create()->load($this->getMasterProductId($item));
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

    /**
     * @param \Magento\Quote\Model\Quote\Item $item
     * @return int
     */
    protected function getMasterProductId($item)
    {
        $productIdToLoad = $item->getProductId();

        // for Grouped Products, use the Parent Product ID instead of the Child ID
        $superProductConfig = $item->getBuyRequest()->getData('super_product_config');
        if (
            is_array($superProductConfig)
            && array_key_exists('product_type', $superProductConfig)
            && $superProductConfig['product_type'] == \Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE
        ) {
            $productIdToLoad = !empty($superProductConfig['product_id'])
                ? $superProductConfig['product_id']
                : $productIdToLoad;
        }

        // For configurable products use the Child Product ID instead of the Parent ID
        if (
            $item->getProductType() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE
        ) {
            $childId = $item->getOptionByCode('simple_product')->getProduct()->getId();
            $productIdToLoad = !empty($childId) ? $childId : $productIdToLoad;
        }

        return $productIdToLoad;
    }
}
