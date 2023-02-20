<?php
/**
 * Class Ometria_Core_Model_Observer_Product
 */
namespace Ometria\Core\Model\Observer;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;

class Product {

    /**
     * @var \Magento\Catalog\Helper\Product\Edit\Action\Attribute
     */
    protected $catalogProductEditActionAttributeHelper;
    protected $helperPing;
    protected $helperProduct;
    protected $helperRequest;

    /** @var ConfigurableType */
    private $configurableType;

    public function __construct(
        \Magento\Catalog\Helper\Product\Edit\Action\Attribute $catalogProductEditActionAttributeHelper,
        \Ometria\Core\Helper\Ping $helperPing,
        \Ometria\Core\Helper\Product $helperProduct,
        \Ometria\Core\Helper\Get\Request $request,
        ConfigurableType $configurableType
    ) {
        $this->catalogProductEditActionAttributeHelper = $catalogProductEditActionAttributeHelper;
        $this->helperPing    = $helperPing;
        $this->helperProduct = $helperProduct;
        $this->helperRequest = $request;
        $this->configurableType = $configurableType;
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
        $this->updateAssociatedProducts($product->getId());

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
        $this->updateAssociatedProducts($product->getId());

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
        $this->updateAssociatedProducts($productIds);

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
        $this->updateAssociatedProducts($productIds);

        \Magento\Framework\Profiler::stop("Ometria::" . __METHOD__);

        return $this;
    }


    /**
     * Pass product ids to Ometria API model
     * @param $ids
     */
    private function updateProducts($ids)
    {
        $ids = $this->helperProduct->convertProductIdsIfNeeded($ids);
        $this->helperPing->sendPing('product', $ids);
    }

    /**
     * Pass product ids of parent configurables affected by updates to Ometria API model
     * @param $ids
     */
    private function updateAssociatedProducts($ids)
    {
        $assocIds = array();

        // Standardise product IDs to array
        if ( ! empty( $ids ) && ! is_array($ids) ) {
            $ids = explode(',', $ids);
        }

        // Check for configurable parent products affected
        if (is_array($ids) || is_object($ids)) {
            foreach ($ids as $id) {
                $parentIds = $this->configurableType->getParentIdsByChild($id);
                foreach ($parentIds as $parentId) {
                    $assocIds[] = $parentId;
                }
            }
        }

        // Ping Ometria with unique parent Ids, if any
        if (count($assocIds)) {
            $assocIds = $this->helperProduct->convertProductIdsIfNeeded(array_unique($assocIds));
            $this->helperPing->sendPing('product', $assocIds);
        }
    }
}
