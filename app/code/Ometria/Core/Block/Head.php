<?php
namespace Ometria\Core\Block;
use stdClass;

class Head extends \Magento\Framework\View\Element\Template
{
    const PAGE_TYPE_BASKET       = 'basket';
    const PAGE_TYPE_CHECKOUT     = 'checkout';
    const PAGE_TYPE_CMS          = 'content';
    const PAGE_TYPE_CATEGORY     = 'listing';
    const PAGE_TYPE_CONFIRMATION = 'confirmation';
    const PAGE_TYPE_HOMEPAGE     = 'homepage';
    const PAGE_TYPE_PRODUCT      = 'product';
    const PAGE_TYPE_SEARCH       = 'search';

    const OM_QUERY               = 'query';
    const OM_SITE                = 'store';
    const OM_PAGE_TYPE           = 'type';
    const OM_PAGE_DATA           = 'data';
    
    protected $scopeConfig;
    protected $storeModelStoreManagerInterface;
    protected $magentoFrameworkUrlInterface;
    protected $magentoFrameworkRegistry;
    protected $catalogModelProductFactory;
    protected $frameworkAppRequestInterface;
    protected $checkoutModelCart;
    protected $checkoutModelSession;
    protected $salesModelOrder;
    protected $stockRegistry;
    protected $query;
    
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context, 
        \Magento\Framework\Registry $magentoFrameworkRegistry,   
        \Magento\Catalog\Model\ProductFactory $catalogModelProductFactory,  
        \Ometria\Core\Helper\Product $coreHelperProduct,  
        \Magento\Checkout\Model\Cart $checkoutModelCart, 
        \Magento\Checkout\Model\Session $checkoutModelSession, 
        \Magento\Sales\Model\OrderFactory $salesModelOrderFactory,    
        \Magento\Search\Model\QueryInterface $query,    
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,          
        array $data = []
    )
    {
        $this->query                            = $query;
        $this->salesModelOrderFactory           = $salesModelOrderFactory;
        $this->checkoutModelCart                = $checkoutModelCart;
        $this->checkoutModelSession             = $checkoutModelSession;
            
        $this->frameworkAppRequestInterface     = $context->getRequest();
        $this->coreHelperProduct                = $coreHelperProduct;
        $this->catalogModelProductFactory       = $catalogModelProductFactory;
        $this->magentoFrameworkRegistry         = $magentoFrameworkRegistry;    
        $this->stockRegistry                    = $stockRegistry;
        $this->magentoFrameworkUrlInterface     = $context->getUrlBuilder();    
        $this->storeModelStoreManagerInterface  = $context->getStoreManager();    
        $this->scopeConfig                      = $context->getScopeConfig();
        return parent::__construct($context, $data);
    }
    
    public function getAPIKey()
    {
        return $this->scopeConfig->getValue('ometria/general/apikey');
    }
    
    public function isUnivarEnabled()
    {
        return $this->scopeConfig->getValue('ometria/advanced/univar');
    }
    
    public function getDataLayer()
    {
        $category = 'null';
        $page = array();
        $page[self::OM_SITE] = $this->_getStore();
        $page['store_url']   = $this->magentoFrameworkUrlInterface->getBaseUrl();

        $page['route'] = $this->_getRouteName();
        $page['controller'] = $this->_getControllerName();
        $page['action'] = $this->_getActionName();

        if ($this->_isHomepage()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_HOMEPAGE;

        } elseif ($this->_isCMSPage()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_CMS;

        } elseif ($this->_isCategory()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_CATEGORY;

        } elseif ($this->_isSearch()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_SEARCH;

            if($query = $this->_getSearchQuery()) $page[self::OM_QUERY] = $query;

        } elseif ($this->_isProduct()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_PRODUCT;
            $page[self::OM_PAGE_DATA] = $this->_getProductPageData();            
        } elseif ($this->_isBasket()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_BASKET;

        } elseif ($this->_isCheckout()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_CHECKOUT;
            if ($step = $this->_getCheckoutStep()) $page[self::OM_PAGE_DATA] = array('step'=>$step);

        } elseif ($this->_isOrderConfirmation()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_CONFIRMATION;
            $page[self::OM_PAGE_DATA] = $this->_getOrderData();
        }

        if ($category = $this->magentoFrameworkRegistry->registry("current_category")) {
            $page['category'] = array(
                'id'=>$category->getId(),
                'path'=>$category->getUrlPath()
                );
        }

        return $page;    
    }

    protected function _isHomepage() {
        return $this->getUrl('') == $this->getUrl('*/*/*', array('_current'=>true, '_use_rewrite'=>true));
    }
    
    protected function _getStore() {
        return $this->storeModelStoreManagerInterface->getStore()->getStoreId();
    }    
    
    protected function _getRouteName() {
        return $this->getRequest()->getRouteName();
    }  
    
    protected function _getControllerName() {
        return $this->getRequest()->getControllerName();
    }

    protected function _getActionName() {
        return $this->getRequest()->getActionName();
    }   
    
    protected function _isProduct() {
        return $this->_getRouteName()      == 'catalog'
            && $this->_getControllerName() == 'product';
    }
        
    protected function _isCMSPage() {
        return $this->_getRouteName() == 'cms';
    }

    protected function _isCategory() {
        return $this->_getRouteName()       == 'catalog'
            && $this->_getControllerName()  == 'category';
    }  
    
    protected function _isSearch() {
        return $this->_getRouteName() == 'catalogsearch';
    }
     
    protected function _getProductPageData(){
        $product = $this->magentoFrameworkRegistry->registry("current_product");

        if (!$product && $id = $this->getProductId()) {
            $product = $this->catalogModelProductFactory->create()->load($id);
        }    

        if ($product) {
            return $this->_getProductInfo($product);
        }
        return false;
    }   
    
    protected function _getProductInStock($product)
    {
        $stock = $this->stockRegistry->getStockItem($product->getId());
        return (boolean) $stock->getIsInStock();
    }
    
    protected function _getProductInfo( $product) {
        $ometria_product_helper = $this->coreHelperProduct;
        
        if($product instanceof \Magento\Catalog\Model\Product) {
            return array(
                'id'                              => $ometria_product_helper->getIdentifierForProduct($product),
                'sku'                             => $product->getSku(),
                'name'                            => $product->getName(),
                'url'                             => $product->getProductUrl(),
                'in_stock'                        => $this->_getProductInStock($product)
            );
        }

        return false;
    }    
    
    protected function _isBasket() {
        return $this->_getRouteName()           == 'checkout'
                && $this->_getControllerName()  == 'cart'
                && $this->_getActionName()      == 'index';
    }
    
    protected function _isCheckout() {
        return strpos($this->_getRouteName(), 'checkout') !== false
                && $this->_getActionName()  != 'success';
    }
    
    protected function _getCheckoutStep() {
        if(!$this->_isCheckout())
        {
            return false;
        }
        if($step = $this->frameworkAppRequestInterface->getParam('step'))
        {
            return $step;
        }

        return false;
    }    

    protected function _isOrderConfirmation() {
        return strpos($this->_getRouteName(), 'checkout') !== false
                && $this->_getActionName() == 'success';
    }

    protected function _getOrderData() {
        if (!$this->_isOrderConfirmation())
            return false;

        if ($orderId = $this->_getCheckoutSession()->getLastOrderId()) {
            /** @var Order $order */
            $order = $this->salesModelOrderFactory->create()->load($orderId);

            return array(
                'id'              => $order->getIncrementId()
            );
        }

        return false;
    }    
    
    protected function _getCheckoutSession() {
        if ($this->_isBasket())
            return $this->checkoutModelCart;

        return $this->checkoutModelSession;
    }

    protected function _getSearchQuery()
    {            
        if(!$this->_isSearch())
            return false;
        
        $param_names = [
            \Magento\Search\Model\QueryFactory::QUERY_VAR_NAME,
            'q',
            'query_text'
        ];
        
        foreach($param_names as $param)
        {
            $text = $this->getRequest()->getParam($param);
            if($text)
            {
                return $text;
            }
        }         
        return false;   
    }          
}