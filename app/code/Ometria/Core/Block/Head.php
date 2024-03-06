<?php
namespace Ometria\Core\Block;

use Magento\Catalog\Model\Product as ProductModel;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\OrderFactory;
use Magento\Search\Model\QueryFactory;
use Magento\Search\Model\QueryInterface;
use Magento\Store\Model\ScopeInterface;
use Ometria\Core\Helper\Product as ProductHelper;
use Ometria\Core\Service\Product\Inventory as InventoryService;

class Head extends Template
{
    const PAGE_TYPE_BASKET       = 'basket';
    const PAGE_TYPE_CHECKOUT     = 'checkout';
    const PAGE_TYPE_CMS          = 'content';
    const PAGE_TYPE_CATEGORY     = 'listing';
    const PAGE_TYPE_CONFIRMATION = 'confirmation';
    const PAGE_TYPE_HOMEPAGE     = 'homepage';
    const PAGE_TYPE_PRODUCT      = 'product';
    const PAGE_TYPE_SEARCH       = 'search';

    const OM_PRODUCT_MAP         = 'product_map';
    const OM_QUERY               = 'query';
    const OM_SITE                = 'store';
    const OM_PAGE_TYPE           = 'type';
    const OM_PAGE_DATA           = 'data';

    private $scopeConfig;
    private $storeModelStoreManagerInterface;
    private $magentoFrameworkUrlInterface;
    private $magentoFrameworkRegistry;
    private $catalogModelProductFactory;
    private $frameworkAppRequestInterface;
    private $checkoutModelCart;
    private $checkoutModelSession;
    private $salesModelOrder;
    private $query;
    private $inventoryService;
    private $coreHelperProduct;
    private $salesModelOrderFactory;

    /**
     * @param Context $context
     * @param Registry $magentoFrameworkRegistry
     * @param ProductFactory $catalogModelProductFactory
     * @param ProductHelper $coreHelperProduct
     * @param Cart $checkoutModelCart
     * @param Session $checkoutModelSession
     * @param OrderFactory $salesModelOrderFactory
     * @param QueryInterface $query
     * @param InventoryService $inventoryService
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $magentoFrameworkRegistry,
        ProductFactory $catalogModelProductFactory,
        ProductHelper $coreHelperProduct,
        Cart $checkoutModelCart,
        Session $checkoutModelSession,
        OrderFactory $salesModelOrderFactory,
        QueryInterface $query,
        InventoryService $inventoryService,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->query                            = $query;
        $this->salesModelOrderFactory           = $salesModelOrderFactory;
        $this->checkoutModelCart                = $checkoutModelCart;
        $this->checkoutModelSession             = $checkoutModelSession;
        $this->frameworkAppRequestInterface     = $context->getRequest();
        $this->coreHelperProduct                = $coreHelperProduct;
        $this->catalogModelProductFactory       = $catalogModelProductFactory;
        $this->magentoFrameworkRegistry         = $magentoFrameworkRegistry;
        $this->inventoryService                 = $inventoryService;
        $this->magentoFrameworkUrlInterface     = $context->getUrlBuilder();
        $this->storeModelStoreManagerInterface  = $context->getStoreManager();
        $this->scopeConfig                      = $context->getScopeConfig();
    }

    /**
     * @return string
     */
    public function getAPIKey()
    {
        return (string) $this->scopeConfig->getValue(
            'ometria/general/apikey',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function isDatalayerEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            'ometria/advanced/univar',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function isCookiebotEnabled()
    {
        return (bool) $this->scopeConfig->isSetFlag('ometria/advanced/enable_cookiebot');
    }

    /**
     * @return string
     */
    public function getCookiebotClass()
    {
        return (string) $this->scopeConfig->getValue('ometria/advanced/cookiebot_classification');
    }

    /**
     * @return bool
     */
    public function isTrackingEnabled()
    {
        $enabled = true;

        // Check config for tracking allowed if on checkout page
        if ($this->_isCheckout()) {
            $enabled = $this->scopeConfig->isSetFlag(
                'ometria/advanced/checkout_tracking_enabled',
                ScopeInterface::SCOPE_STORE
            );
        }

        return $enabled;
    }

    /**
     * @return bool
     */
    public function pageViewOnVariantEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            'ometria/advanced/pageview_on_variant',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return array
     */
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
            if ($query = $this->_getSearchQuery()) {
                $page[self::OM_QUERY] = $query;
            }
        } elseif ($this->_isProduct()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_PRODUCT;
            $page[self::OM_PAGE_DATA] = $this->_getProductPageData();
            $page[self::OM_PRODUCT_MAP] = $this->_getProductMappingData();
        } elseif ($this->_isBasket()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_BASKET;
        } elseif ($this->_isCheckout()) {
            $page[self::OM_PAGE_TYPE] = self::PAGE_TYPE_CHECKOUT;
            if ($step = $this->_getCheckoutStep()) {
                $page[self::OM_PAGE_DATA] = array('step'=>$step);
            }
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

    /**
     * @return array|bool
     */
    private function _getProductPageData()
    {
        $product = $this->getCurrentProduct();

        if ($product) {
            return $this->_getProductInfo($product);
        }

        return false;
    }

    /**
     * @param $product
     * @return array|bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function _getProductInfo($product)
    {
        if ($product instanceof ProductModel) {
            $productInfo = array(
                'id'        => $this->coreHelperProduct->getIdentifierForProduct($product),
                'sku'       => $product->getSku(),
                'name'      => $product->getName(),
                'url'       => $product->getProductUrl(),
                'in_stock'  => $this->inventoryService->getStockStatus($product)
            );

            /**
             * Only show image info for products with an image
             */
            if ($imageUrl = $this->coreHelperProduct->getProductImageUrl($product)) {
                $productInfo['image_url'] = $imageUrl;
            }

            /**
             * Only show price info for product types where relevant
             */
            if ($this->coreHelperProduct->canShowProductPrice($product)) {
                $price = $this->coreHelperProduct->getProductRegularPrice($product);
                $finalPrice = $this->coreHelperProduct->getProductFinalPrice($product);

                $productInfo['currency'] = $this->_getCurrencyCode();
                $productInfo['price'] = $price;

                // Only show 'special_price' if discounted price is less than regular price.
                if ($finalPrice < $price) {
                    $productInfo['special_price'] = $finalPrice;
                }
            }

            return $productInfo;
        }

        return false;
    }

    /**
     * @return array|bool
     */
    private function _getProductMappingData()
    {
        $product = $this->getCurrentProduct();

        /**
         * Only include child map for configurable products
         */
        if ($product && $product->getTypeId() == Configurable::TYPE_CODE) {
            return $this->_getConfigurableProductMap($product);
        }

        return false;
    }

    /**
     * @param $product
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function _getConfigurableProductMap($product)
    {
        $productMap = [];
        $childProducts = $product->getTypeInstance()->getUsedProducts($product);

        if (count($childProducts) > 0) {
            foreach ($childProducts as $childProduct) {
                $productMap[$childProduct->getId()] = $this->_getProductInfo($childProduct);

                // Override to always give URL of parent product
                $productMap[$childProduct->getId()]['url'] = $product->getProductUrl();
            }
        }

        return $productMap;
    }

    /**
     * @return bool
     */
    private function _isHomepage()
    {
        return $this->getUrl('') == $this->getUrl('*/*/*', array('_current' => true, '_use_rewrite' => true));
    }

    /**
     * @return int
     * @throws NoSuchEntityException
     */
    private function _getStore()
    {
        return $this->storeModelStoreManagerInterface->getStore()->getStoreId();
    }

    /**
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function _getCurrencyCode()
    {
        return $this->storeModelStoreManagerInterface->getStore()->getCurrentCurrency()->getCode();
    }

    /**
     * @return string
     */
    private function _getRouteName()
    {
        return (string) $this->getRequest()->getRouteName();
    }

    /**
     * @return string
     */
    private function _getControllerName()
    {
        return (string) $this->getRequest()->getControllerName();
    }

    /**
     * @return string
     */
    private function _getActionName()
    {
        return (string) $this->getRequest()->getActionName();
    }

    /**
     * @return bool
     */
    private function _isProduct()
    {
        return $this->_getRouteName()      == 'catalog'
            && $this->_getControllerName() == 'product';
    }

    /**
     * @return bool
     */
    private function _isCMSPage()
    {
        return $this->_getRouteName() == 'cms';
    }

    /**
     * @return bool
     */
    private function _isCategory()
    {
        return $this->_getRouteName()       == 'catalog'
            && $this->_getControllerName()  == 'category';
    }

    /**
     * @return bool
     */
    private function _isSearch()
    {
        return $this->_getRouteName() == 'catalogsearch';
    }

    /**
     * @return bool
     */
    private function _isBasket()
    {
        return $this->_getRouteName()           == 'checkout'
                && $this->_getControllerName()  == 'cart'
                && $this->_getActionName()      == 'index';
    }

    /**
     * @return bool
     */
    private function _isCheckout()
    {
        return strpos($this->_getRouteName(), 'checkout') !== false
                && $this->_getActionName()  != 'success';
    }

    /**
     * @return string|bool
     */
    private function _getCheckoutStep()
    {
        if (!$this->_isCheckout()) {
            return false;
        }
        if ($step = $this->frameworkAppRequestInterface->getParam('step')) {
            return (string) $step;
        }

        return false;
    }

    /**
     * @return bool
     */
    private function _isOrderConfirmation()
    {
        return strpos($this->_getRouteName(), 'checkout') !== false
                && $this->_getActionName() == 'success';
    }

    /**
     * @return array|bool
     */
    private function _getOrderData()
    {
        if (!$this->_isOrderConfirmation())
            return false;

        if ($orderId = $this->_getCheckoutSession()->getLastOrderId()) {
            /** @var Order $order */
            $order = $this->salesModelOrderFactory->create()->load($orderId);

            return array(
                'id' => $order->getIncrementId()
            );
        }

        return false;
    }

    /**
     * @return Cart|Session
     */
    private function _getCheckoutSession()
    {
        if ($this->_isBasket()) {
            return $this->checkoutModelCart;
        }

        return $this->checkoutModelSession;
    }

    /**
     * @return string|bool
     */
    private function _getSearchQuery()
    {
        if (!$this->_isSearch()) {
            return false;
        }

        $paramNames = [
            QueryFactory::QUERY_VAR_NAME,
            'q',
            'query_text'
        ];

        foreach ($paramNames as $param) {
            $text = $this->getRequest()->getParam($param);
            if ($text) {
                return (string) $text;
            }
        }

        return false;
    }

    /**
     * @return ProductModel|bool
     */
    private function getCurrentProduct()
    {
        $product = $this->magentoFrameworkRegistry->registry("current_product");

        if (!$product && $id = $this->getProductId()) {
            $product = $this->catalogModelProductFactory->create()->load($id);
        }

        if ($product) {
            return $product;
        }

        return false;
    }
}
