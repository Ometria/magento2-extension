<?php
namespace Ometria\Api\Controller\V1;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Catalog\Pricing\Price\SpecialPrice;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Area as AppArea;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Ometria\Api\Helper\Filter\V1\Service as FilterService;
use Ometria\Api\Helper\Format\V1\Products as Helper;
use Ometria\Api\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Products extends Base
{
    const PRODUCT_TYPE_IDX = 'magento_product_type';

    protected $resultJsonFactory;
    protected $apiHelperServiceFilterable;
    protected $productRepository;
    protected $urlModel;
    protected $attributesFactory;
    protected $helperCategory;
    protected $response;
    protected $productCollectionFactory;
    protected $helperOmetriaApiFilter;
    protected $searchCriteria;
    protected $dataObjectProcessor;
    protected $storeManager;
    protected $metadataService;
    protected $searchCriteriaBuilder;
    protected $priceRender;
    protected $productFactory;
    protected $resourceConnection;
    protected $request;
    protected $directoryHelper;
    protected $storeUrlHelper;

    protected $scopeConfig;

    /** @var StockRegistryInterface */
    private $stockRegistry;

    /** @var HttpContext */
    private $httpContext;

    /** @var AppEmulation */
    private $appEmulation;

    /** @var ProductResource */
    private $productResource;

    protected $storeIdCache=false;
    protected $productTypeFactory;

    /**
    * Prevent twice joining visibility if its added as filter
    */
    protected $needsVisibilityJoin;
    protected $productTypeNames;
    protected $metadataServiceInterface;

    /**
     * Cache of child:parent relationships
     * @var array
     */
    protected $childParentConfigurableProductIds = [];
    protected $childParentBundleProductIds = [];
    protected $childParentGroupedProductIds = [];
    protected $context;

    public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Ometria\Api\Helper\Service\Filterable\Service\Product $apiHelperServiceFilterable,
		\Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
		\Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $attributesFactory,
		\Ometria\Api\Helper\Category $helperCategory,
		\Ometria\Api\Helper\Filter\V1\Service $helperOmetriaApiFilter,
		\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria,
		\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
		\Magento\Framework\Reflection\DataObjectProcessor $dataObjectProcessor,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Catalog\Api\ProductAttributeRepositoryInterface $metadataServiceInterface,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Directory\Helper\Data $directoryHelper,
        \Ometria\Api\Helper\StoreUrl $storeUrlHelper,
        \Magento\Catalog\Model\Product\TypeFactory $productTypeFactory,
        StockRegistryInterface $stockRegistry,
        HttpContext $httpContext,
        AppEmulation $appEmulation,
        ProductResource $productResource,
        ScopeConfigInterface $scopeConfig
	) {
		parent::__construct($context);
		$this->searchCriteriaBuilder      = $searchCriteriaBuilder;
		$this->metadataService            = $metadataServiceInterface;
		$this->resultJsonFactory          = $resultJsonFactory;
		$this->apiHelperServiceFilterable = $apiHelperServiceFilterable;
		$this->productRepository          = $productRepository;
		$this->attributesFactory          = $attributesFactory;
		$this->helperCategory             = $helperCategory;
		$this->response                   = $context->getResponse();
		$this->productCollectionFactory   = $productCollectionFactory;
		$this->helperOmetriaApiFilter     = $helperOmetriaApiFilter;
		$this->searchCriteria             = $searchCriteria;
		$this->dataObjectProcessor        = $dataObjectProcessor;
		$this->storeManager               = $storeManager;
		$this->productFactory             = $productFactory;
		$this->resourceConnection         = $resourceConnection;
		$this->directoryHelper            = $directoryHelper;
		$this->storeUrlHelper             = $storeUrlHelper;
        $this->productTypeFactory         = $productTypeFactory;
        $this->stockRegistry              = $stockRegistry;
        $this->httpContext                = $httpContext;
        $this->appEmulation               = $appEmulation;
        $this->productResource            = $productResource;
        $this->scopeConfig = $scopeConfig;
	}

    public function execute()
    {
        $items = $this->getProductItems();

        if ($this->_request->getParam(FilterService::PARAM_COUNT)) {
            $data = $this->getCountData($items);
        } else {
            $data = $this->getItemsData($items);
        }

        return $this->resultJsonFactory->create()->setData($data);
    }

    /**
     * @return array
     * @throws LocalizedException
     */
    private function getProductItems()
    {
        $collection = $this->productCollectionFactory->create();

        $searchCriteria = $this->helperOmetriaApiFilter
            ->applyFilertsToSearchCriteria($this->searchCriteria);

        $this->setCurrentStoreIfStoreIdFilterExists($searchCriteria);

        foreach ($this->metadataService->getList($this->searchCriteriaBuilder->create())->getItems() as $metadata) {
            $collection->addAttributeToSelect($metadata->getAttributeCode());
        }

        foreach ($searchCriteria->getFilterGroups() as $group) {
            $this->addFilterGroupToCollection($group, $collection);
        }

        if ($this->getRequest()->getParam('product_store')) {
            $collection->setStoreId($this->getRequest()->getParam('product_store'));
        }

        $collection->joinAttribute('status', 'catalog_product/status', 'entity_id', null, 'inner');

        if ($this->needsVisibilityJoin) {
            $collection->joinAttribute('visibility', 'catalog_product/visibility', 'entity_id', null, 'inner');
        }

        // Set default page size based on 'count' parameter being present or not
        $defaultPageSize = $this->_request->getParam(FilterService::PARAM_COUNT) ? false : 100;
        $pageSize = $this->getRequest()->getParam(FilterService::PARAM_PAGE_SIZE, $defaultPageSize);
        $collection->setPageSize($pageSize);

        $currentPage = $this->getRequest()->getParam(FilterService::PARAM_CURRENT_PAGE, 1);
        $collection->setCurPage($currentPage);

        // Sort by product entity ID for consistency
        $collection->addAttributeToSort('entity_id', 'asc');

        return $this->apiHelperServiceFilterable->processList(
            $collection,
            ProductInterface::class,
            $this->getRequest()->getParam('product_image', 'image')
        );
    }

    /**
     * @param $items
     * @return array
     */
    private function getCountData($items)
    {
        return [
            'count' => count($items)
        ];
    }

    /**
     * @param $collection
     * @return array
     */
    private function getItemsData($items)
    {
        if ($this->_request->getParam('listing') === 'true') {
            try {
                $items = $this->addStoreListingToItems($items, $this->resourceConnection);
            } catch (\Exception $e) {
                // pass
            }
        }

        $this->prepareChildParentRelationships($items);

        $items = array_map(function ($item){
            return $this->serializeItem($item);
        }, $items);

        return array_values($items);
    }

	protected function getArrayKey($array, $key)
	{
	    return array_key_exists($key, $array) ? $array[$key] : null;
	}

	protected function getImageUrlKey()
	{
	    $key = $this->getRequest()->getParam('product_image');

	    if ($key) {
	        return $key;
	    }

	    return 'image';
	}

	protected function serializeItem($item)
	{
        $tmp = Helper::getBlankArray();

        $tmp['id']          = strval($this->getArrayKey($item, 'id'));
        $tmp['title']       = $this->getArrayKey($item, 'name');
        $tmp['sku']         = $this->getArrayKey($item, 'sku');
        $tmp['url']         = $this->getArrayKey($item, 'url');
        $tmp['image_url']   = $this->getArrayKey($item, 'image_url');
        $tmp['attributes']  = [];
        $tmp['is_active']   = (bool) ($this->getArrayKey($item, 'status') == ProductStatus::STATUS_ENABLED);
        $tmp['stores']      = $this->getArrayKey($item, 'store_ids');
        $tmp['parent_id'] = $this->getVariantParentId($item);
        $tmp['is_variant'] = (bool) $tmp['parent_id'] != null ? true : false;

        if ($this->_request->getParam('raw') === 'true') {
            $tmp['_raw'] = $item;
        }

        $tmp = $this->appendStock($tmp['id'], $tmp);
        $tmp = $this->appendPricing($tmp['id'], $tmp);

        if (isset($item['store_listings'])) {
            $tmp['store_listings'] = $item['store_listings'];
        }

        //add attributes
        $attributes = $this->getArrayKey($item, 'custom_attributes');
        $attributes = $attributes ? $attributes : [];
        foreach ($attributes as $attribute) {
            $fullAttribute = $this->attributesFactory->create()
                ->addFieldToFilter('attribute_code', $attribute['attribute_code'])
                ->getFirstItem();

            $inputType = $fullAttribute->getFrontendInput();
            $type = $inputType == 'multiselect' ? '&' . $attribute['attribute_code'] : $attribute['attribute_code'];
            $valueIdx = in_array($inputType, ['select', 'multiselect']) ? 'id' : 'value';

            $tmp['attributes'][] = [
                'type'  => $type,
                $valueIdx => $attribute['value'],
                'label'   => $fullAttribute->getFrontendLabel()
            ];
        }

        $categoriesAsAttributes = $this->helperCategory->getOmetriaAttributeFromCategoryIds(
            $this->getArrayKey($item, 'category_ids')
        );

        foreach ($categoriesAsAttributes as $category) {
            $tmp['attributes'][] = $category;
        }

        if ($productTypeData = $this->getProductTypeData($item)) {
            $tmp['attributes'][] = $productTypeData;
        }

        return $tmp;
	}

    protected function setCurrentStoreIfStoreIdFilterExists($searchCriteria)
    {
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                if ($filter->getField() === 'store_id') {
                    foreach($filter->getValue() as $store_id) {
                        $this->storeManager->setCurrentStore(
                            $this->storeManager->getStore($store_id)
                        );
                        return;
                    }
                }
            }
        }
    }

    /**
    * Repository interface does not support store or website filtering
    */
    protected function addFilterGroupToCollection(
        \Magento\Framework\Api\Search\FilterGroup $filterGroup,
        \Magento\Catalog\Model\ResourceModel\Product\Collection $collection
    ) {
        $fields = [];
        foreach ($filterGroup->getFilters() as $filter) {
            if ($filter->getField() === 'store_id') {
                foreach ($filter->getValue() as $store_id) {
                    $store = $this->storeManager->getStore($store_id);
                    $collection->addStoreFilter($store);
                }
                continue;
            }

            if ($filter->getField() === 'website_ids') {
                foreach($filter->getValue() as $website_id) {
                    $website = $this->storeManager->getWebsite($website_id);
                    $collection->addWebsiteFilter($website);
                }
                continue;
            }

            if ($filter->getField() === 'visibility') {
                $this->needsVisibilityJoin = false;
                $collection->addFieldToFilter('visibility', ['in'=>$filter->getValue()]);
                continue;
            }

            $condition = $filter->getConditionType() ? $filter->getConditionType() : 'eq';
            $fields[] = ['attribute' => $filter->getField(), $condition => $filter->getValue()];
        }

        if (count($fields) > 1) {
            throw new \Exception("Can't handle multiple OR filters");
        }

        if ($fields) {
            $attribute = $fields[0]['attribute'];
            unset($fields[0]['attribute']);
            $filter = $fields[0];
            $collection->addFieldToFilter($attribute, $filter);
        }
    }

    protected function addStoreListingToItems($items)
    {
        $stores = $this->storeManager->getStores();
        $store_id_lookup = array();

        foreach ($stores as $store) {
            $id = $store->getId();
            $store_id_lookup[$id] = $store;
        }

        $all_store_ids = array();
        $all_product_ids = array();

        foreach ($items as $item) {
            $all_product_ids[] = $item['id'];
            foreach ($item['store_ids'] as $store_id) {
                $all_store_ids[$store_id] = $store_id;
            }
        }

        $store_listings = array();
        foreach ($all_store_ids as $store_id) {
            $store = $store_id_lookup[$store_id];
            $store_listings = $this->getProductListingsForStore($store, $all_product_ids, $store_listings);
        }

        $ret = array();
        foreach($items as $itemData) {
            $id = $itemData['id'];
            $itemData['store_listings'] = isset($store_listings[$id]) ? array_values($store_listings[$id]) : array();
            $ret[] = $itemData;
        }

        return $ret;
    }


    protected function getProductListingsForStore($store, $productIds, $storeListings)
    {
        $storeId = $store->getId();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $collectionFactory = $objectManager->create('Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');
        $collection = $collectionFactory
                ->create()
                ->addAttributeToSelect('*')
                ->setStoreId($storeId)
                ->addAttributeToFilter('entity_id', array('in' => $productIds));

        $items = $this->apiHelperServiceFilterable->processList(
            $collection,
            'Magento\Catalog\Api\Data\ProductInterface',
            $this->getRequest()->getParam('product_image', 'image')
        );

        $baseCurrency = $store->getBaseCurrency()->getCode();
        $storeCurrency = $store->getDefaultCurrency()->getCode();

        foreach ($items as $item) {
            $id = $item['id'];
            $url = $this->storeUrlHelper->getStoreUrlByProductIdAndStoreId($id, $storeId);
            $tmp = array(
                'store_id' => $storeId,
                'title' => $item['name'],
                'url' => $url,
                'store_currency' => $storeCurrency,
                'visibility' => $item['visibility'],
                'status' => $item['status'],
                'image_url' => $item['image_url']
            );

            $tmp = $this->appendPricing($id, $tmp, $storeId, $baseCurrency, $storeCurrency);

            $storeListings[$id][$storeId] = $tmp;
        }

        return $storeListings;
    }

    protected function appendPricing($productId, $item, $storeId = null, $baseCurrency = null, $storeCurrency = null)
    {
        $storePrice = $this->getProductPrice(
            $productId,
            $storeId,
            RegularPrice::PRICE_CODE,
            $baseCurrency,
            $storeCurrency
        );

        if ($storePrice) {
            $item['price'] = $storePrice;
        }

        $storeSpecialPrice = $this->getProductPrice(
            $productId,
            $storeId,
            SpecialPrice::PRICE_CODE,
            $baseCurrency,
            $storeCurrency
        );

        if ($storeSpecialPrice) {
            $item['special_price'] = $storeSpecialPrice;
        }

        if ($this->_request->getParam('final_price') === 'true') {
            $storeFinalPrice = $this->getProductPrice(
                $productId,
                $storeId,
                FinalPrice::PRICE_CODE,
                $baseCurrency,
                $storeCurrency
            );

            if ($storeFinalPrice) {
                $item['final_price'] = $storeFinalPrice;
            }
        }

        return $item;
    }

    /**
     * @param $productId
     * @param $item
     * @return mixed
     * @throws LocalizedException
     */
    private function appendStock($productId, $item)
    {
        $stockStatus = 0;
        $storeIds = $this->getStoreIdsForStock();
        if (empty($storeIds) || (count($storeIds) === 1 && $storeIds[0] === '*')) {
            $websiteId = $this->storeManager->getWebsite()->getId();
            $stockItem = $this->stockRegistry->getStockItem($productId, $websiteId);
            if (isset($stockItem['is_in_stock'])) {
                $item['is_in_stock'] = $stockItem['is_in_stock'];
            }
        } else {
            $productStoreIds = $storeIds;
            foreach($productStoreIds as $key => $storeId) {
                $store = $this->storeManager->getStore($storeId);
                $websiteId = $store->getWebsiteId();
                $stockItem = $this->stockRegistry->getStockItem($productId, $websiteId);
                $stockStatus = $this->stockRegistry->getProductStockStatus($productId, $websiteId);
            }
            $item['is_in_stock'] = $stockStatus;
        }

        if (isset($stockItem['qty'])) {
            $item['qty'] = (float) $stockItem['qty'];
        }

        return $item;
    }

    private function getStoreIdsForStock(){
        $configStoreIds = $this->scopeConfig->getValue('ometria/advanced/stock_store_ids', ScopeInterface::SCOPE_STORE);
        if (empty($configStoreIds)) {
            return [];
        }
        $storeIds = array_filter(array_map('trim', explode(PHP_EOL, $configStoreIds)));
        return $storeIds;
    }

    protected function getProductPrice(
        $productId,
        $storeId,
        $priceCode,
        $baseCurrency = null,
        $storeCurrency = null
    ) {
        // Override HTTP currency value to ensure Magento internals use correct store currency
        $beforeCurrency = $this->httpContext->getValue(HttpContext::CONTEXT_CURRENCY);
        $this->httpContext->setValue(HttpContext::CONTEXT_CURRENCY, $storeCurrency, null);

        $product = $this->productRepository->getById($productId, false, $storeId);

        // Emulate store as required to ensure Magento internals use correct store currency
        $this->appEmulation->startEnvironmentEmulation(
            $product->getStoreId(),
            AppArea::AREA_FRONTEND,
            true
        );

        $price = $product->getPriceInfo()->getPrice($priceCode)->getValue();

        // Final price is already converted so skip it here
        if ($priceCode != FinalPrice::PRICE_CODE && $storeCurrency && $baseCurrency) {
            try {
                $price = $this->directoryHelper->currencyConvert(
                    $price,
                    $baseCurrency,
                    $storeCurrency
                );
            } catch (\Exception $e) {
                // Allow the "undefined rate" exception and return the price as is if no rate has been setup.
            }
        }

        // Stop emulating store
        $this->appEmulation->stopEnvironmentEmulation();

        // Reset HTTP currency value to before value
        $this->httpContext->setValue(HttpContext::CONTEXT_CURRENCY, $beforeCurrency, $beforeCurrency);

        return $price;
    }

    /**
     * @param array $items
     */
    protected function prepareChildParentRelationships(array $items)
    {
        // retrieve all Product IDs from the data being processed
        $allProductIds = [];
        foreach ($items as $_item) {
            $_productId = $this->getArrayKey($_item, 'id');
            if (!$_productId) {
                continue;
            }

            $allProductIds[] = $_productId;
        }

        // fetch array of Configurable Product relationships, filtered by the items being processed
        $this->childParentConfigurableProductIds = $this->productResource->getConfigurableProductParentIds($allProductIds);

        // fetch array of Bundle Product relationships, filtered by the items being processed
        $this->childParentBundleProductIds = $this->productResource->getBundleProductParentIds($allProductIds);

        // fetch array of Grouped Product relationships, filtered by the items being processed
        $this->childParentGroupedProductIds = $this->productResource->getGroupedProductParentIds($allProductIds);
    }

    /**
     * @param array $item
     * @return int|bool
     */
    protected function getVariantParentId($item)
    {
        $productId = $this->getArrayKey($item, 'id');

        // if the product can be viewed individually, it should not be treated as a variant
        $visibleInSiteVisibilities = [
            \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG,
            \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_SEARCH,
            \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH,
        ];
        $visibility = $this->getArrayKey($item, 'visibility');
        if (in_array($visibility, $visibleInSiteVisibilities)) {
            return null;
        }

        // if the product is associated to a configurable product, return the parent ID
        if (array_key_exists($productId, $this->childParentConfigurableProductIds)) {
            return $this->childParentConfigurableProductIds[$productId];
        }

        // if the product is associated to a bundle product, return the parent ID
        if (array_key_exists($productId, $this->childParentBundleProductIds)) {
            return $this->childParentBundleProductIds[$productId];
        }

        // if the product is associated to a grouped product, return the parent ID
        if (array_key_exists($productId, $this->childParentGroupedProductIds)) {
            return $this->childParentGroupedProductIds[$productId];
        }

        return null;
    }

    /**
     * @param $item
     * @return array
     */
    protected function getProductTypeData($item)
    {
        $typeId = $this->getArrayKey($item, 'type_id');
        $typeName = $this->getProductTypeNameById($typeId);

        return [
            'type' => self::PRODUCT_TYPE_IDX,
            'value' => $typeId,
            'label' => $typeName
        ];
    }

    /**
     * @param $typeId
     * @return string
     */
    protected function getProductTypeNameById($typeId)
    {
        $typeNames = $this->getProductTypeNames();

        if (isset($typeNames[$typeId])) {
            $name = $typeNames[$typeId];
        } else {
            // Default to uppercased type_id (this should never happen).
            $name = ucwords($typeId);
        }

        return $name;
    }

    /**
     * Retrieve array of product type id top name mappings
     * @return mixed
     */
    protected function getProductTypeNames()
    {
        if (!isset($this->productTypeNames)) {
            $types = $this->productTypeFactory->create()->getTypes();

            foreach ($types as $type) {
                if (isset($type['name']) && isset($type['label'])) {
                    $this->productTypeNames[$type['name']] = $type['label']->getText();
                }
            }
        }

        return $this->productTypeNames;
    }
}
