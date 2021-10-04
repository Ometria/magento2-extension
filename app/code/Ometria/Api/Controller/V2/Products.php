<?php
namespace Ometria\Api\Controller\V2;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\TypeFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Catalog\Pricing\Price\SpecialPrice;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Area as AppArea;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Api\Data\QuoteDetailsInterfaceFactory;
use Magento\Tax\Api\Data\QuoteDetailsItemInterfaceFactory;
use Magento\Tax\Api\Data\TaxClassKeyInterface;
use Magento\Tax\Api\Data\TaxClassKeyInterfaceFactory;
use Magento\Tax\Api\Data\TaxDetailsItemInterface;
use Magento\Tax\Api\TaxCalculationInterface;
use Magento\Tax\Model\Config as TaxConfig;
use Ometria\Api\Api\Data\ProductInterface as OmetriaProductInterface;
use Ometria\Api\Helper\Filter\V2\Service;
use Ometria\Api\Model\ResourceModel\Product as ProductResource;
use Ometria\Core\Helper\Product as ProductHelper;
use Ometria\Core\Service\Product\Inventory as InventoryService;

class Products extends Action
{
    /** @var ProductCollectionFactory */
    private $productCollectionFactory;

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

    /** @var Service */
    private $serviceV2;

    /** @var TaxClassKeyInterfaceFactory */
    private $taxClassKeyFactory;

    /** @var TaxConfig */
    private $taxConfig;

    /** @var QuoteDetailsInterfaceFactory */
    private $quoteDetailsFactory;

    /** @var QuoteDetailsItemInterfaceFactory */
    private $quoteDetailsItemFactory;

    /** @var TaxCalculationInterface */
    private $taxCalculationService;

    /** @var HttpContext */
    private $httpContext;

    /** @var AppEmulation */
    private $appEmulation;

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
     * @param ProductResource $productResource
     * @param ProductAttributeRepositoryInterface $attributeRepository
     * @param CategoryRepository $categoryRepository
     * @param TypeFactory $typeFactory
     * @param StoreManagerInterface $storeManager
     * @param ProductHelper $productHelper
     * @param Service $serviceV2
     * @param TaxClassKeyInterfaceFactory $taxClassKeyFactory
     * @param TaxConfig $taxConfig
     * @param QuoteDetailsInterfaceFactory $quoteDetailsFactory
     * @param QuoteDetailsItemInterfaceFactory $quoteDetailsItemFactory
     * @param TaxCalculationInterface $taxCalculationService
     * @param InventoryService $inventoryService
     * @param HttpContext $httpContext
     * @param AppEmulation $appEmulation
     */
    public function __construct(
        Context $context,
        ProductCollectionFactory $productCollectionFactory,
        ProductResource $productResource,
        ProductAttributeRepositoryInterface $attributeRepository,
        CategoryRepository $categoryRepository,
        TypeFactory $typeFactory,
        StoreManagerInterface $storeManager,
        ProductHelper $productHelper,
        Service $serviceV2,
        TaxClassKeyInterfaceFactory $taxClassKeyFactory,
        TaxConfig $taxConfig,
        QuoteDetailsInterfaceFactory $quoteDetailsFactory,
        QuoteDetailsItemInterfaceFactory $quoteDetailsItemFactory,
        TaxCalculationInterface $taxCalculationService,
        InventoryService $inventoryService,
        HttpContext $httpContext,
        AppEmulation $appEmulation
    ) {
        parent::__construct($context);

        $this->productCollectionFactory = $productCollectionFactory;
        $this->productResource = $productResource;
        $this->attributeRepository = $attributeRepository;
        $this->categoryRepository = $categoryRepository;
        $this->typeFactory = $typeFactory;
        $this->storeManager = $storeManager;
        $this->productHelper = $productHelper;
        $this->serviceV2 = $serviceV2;
        $this->taxClassKeyFactory = $taxClassKeyFactory;
        $this->taxConfig = $taxConfig;
        $this->quoteDetailsFactory = $quoteDetailsFactory;
        $this->quoteDetailsItemFactory = $quoteDetailsItemFactory;
        $this->taxCalculationService = $taxCalculationService;
        $this->inventoryService = $inventoryService;
        $this->httpContext = $httpContext;
        $this->appEmulation = $appEmulation;
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

            // Add in stock filter to collection if MSI not enabled (MSI does this on collection load)
            if (!$this->inventoryService->isMSIAvailable()) {
                $this->inventoryService->addLegacyStockFilterToCollection($collection);
            }

            // Add product store filter
            $collection->addStoreFilter($storeId);

            // Sort products by ID
            $collection->addAttributeToSort('entity_id', 'asc');

            $this->productCollections[$storeId] = $collection;
        }

        return $this->productCollections[$storeId];
    }

    /**
     * Get count, taking explicit page_size request param in to account (page_size is removed by getSize function).
     *
     * @param Collection $collection
     * @return array
     */
    private function getItemsCount(Collection $collection)
    {
        $count = $collection->getSize();

        if ($requestPageSize = $this->_request->getParam(Service::PARAM_PAGE_SIZE)) {
            $count = ($requestPageSize < $count) ? $requestPageSize : $count;
        }

        return [
            'count' => (int) $count
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
        $productData[OmetriaProductInterface::IS_ACTIVE] = (bool) ($product->getStatus() == ProductStatus::STATUS_ENABLED);
        $productData[OmetriaProductInterface::STORES] = $product->getStoreIds();
        $productData[OmetriaProductInterface::IS_IN_STOCK] = $this->inventoryService->getStockStatus($product);
        $productData[OmetriaProductInterface::QTY] = $this->inventoryService->getSalableQuantity($product);

        $this->appendProductPriceData($productData, $product);

        // Add listings data if required by request parameter
        if ($this->_request->getParam(Service::PARAM_PRODUCT_LISTING)) {
            $productData[OmetriaProductInterface::STORE_LISTINGS] = $this->getListings($product);
        }

        return $productData;
    }

    /**
     * @param ProductInterface $product
     * @return string|null
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
                OmetriaProductInterface::STORE_CURRENCY => $this->getStoreDefaultCurrency($storeId),
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
     * Update product data array with prices that exist for the product
     *
     * @param $productData
     * @param ProductInterface $product
     */
    private function appendProductPriceData(&$productData, ProductInterface $product)
    {
        $storeId = $product->getStoreId();
        $storeCurrency = $this->getStoreDefaultCurrency($storeId);

        // Override HTTP currency value to ensure Magento internals use correct store currency
        $beforeCurrency = $this->httpContext->getValue(HttpContext::CONTEXT_CURRENCY);
        $this->httpContext->setValue(HttpContext::CONTEXT_CURRENCY, $storeCurrency, null);

        // Emulate store as required to ensure Magento internals use correct store currency
        $this->appEmulation->startEnvironmentEmulation(
            $storeId,
            AppArea::AREA_FRONTEND,
            true
        );

        $priceInfo = $product->getPriceInfo();

        // Add regular price data to the product data array
        if ($price = $priceInfo->getPrice(RegularPrice::PRICE_CODE)->getAmount()->getValue()) {
            $productData[OmetriaProductInterface::PRICE] = $price;
        }

        // Add special price data to the product data array
        if ($specialPrice = $priceInfo->getPrice(SpecialPrice::PRICE_CODE)->getAmount()->getValue()) {
            $productData[OmetriaProductInterface::SPECIAL_PRICE] = $specialPrice;
        }

        // Add final price data to the product data array
        if ($finalPrice = $priceInfo->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getValue()) {
            $productData[OmetriaProductInterface::FINAL_PRICE] = $finalPrice;
        }

        // Add tax data to the product data array (this is currency converted internally)
        $taxDetailsItem = $this->getTaxDetails(
            $product,
            $finalPrice
        );

        $productData[OmetriaProductInterface::TAX_AMOUNT] = $taxDetailsItem->getRowTax();
        $productData[OmetriaProductInterface::FINAL_PRICE_INCL_TAX] = $taxDetailsItem->getPriceInclTax();

        // Stop emulating store
        $this->appEmulation->stopEnvironmentEmulation();

        // Reset HTTP currency value to before value
        $this->httpContext->setValue(HttpContext::CONTEXT_CURRENCY, $beforeCurrency, $beforeCurrency);
    }

    /**
     * @param $product
     * @param $finalPrice
     * @return TaxDetailsItemInterface
     */
    public function getTaxDetails($product, $finalPrice)
    {
        $priceIncludesTax = $this->taxConfig->priceIncludesTax($product->getStoreId());

        $taxClassKey = $this->taxClassKeyFactory->create();
        $taxClassKey->setType(TaxClassKeyInterface::TYPE_ID)
            ->setValue($product->getTaxClassId());

        $item = $this->quoteDetailsItemFactory->create();
        $item->setQuantity(1)
            ->setCode($product->getSku())
            ->setTaxClassKey($taxClassKey)
            ->setIsTaxIncluded($priceIncludesTax)
            ->setType('product')
            ->setUnitPrice($finalPrice);

        $quoteDetails = $this->quoteDetailsFactory->create();
        $quoteDetails->setItems([$item]);

        $taxDetails = $this->taxCalculationService->calculateTax($quoteDetails, $product->getStoreId(), true);

        $taxDetailItems = $taxDetails->getItems();

        return array_shift($taxDetailItems);
    }

    /**
     * @param $storeId
     * @return string
     */
    private function getStoreDefaultCurrency($storeId)
    {
        if (!isset($this->storeCurrencies[$storeId])) {
            $stores = $this->storeManager->getStores(true);

            foreach ($stores as $store) {
                $this->storeCurrencies[$store->getId()] = $store->getDefaultCurrency()->getCode();
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
