<?php
/**
 * Class Ometria_Core_Model_Observer_Product
 */
namespace Ometria\Core\Model\Observer;

class Product {

    /**
     * @var \Magento\Catalog\Helper\Product\Edit\Action\Attribute
     */
    protected $catalogProductEditActionAttributeHelper;
    protected $helperPing;
    protected $helperProduct;
    protected $helperRequest;
    public function __construct(
        \Magento\Catalog\Helper\Product\Edit\Action\Attribute $catalogProductEditActionAttributeHelper,
        \Ometria\Core\Helper\Ping $helperPing, 
        \Ometria\Core\Helper\Product $helperProduct,
        \Ometria\Core\Helper\Get\Request $request              
    ) {
        $this->catalogProductEditActionAttributeHelper = $catalogProductEditActionAttributeHelper;
        $this->helperPing    = $helperPing;        
        $this->helperProduct = $helperProduct;        
        $this->helperRequest = $request;
    }
    /**
     * Catalog Product Delete After
     *
     * @param Varien_Event_Observer $observer
     * @return Ometria_Core_Model_Observer_Product
     */
    public function catalogProductDeleteAfter(\Magento\Framework\Event\Observer $observer) {
        \Magento\Framework\Profiler::start("Ometria::" . __METHOD__);

        $product = $observer->getEvent()->getProduct();
        $this->updateProducts($product->getId());

        \Magento\Framework\Profiler::stop("Ometria::" . __METHOD__);

        return $this;
    }

    /**
     * Catalog Product Save After
     *
     * @param Varien_Event_Observer $observer
     * @return Ometria_Core_Model_Observer_Product
     */
    public function catalogProductSaveAfter(\Magento\Framework\Event\Observer $observer) {
        \Magento\Framework\Profiler::start("Ometria::" . __METHOD__);

        $product = $observer->getEvent()->getProduct();
        $this->updateProducts($product->getId());

        \Magento\Framework\Profiler::stop("Ometria::" . __METHOD__);

        return $this;
    }

    /**
     * Product Mass Action - Update Attributes
     *
     * @param Varien_Event_Observer $observer
     * @return Ometria_Core_Model_Observer_Product
     */
    public function catalogProductUpdateAttributes(\Magento\Framework\Event\Observer $observer) {
        \Magento\Framework\Profiler::start("Ometria::" . __METHOD__);

        $productIds = $this->catalogProductEditActionAttributeHelper->getProductIds();
        $this->updateProducts($productIds);

        \Magento\Framework\Profiler::stop("Ometria::" . __METHOD__);

        return $this;
    }

    /**
     * Product Mass Action - Update Status
     *
     * @param Varien_Event_Observer $observer
     * @return Ometria_Core_Model_Observer_Product
     */
    public function catalogProductUpdateStatus(\Magento\Framework\Event\Observer $observer) {
        \Magento\Framework\Profiler::start("Ometria::" . __METHOD__);

        //$productIds = Mage::app()->getFrontController()->getRequest()->getParam('product');
        $productIds = $this->helperRequest->getParam('selected');
        $this->updateProducts($productIds);

        \Magento\Framework\Profiler::stop("Ometria::" . __METHOD__);

        return $this;
    }


    /**
     * Pass product ids to Ometria API model
     *
     * @param $ids
     * @return bool
     *
     */
    protected function updateProducts($ids) {
        //$ometria_ping_helper = Mage::helper('ometria/ping');
        //$ometria_product_helper = Mage::helper('ometria/product');        
        $ometria_ping_helper = $this->helperPing;
        $ometria_product_helper = $this->helperProduct;

        $ids = $ometria_product_helper->convertProductIdsIfNeeded($ids);

        $ometria_ping_helper->sendPing('product', $ids);
    }
}