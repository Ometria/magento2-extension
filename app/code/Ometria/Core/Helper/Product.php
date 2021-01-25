<?php
namespace Ometria\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Ometria\Core\Helper\Config as ConfigHelper;

class Product extends AbstractHelper
{
    /** @var ConfigHelper */
    private $helperConfig;

    /** @var ProductRepositoryInterface */
    private $productRepository;

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /**
     * @param Context $context
     * @param ProductRepositoryInterface $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ConfigHelper $helperConfig
     *
     */
    public function __construct(
        Context $context,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ConfigHelper $helperConfig
    ) {
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->helperConfig = $helperConfig;
        return parent::__construct($context);
    }

    public function getIdentifierForProduct($product) {
        if (!$product) return null;

        if ($this->helperConfig->isSkuMode()) {
            return $product->getSku();
        } else {
            return $product->getId();
        }
    }

    public function getIdentifiersForProducts($products) {
        $is_sku_mode = $this->helperConfig->isSkuMode();

        $ret = array();
        foreach($products as $product){
            if ($is_sku_mode) {
                $ret[] = $product->getSku();
            } else {
                $ret[] = $product->getId();
            }
        }

        return $ret;

    }

    public function convertProductIdsIfNeeded($ids){

        if (!$this->helperConfig->isSkuMode()) {
            return $ids;
        }

        if (!$ids) return $ids;

        $was_array = is_array($ids);
        if (!is_array($ids)) $ids = array($ids);

        $products_collection = Mage::getModel('catalog/product')
                ->getCollection()
                ->addAttributeToFilter('entity_id', array('in' => $ids));

        $skus = array();
        foreach($products_collection as $product) {
            $skus[] =  $product->getSku();
            $product->clearInstance();
        }

        if (!$was_array) {
            return count($skus)>0 ? $skus[0] : null;
        } else {
            return $skus;
        }
    }

    public function getProductByIdentifier($id){
        $product_model = Mage::getModel('catalog/product');

        if ($this->helperConfig->isSkuMode()){
            return $product_model->load($product_model->getIdBySku($id));
        } else {
            return $product_model->load($id);
        }
    }

    /**
     * @param $product
     * @param string $imageId
     * @param bool $usePreferredProduct
     * @return mixed|null
     * @throws \Exception
     */
    public function getProductImageUrl($product, $imageId = 'image', $usePreferredProduct = true)
    {
        if ($usePreferredProduct && $product->getTypeId() == Configurable::TYPE_CODE) {
            return $this->getPreferredProductImageUrl($product, $imageId);
        }

        // Return the relevant image URL for the requested image type (image, small_image, thumbnail, etc)
        $attribute = $product->getResource()->getAttribute($imageId);
        if ($product->getData($imageId) && $attribute) {
            return $attribute->getFrontend()->getUrl($product);
        }

        return null;
    }

    /**
     * @param ProductInterface $product
     * @param $imageId
     * @return mixed|null
     * @throws \Exception
     */
    private function getPreferredProductImageUrl(ProductInterface $product, $imageId)
    {
        // Ensure this is a configurable product
        if ($product->getTypeId() != Configurable::TYPE_CODE) {
            throw new \Exception("Preferred product image is available for configurable products only");
        }

        // Use the configurable's image if allowed and one is present
        if ($this->helperConfig->canUseConfigurableImage() && $product->getData($imageId)) {
            return $this->getProductImageUrl($product, $imageId, false);
        }

        // Use preferred product logic if configured
        $preferredProductAttribute = $this->helperConfig->getPreferredProductAttribute();
        if ($preferredProductAttribute) {
            // Load preferred product variant if SKU is defined for this product
            $preferredProductSku = $product->getData($preferredProductAttribute);
            if ($preferredProductSku) {
                try {
                    // Try to load the defined preferred product variant
                    $preferredProduct = $this->productRepository->get($preferredProductSku);
                }
                catch (NoSuchEntityException $e) {
                    // Prevent error if the preferred product no longer exists
                    $preferredProduct = false;
                }

                // Try to use image of preferred product variant if it has one and is enabled and in stock
                if ($preferredProduct && $preferredProduct->isSalable() && $preferredProduct->getData($imageId)) {
                    return $this->getProductImageUrl($preferredProduct, $imageId, false);
                }
            }

            // If preferred product is not set, has no stock or has no image then try to use any enabled, in-stock
            // variant with an image as the preferred product instead
            $preferredProduct = $this->getActiveInStockVariantWithImage($product);
            if ($preferredProduct) {
                return $this->getProductImageUrl($preferredProduct, $imageId, false);
            }
        }

        // Default to using the configurable product's image
        $attribute = $product->getResource()->getAttribute($imageId);
        if ($product->getData($imageId) && $attribute) {
            return $attribute->getFrontend()->getUrl($product);
        }

        // No valid image could be found
        return null;
    }

    /**
     * @param $product
     * @param string $imageId
     * @return string|null
     */
    public function getProductImageUrlV2($product, $imageId = 'image')
    {
        $imageUrl = null;

        $attribute = $product->getResource()->getAttribute($imageId);
        if ($product->getData($imageId) && $attribute) {
            $imageUrl = (string) $attribute->getFrontend()->getUrl($product);
        }

        return $imageUrl;
    }

    /**
     * @param ProductInterface $product
     * @param $imageId
     * @param false $preferredProduct
     * @return string|null
     */
    public function getPreferredProductImageUrlV2(ProductInterface $product, $imageId, $preferredProduct = false)
    {
        // Use the configurable's image if allowed and one is present
        if ($this->helperConfig->canUseConfigurableImage() && $product->getData($imageId)) {
            return $this->getProductImageUrlV2($product, $imageId);
        }

        // Try to use image of preferred product variant if it has one and is enabled and in stock
        if ($preferredProduct &&
            $preferredProduct->isSalable() &&
            $preferredProduct->getData($imageId)) {
            return $this->getProductImageUrlV2($preferredProduct, $imageId);
        }

        // If preferred product is not set, has no stock or has no image then try to use any enabled, in-stock
        // variant with an image as the preferred product instead
        $preferredProduct = $this->getActiveInStockVariantWithImage($product);
        if ($preferredProduct) {
            return $this->getProductImageUrlV2($preferredProduct, $imageId);
        }

        // No valid preferred product image could be found
        return null;
    }

    /**
     * Find a child product which is enabled, in stock and has an image
     * @param ProductInterface $product
     * @return ProductInterface | bool
     */
    private function getActiveInStockVariantWithImage(ProductInterface $product)
    {
        $childProducts = $product->getTypeInstance()
            ->getUsedProductCollection($product)
            ->addAttributeToFilter('is_saleable', ['eq' => 1]);

        if ($childProducts) {
            // Add media gallery data to collection
            $childProducts->addMediaGalleryData();

            // Can't filter by has image, so loop and return first product with an image
            foreach ($childProducts as $childProduct) {
                if ($childProduct->getMediaGalleryImages()->getSize() > 0) {
                    return $childProduct;
                }
            }
        }

        return false;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return bool
     */
    public function canShowProductPrice($product)
    {
        // Grouped products have no price themselves, so can't show a price for this product type
        if ($product->getTypeId() == \Magento\GroupedProduct\Model\Product\Type\Grouped::TYPE_CODE) {
            return false;
        }

        return true;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return mixed
     */
    public function getProductFinalPrice($product)
    {
        return $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return mixed
     */
    public function getProductRegularPrice($product)
    {
        return $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
    }

    /**
     * @return string
     */
    public function getPreferredProductAttribute()
    {
        return $this->helperConfig->getPreferredProductAttribute();
    }
}
