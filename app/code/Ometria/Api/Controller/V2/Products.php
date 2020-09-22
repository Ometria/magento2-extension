<?php
namespace Ometria\Api\Controller\V2;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\Product\TypeFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Catalog\Pricing\Price\SpecialPrice;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Ometria\Api\Api\Data\ProductInterface as OmetriaProductInterface;
use Ometria\Api\Helper\Filter\V2\Service;
use Ometria\Api\Model\ResourceModel\Product as ProductResource;
use Ometria\Core\Helper\Product as ProductHelper;
use Magento\Framework\Controller\ResultInterface;
use Magento\CatalogInventory\Helper\Stock as StockHelper;

class Products extends Action
{
    /** @var ProductCollectionFactory */
    private $productCollectionFactory;

    /** @var StockRegistryInterface */
    private $stockRegistry;

    /** @var ProductResource */
    private $productResource;

    /** @var ProductAttributeRepositoryInterface */
    private $attributeRepository;

    /** @var CategoryRepository */
    private $categoryRepository;

    /** @var array */
    private $configurableProductParentIds;

    /** @var TypeFactory */
    private $typeFactory;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var ProductHelper */
    private $productHelper;

    /** @var StockHelper */
    private $stockHelper;

    /** @var Service */
    private $serviceV2;

    /** @var array */
    private $productCollections = [];

    /** @var array */
    private $bundleProductParentIds = [];

    /** @var array */
    private $groupedProductParentIds = [];

    /** @var array */
    private $productTypeLabels;

    /** @var array */
    private $attributeData = [];

    /** @var array */
    private $categoryData = [];

    /** @var array */
    private $productTypeData = [];

    /** @var array */
    private $storeCurrencies = [];

    /**
     * @param Context $context
     * @param ProductCollectionFactory $productCollectionFactory
     * @param StockRegistryInterface $stockRegistry
     * @param ProductResource $productResource
     * @param ProductAttributeRepositoryInterface $attributeRepository
     * @param CategoryRepository $categoryRepository
     * @param TypeFactory $typeFactory
     * @param StoreManagerInterface $storeManager
     * @param ProductHelper $productHelper
     * @param StockHelper $stockHelper
     * @param Service $serviceV2
     */
    public function __construct(
        Context $context,
        ProductCollectionFactory $productCollectionFactory,
        StockRegistryInterface $stockRegistry,
        ProductResource $productResource,
        ProductAttributeRepositoryInterface $attributeRepository,
        CategoryRepository $categoryRepository,
        TypeFactory $typeFactory,
        StoreManagerInterface $storeManager,
        ProductHelper $productHelper,
        StockHelper $stockHelper,
        Service $serviceV2
    ) {
        parent::__construct($context);

        $this->productCollectionFactory = $productCollectionFactory;
        $this->stockRegistry = $stockRegistry;
        $this->productResource = $productResource;
        $this->attributeRepository = $attributeRepository;
        $this->categoryRepository = $categoryRepository;
        $this->typeFactory = $typeFactory;
        $this->storeManager = $storeManager;
        $this->productHelper = $productHelper;
        $this->stockHelper = $stockHelper;
        $this->serviceV2 = $serviceV2;
    }

    /**
     * @return ResultInterface
     */
    public function execute()
    {
        $storeId = $this->_request->getParam(Service::PARAM_PRODUCT_STORE, 0);

        $collection = $this->getProductCollection($storeId);

        if ($this->_request->getParam(Service::PARAM_COUNT)) {
            $data = $this->getItemsCount($collection);
        } else {
            $data = $this->getItemsData($collection);
        }

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($data);
    }

    /**
     * Get the products collection for a given store ID
     *
     * @return Collection
     */
    private function getProductCollection($storeId)
    {
        if (!isset($this->productCollections[$storeId])) {
            $collection = $this->productCollectionFactory->create();
            $collection->addAttributeToSelect('*');
            $this->serviceV2->applyFiltersToCollection($collection);

            $this->stockHelper->addIsInStockFilterToCollection($collection);

            // Add product store filter
            $collection->addStoreFilter($storeId);

            // Sort products by ID
            $collection->addAttributeToSort('entity_id', 'asc');

            $this->productCollections[$storeId] = $collection;
        }

        return $this->productCollections[$storeId];
    }

    /**
     * @param Collection $collection
     * @return array
     */
    private function getItemsCount(Collection $collection)
    {
        return [
            'count' => $collection->getSize()
        ];
    }

    /**
     * @param Collection $collection
     * @return array
     */
    public function getItemsData(Collection $collection)
    {
        $products = [];

        $this->getParentProductIds($collection);

        /** @var ProductInterface $product */
        foreach ($collection as $product) {
            $products[] = $this->getProductData($product);
        }

        return $products;
    }

    /**
     * @param $collection
     */
    private function getParentProductIds($collection)
    {
        $productIds = $collection->getAllIds();
        $this->configurableProductParentIds = $this->productResource->getConfigurableProductParentIds($productIds);
        $this->bundleProductParentIds = $this->productResource->getBundleProductParentIds($productIds);
        $this->groupedProductParentIds = $this->productResource->getGroupedProductParentIds($productIds);
    }

    /**
     * @param ProductInterface $product
     * @return string[]
     */
    private function getProductData(ProductInterface $product)
    {
        $stockItem = $this->getStockItem($product);
        $parentId = $this->getParentId($product);

        // This is only required to maintain parity with V1 API property ordering, rather than just
        // instantiating a new keyed array with initial values here
        $productData = $this->initProductArray();

        $productData[OmetriaProductInterface::TYPE] = 'product';
        $productData[OmetriaProductInterface::ID] = $product->getId();
        $productData[OmetriaProductInterface::TITLE] = $product->getName();
        $productData[OmetriaProductInterface::SKU] = $product->getSku();
        $productData[OmetriaProductInterface::URL] = $this->getProductUrl($product);
        $productData[OmetriaProductInterface::IMAGE_URL] = $this->getImageUrl($product);
        $productData[OmetriaProductInterface::IS_VARIANT] = (bool) $parentId != null ? true : false;
        $productData[OmetriaProductInterface::PARENT_ID] = $parentId;
        $productData[OmetriaProductInterface::ATTRIBUTES] = $this->getAttributes($product);
        $productData[OmetriaProductInterface::IS_ACTIVE] = (bool) $product->getStatus();
        $productData[OmetriaProductInterface::STORES] = $product->getStoreIds();
        $productData[OmetriaProductInterface::IS_IN_STOCK] = (string) $stockItem->getIsInStock();
        $productData[OmetriaProductInterface::QTY] = (float) $stockItem->getManageStock() ? $stockItem->getQty() : 0;

        $this->appendProductPriceData($productData, $product);

        // Add listings data if required by request parameter
        if ($this->_request->getParam(Service::PARAM_PRODUCT_LISTING)) {
            $productData[OmetriaProductInterface::STORE_LISTINGS] = $this->getListings($product);
        }

        return $productData;
    }

    /**
     * Update product data array with prices that exist for the product
     * @param $productData
     * @param ProductInterface $product
     */
    private function appendProductPriceData(&$productData, ProductInterface $product)
    {
        $prices = $product->getPriceInfo()->getPrices();

        // Add pricing data to the product data array
        if ($price = $prices->get(RegularPrice::PRICE_CODE)->getValue()) {
            $productData[OmetriaProductInterface::PRICE] = $price;
        }

        if ($specialPrice = $prices->get(SpecialPrice::PRICE_CODE)->getValue()) {
            $productData[OmetriaProductInterface::SPECIAL_PRICE] = $specialPrice;
        }

        if ($finalPrice = $prices->get(FinalPrice::PRICE_CODE)->getValue()) {
            $productData[OmetriaProductInterface::FINAL_PRICE] = $finalPrice;
        }
    }

    /**
     * @param ProductInterface $product
     * @return StockItemInterface
     */
    private function getStockItem(ProductInterface $product)
    {
        return $this->stockRegistry->getStockItem($product->getId());
    }

    /**
     * @param ProductInterface $product
     * @return |null
     */
    private function getImageUrl(ProductInterface $product)
    {
        $imageId = $this->_request->getParam(Service::PARAM_PRODUCT_IMAGE, Service::PARAM_PRODUCT_DEFAULT_IMAGE_ID);

        if ($product->getTypeId() == Configurable::TYPE_CODE) {
            $preferredProduct = $this->getPreferredProduct($product);
            return $this->productHelper->getPreferredProductImageUrlV2($product, $imageId, $preferredProduct);
        }

        return $this->productHelper->getProductImageUrlV2($product, $imageId);
    }

    /**
     * @param ProductInterface $product
     * @return ProductInterface|false
     */
    private function getPreferredProduct(ProductInterface $product)
    {
        $preferredProductAttribute = $this->productHelper->getPreferredProductAttribute();
        if ($preferredProductAttribute) {
            // Load preferred product variant if SKU is defined for this product
            $preferredProductSku = $product->getData($preferredProductAttribute);
            $collection = $this->getProductCollection($product->getStoreId());
            $preferredProduct = $collection->getItemByColumnValue('sku', $preferredProductSku);

            if ($preferredProduct != null) {
                return $preferredProduct;
            }
        }

        return false;
    }

    /**
     * @param ProductInterface $product
     * @return int|null
     */
    private function getParentId(ProductInterface $product)
    {
        // if the product can be viewed individually, it should not be treated as a variant
        $visibilities = [
            Visibility::VISIBILITY_IN_CATALOG,
            Visibility::VISIBILITY_IN_SEARCH,
            Visibility::VISIBILITY_BOTH,
        ];

        if (in_array($product->getVisibility(), $visibilities)) {
            return null;
        }

        // if the product is associated to a configurable product, return the parent ID
        if (array_key_exists($product->getId(), $this->configurableProductParentIds)) {
            return $this->configurableProductParentIds[$product->getId()];
        }

        // if the product is associated to a bundle product, return the parent ID
        if (array_key_exists($product->getId(), $this->bundleProductParentIds)) {
            return $this->bundleProductParentIds[$product->getId()];
        }

        // if the product is associated to a grouped product, return the parent ID
        if (array_key_exists($product->getId(), $this->groupedProductParentIds)) {
            return $this->groupedProductParentIds[$product->getId()];
        }

        return null;
    }

    /**
     * @param ProductInterface $product
     * @return array
     */
    private function getAttributes(ProductInterface $product)
    {
        $attributes = [];

        // Retrieve custom attribute data
        foreach ($product->getCustomAttributes() as $attribute) {
            try {
                $attributeData = $this->getAttributeData($attribute->getAttributeCode());
            } catch (NoSuchEntityException $e) {
                continue;
            }

            $valueIdx = in_array($attributeData['input'], ['select', 'multiselect']) ? 'id' : 'value';

            $attributes[] = [
                'type' => $attribute->getAttributeCode(),
                $valueIdx => $attribute->getValue(),
                'label' => $attributeData['label']
            ];
        }

        // Retrieve categories as attribute data
        foreach ($product->getCategoryIds() as $categoryId) {
            try {
                $categoryData = $this->getCategoryData($categoryId);
            } catch (NoSuchEntityException $e) {
                continue;
            }

            $attributes[] = $categoryData;
        }

        // Retrieve Magento product type as attribute data
        $productTypeData = $this->getProductTypeData($product);
        if ($productTypeData != false) {
            $attributes[] = $productTypeData;
        }

        return $attributes;
    }

    /**
     * @param $attributeCode
     * @return array
     * @throws NoSuchEntityException
     */
    private function getAttributeData($attributeCode)
    {
        if (!isset($this->attributeData[$attributeCode])) {
            $attribute = $this->attributeRepository->get($attributeCode);

            $this->attributeData[$attributeCode] = [
                'input' => $attribute->getFrontendInput(),
                'label' => $attribute->getFrontendLabel()
            ];
        }

        return $this->attributeData[$attributeCode];
    }

    /**
     * @param $categoryId
     * @return array
     * @throws NoSuchEntityException
     */
    private function getCategoryData($categoryId)
    {
        if (!isset($this->categoryData[$categoryId])) {
            $category = $this->categoryRepository->get($categoryId);

            $this->categoryData[$categoryId] = [
                'type'      => 'category',
                'id'        => $categoryId,
                'url_key'   => $category->getUrlKey(),
                'url_path'  => $category->getUrlPath(),
                'label'     => $category->getName()
            ];
        }

        return $this->categoryData[$categoryId];
    }

    /**
     * Retrieve array of product type id top name mappings
     *
     * @param ProductInterface $product
     * @return array|false
     */
    private function getProductTypeData(ProductInterface $product)
    {
        $typeId = $product->getTypeId();

        if (!isset($this->productTypeData[$typeId])) {
            $typeLabels = $this->getProductTypeLabels();

            if (isset($typeLabels[$typeId])) {
                $productTypeData = [
                    'type' => 'magento_product_type',
                    'value' => $typeId,
                    'label' => $typeLabels[$typeId]
                ];
            } else {
                $productTypeData = false;
            }

            $this->productTypeData[$typeId] = $productTypeData;
        }

        return $this->productTypeData[$typeId];
    }

    /**
     * @return array
     */
    private function getProductTypeLabels()
    {
        if (!isset($this->productTypeLabels)) {
            $types = $this->typeFactory->create()->getTypes();

            foreach ($types as $typeId => $type) {
                $this->productTypeLabels[$typeId] = (string) $type['label'];
            }
        }

        return $this->productTypeLabels;
    }

    /**
     * @param ProductInterface $product
     * @return array
     */
    private function getListings(ProductInterface $product)
    {
        $listings = [];

        foreach ($product->getStoreIds() as $storeId) {
            $collection = $this->getProductCollection($storeId);
            $productInStore = $collection->getItemById($product->getId());

            $listing = [
                OmetriaProductInterface::STORE_ID => $storeId,
                OmetriaProductInterface::TITLE => $productInStore->getName(),
                OmetriaProductInterface::URL => $this->getProductUrl($productInStore),
                OmetriaProductInterface::STORE_CURRENCY => $this->getStoreCurrency($storeId),
                OmetriaProductInterface::VISIBILITY => (int) $productInStore->getVisibility(),
                OmetriaProductInterface::STATUS => (int) $productInStore->getStatus(),
                OmetriaProductInterface::IMAGE_URL => $this->getImageUrl($productInStore)
            ];

            $this->appendProductPriceData($listing, $productInStore);

            $listings[] = $listing;
        }

        return $listings;
    }

    /**
     * @param $storeId
     * @return string
     */
    private function getStoreCurrency($storeId)
    {
        if (!isset($this->storeCurrencies[$storeId])) {
            $stores = $this->storeManager->getStores();

            foreach ($stores as $store) {
                $this->storeCurrencies[$storeId] = $store->getDefaultCurrency()->getCode();
            }
        }

        return $this->storeCurrencies[$storeId];
    }

    /**
     * @param ProductInterface $product
     * @return mixed
     * @throws NoSuchEntityException
     */
    private function getProductUrl(ProductInterface $product)
    {
        $originalStore = $this->storeManager->getStore()->getId();
        $this->storeManager->setCurrentStore($product->getStoreId());
        $productUrl = $product->getProductUrl(false);
        $this->storeManager->setCurrentStore($originalStore);
        return $productUrl;
    }

    /**
     * Initialise product array to ensure ordering of properties is consitent with Product V1 endpoint.
     *
     * @deprecated
     * @return array
     */
    private function initProductArray()
    {
        return [
            OmetriaProductInterface::TYPE => null,
            OmetriaProductInterface::ID => null,
            OmetriaProductInterface::TITLE => null,
            OmetriaProductInterface::SKU => null,
            OmetriaProductInterface::PRICE => null,
            OmetriaProductInterface::URL => null,
            OmetriaProductInterface::IMAGE_URL => null,
            OmetriaProductInterface::IS_VARIANT => null,
            OmetriaProductInterface::PARENT_ID => null,
            OmetriaProductInterface::ATTRIBUTES => null,
            OmetriaProductInterface::IS_ACTIVE => null,
            OmetriaProductInterface::STORES => null
        ];
    }
}
