<?php
namespace Ometria\Api\Controller\V1;

use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Exception\LocalizedException;
use Ometria\Api\Helper\Format\V1\Products as Helper;
use Ometria\Api\Controller\V1\Base;

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
    protected $productCollection;
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

    /** @var StockRegistryInterface */
    private $stockRegistry;

    protected $storeIdCache=false;
    protected $productTypeFactory;

    /**
    * Prevent twice joining visibility if its added as filter
    */
    protected $needsVisibilityJoin;
    protected $productTypeNames;

    /**
     * Cache of child:parent relationships
     * @var array
     */
    protected $childParentConfigurableProductIds = [];
    protected $childParentBundleProductIds = [];
    protected $childParentGroupedProductIds = [];

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Ometria\Api\Helper\Service\Filterable\Service\Product $apiHelperServiceFilterable,
		\Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
		\Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $attributesFactory,
		\Ometria\Api\Helper\Category $helperCategory,
		\Ometria\Api\Helper\Filter\V1\Service $helperOmetriaApiFilter,
		\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria,
		\Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection,
		\Magento\Framework\Reflection\DataObjectProcessor $dataObjectProcessor,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Catalog\Api\ProductAttributeRepositoryInterface $metadataServiceInterface,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Directory\Helper\Data $directoryHelper,
        \Ometria\Api\Helper\StoreUrl $storeUrlHelper,
        \Magento\Catalog\Model\Product\TypeFactory $productTypeFactory,
        StockRegistryInterface $stockRegistry
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
		$this->productCollection          = $productCollection;
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
	}

	protected function getArrayKey($array, $key)
	{
	    return array_key_exists($key, $array) ? $array[$key] : null;
	}

	protected function getImageUrlKey()
	{
	    $key = $this->getRequest()->getParam('product_image');
	    if($key)
	    {
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
        $tmp['is_active']   = $this->getArrayKey($item, 'status') !== \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED;
        $tmp['stores']      = $this->getArrayKey($item, 'store_ids');

        // Add parent ID if this is a variant simple product
        if ($variantParentId = $this->getVariantParentId($item)) {
            $tmp['parent_id'] = $variantParentId;
            $tmp['is_variant'] = true;
        }

        if($this->_request->getParam('raw') === 'true') {
            $tmp['_raw'] = $item;
        }

        $tmp = $this->appendPricing($tmp['id'], $tmp);
        $tmp = $this->appendStock($tmp['id'], $tmp);

        if (isset($item['store_listings'])) {
            $tmp['store_listings'] = $item['store_listings'];
        }

        //add attributes
        $attributes = $this->getArrayKey($item, 'custom_attributes');
        $attributes = $attributes ? $attributes : [];
        foreach($attributes as $attribute)
        {
            $full_attribute       = $this->attributesFactory->create()
                ->addFieldToFilter('attribute_code', $attribute['attribute_code'])
                ->getFirstItem();

            $key = 'value';
            if(in_array($full_attribute->getFrontendInput(),['select', 'multiselect']))
            {
                $key = 'id';
            }
            $tmp['attributes'][] = [
                'type' =>$attribute['attribute_code'],
                $key   =>$attribute['value'],
                'label'=>$full_attribute->getFrontendLabel()
            ];
        }

        $categories_as_attributes = $this->helperCategory
            ->getOmetriaAttributeFromCategoryIds(
                $this->getArrayKey($item, 'category_ids'));
        foreach($categories_as_attributes as $category)
        {
            $tmp['attributes'][] = $category;
        }

        if ($productTypeData = $this->getProductTypeData($item)) {
            $tmp['attributes'][] = $productTypeData;
        }

        return $tmp;
	}

	protected function getItemsForJson()
	{
        $searchCriteria = $this->helperOmetriaApiFilter
            ->applyFilertsToSearchCriteria($this->searchCriteria);
        $this->setCurrentStoreIfStoreIdFilterExists($searchCriteria);

        $collection = $this->productCollection;
        foreach ($this->metadataService->getList($this->searchCriteriaBuilder->create())->getItems() as $metadata) {
            $collection->addAttributeToSelect($metadata->getAttributeCode());
        }

        foreach ($searchCriteria->getFilterGroups() as $group) {
            $this->addFilterGroupToCollection($group, $collection);
        }

        $page_size = $this->getRequest()->getParam(\Ometria\Api\Helper\Filter\V1\Service::PARAM_PAGE_SIZE);
        $page_size = $page_size ? $page_size : 100;
        $collection->setPageSize($page_size);

        $current_page = $this->getRequest()->getParam(\Ometria\Api\Helper\Filter\V1\Service::PARAM_CURRENT_PAGE);
        $current_page = $current_page ? $current_page : 1;
        $collection->setCurPage($current_page);

        if ($this->getRequest()->getParam('product_store')){
            $collection->setStoreId($this->getRequest()->getParam('product_store'));
        }

        $collection->joinAttribute('status', 'catalog_product/status', 'entity_id', null, 'inner');

        if($this->needsVisibilityJoin)
        {
            $collection->joinAttribute('visibility', 'catalog_product/visibility', 'entity_id', null, 'inner');
        }

        $items = $this->apiHelperServiceFilterable->processList(
            $collection,
            'Magento\Catalog\Api\Data\ProductInterface',
            $this->getRequest()->getParam('product_image', 'image')
        );

        if($this->_request->getParam('listing') === 'true')
        {
            try {
                $items = $this->addStoreListingToItems($items, $this->resourceConnection);
            } catch (\Exception $e){
                // pass
            }
        }

        $this->prepareChildParentRelationships($items);

        $items      = array_map(function($item){
            return $this->serializeItem($item);
        }, $items);

        $items = array_values($items);
        return $items;
	}

    public function execute()
    {
        $items  = $this->getItemsForJson();
        $result = $this->resultJsonFactory->create();
        return $result->setData($items);
    }

    protected function setCurrentStoreIfStoreIdFilterExists($searchCriteria)
    {
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                if($filter->getField() === 'store_id')
                {
                    foreach($filter->getValue() as $store_id)
                    {
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
            if($filter->getField() === 'store_id')
            {
                foreach($filter->getValue() as $store_id)
                {
                    $store = $this->storeManager->getStore($store_id);
                    $collection->addStoreFilter($store);
                }
                continue;
            }

            if($filter->getField() === 'website_ids')
            {
                foreach($filter->getValue() as $website_id)
                {
                    $website = $this->storeManager->getWebsite($website_id);
                    $collection->addWebsiteFilter($website);
                }
                continue;
            }

            if($filter->getField() === 'visibility')
            {
                $this->needsVisibilityJoin = false;
                $collection->addFieldToFilter('visibility', ['in'=>$filter->getValue()]);
                continue;
            }

            $condition = $filter->getConditionType() ? $filter->getConditionType() : 'eq';
            $fields[] = ['attribute' => $filter->getField(), $condition => $filter->getValue()];
        }
        if(count($fields) > 1)
        {
            throw new \Exception("Can't handle multiple OR filters");
        }
        if ($fields) {
            $attribute = $fields[0]['attribute'];
            unset($fields[0]['attribute']);
            $filter = $fields[0];
            $collection->addFieldToFilter($attribute, $filter);
        }
    }

    protected function addStoreListingToItems(
        $items
    ){
        $stores = $this->storeManager->getStores();
        $store_id_lookup = array();
        foreach($stores as $store){
            $id = $store->getId();
            $store_id_lookup[$id] = $store;
        }

        $all_store_ids = array();
        $all_product_ids = array();
        foreach($items as &$item){
            $all_product_ids[] = $item['id'];
            foreach($item['store_ids'] as $store_id){
                $all_store_ids[$store_id] = $store_id;
            }
        }


        $store_listings = array();
        foreach($all_store_ids as $store_id){
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


    protected function getProductListingsForStore(
        $store,
        $productIds,
        $store_listings
    ){
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

        $base_currency = $store->getBaseCurrency()->getCode();
        $store_currency = $store->getDefaultCurrency()->getCode();

        foreach($items as $item){
            $id = $item['id'];

            $url = $this->storeUrlHelper->getStoreUrlByProductIdAndStoreId($id, $storeId);

            $tmp = array(
                'store_id' => $storeId,
                'title' => $item['name'],
                'url' => $url,
                'store_currency' => $store_currency,
                'visibility' => $item['visibility'],
                'status' => $item['status'],
                'image_url' => $item['image_url']
                );

            $tmp = $this->appendPricing($id, $tmp, $storeId, $base_currency, $store_currency);

            $store_listings[$id][$storeId] = $tmp;
        }

        return $store_listings;
    }

    protected function appendPricing($product_id, $item, $storeId = null, $base_currency = null, $store_currency = null)
    {
        $store_price = $this->getProductPrice(
            $product_id,
            $storeId,
            \Magento\Catalog\Pricing\Price\RegularPrice::PRICE_CODE,
            $base_currency,
            $store_currency
        );

        if ($store_price) {
            $item['price'] = $store_price;
        }

        $store_special_price = $this->getProductPrice(
            $product_id,
            $storeId,
            \Magento\Catalog\Pricing\Price\SpecialPrice::PRICE_CODE,
            $base_currency,
            $store_currency
        );

        if ($store_special_price) {
            $item['special_price'] = $store_special_price;
        }

        if($this->_request->getParam('final_price') === 'true') {
            $store_final_price = $this->getProductPrice(
                $product_id,
                $storeId,
                \Magento\Catalog\Pricing\Price\FinalPrice::PRICE_CODE,
                $base_currency,
                $store_currency
            );

            if ($store_final_price) {
                $item['final_price'] = $store_final_price;
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
        $websiteId = $this->storeManager->getWebsite()->getId();
        $stockItem = $this->stockRegistry->getStockItem($productId, $websiteId);

        if (isset($stockItem['is_in_stock'])) {
            $item['is_in_stock'] = $stockItem['is_in_stock'];
        }

        if (isset($stockItem['qty'])) {
            $item['qty'] = (float) $stockItem['qty'];
        }

        return $item;
    }

    protected function getProductPrice(
        $product_id,
        $storeId,
        $price_code,
        $base_currency = null,
        $store_currency = null
    ) {
        $product = $this->productRepository->getById($product_id, false, $storeId);

        $price = $product->getPriceInfo()->getPrice($price_code)->getValue();

        if ($store_currency && $base_currency) {
            try {
                $price = $this->directoryHelper->currencyConvert(
                    $price,
                    $base_currency,
                    $store_currency
                );
            } catch (\Exception $e) {
                // Allow the "undefined rate" exception and return the price as is if no rate has been setup.
            }
        }

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
        $this->childParentConfigurableProductIds = $this->getConfigurableProductParentChildIds($allProductIds);

        // fetch array of Bundle Product relationships, filtered by the items being processed
        $this->childParentBundleProductIds = $this->getBundleProductParentChildIds($allProductIds);

        // fetch array of Grouped Product relationships, filtered by the items being processed
        $this->childParentGroupedProductIds = $this->getGroupedProductParentChildIds($allProductIds);
    }

    /**
     * Bulk version of the native method to retrieve relationships one by one.
     * @see \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable::getParentIdsByChild
     *
     * @param array $childIds
     * @return array
     */
    protected function getConfigurableProductParentChildIds(array $childIds)
    {
        $childToParentIds = [];

        $connection = $this->resourceConnection->getConnection();

        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('catalog_product_super_link'),
                ['product_id', 'parent_id']
            )
            ->where(
                'product_id IN (?)',
                $childIds
            )
            // order by the oldest links first so the iterator will end with the most recent link
            ->order('link_id ASC');

        $result = $connection->fetchAll($select);
        foreach ($result as $_row) {
            $childToParentIds[$_row['product_id']] = $_row['parent_id'];
        }

        return $childToParentIds;
    }

    /**
     * Bulk version of the native method to retrieve relationships one by one.
     * @see \Magento\Bundle\Model\ResourceModel\Selection::getParentIdsByChild
     *
     * @param array $childIds
     * @return array
     */
    protected function getBundleProductParentChildIds(array $childIds)
    {
        $childToParentIds = [];

        $connection = $this->resourceConnection->getConnection();

        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('catalog_product_bundle_selection'),
                ['parent_product_id', 'product_id']
            )
            ->where(
                'product_id IN (?)',
                $childIds
            )
            // order by the oldest selections first so the iterator will end with the most recent link
            ->order('selection_id ASC');

        $result = $connection->fetchAll($select);
        foreach ($result as $_row) {
            $childToParentIds[$_row['product_id']] = $_row['parent_product_id'];
        }

        return $childToParentIds;
    }

    /**
     * Bulk version of the native method to retrieve relationships one by one.
     * @see \Magento\GroupedProduct\Model\ResourceModel\Product\Link::getParentIdsByChild
     *
     * @param array $childIds
     * @return array
     */
    protected function getGroupedProductParentChildIds(array $childIds)
    {
        $childToParentIds = [];

        $connection = $this->resourceConnection->getConnection();

        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('catalog_product_link'),
                ['product_id', 'linked_product_id']
            )
            ->where(
                'linked_product_id IN (?)',
                $childIds
            )
            ->where(
                'link_type_id = ?',
                \Magento\GroupedProduct\Model\ResourceModel\Product\Link::LINK_TYPE_GROUPED
            )
            // order by the oldest links first so the iterator will end with the most recent link
            ->order('link_id ASC');

        $result = $connection->fetchAll($select);
        foreach ($result as $_row) {
            $childToParentIds[$_row['linked_product_id']] = $_row['product_id'];
        }

        return $childToParentIds;
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
            return false;
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

        return false;
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
        }
        else {
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
