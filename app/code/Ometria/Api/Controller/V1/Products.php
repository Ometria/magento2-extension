<?php
namespace Ometria\Api\Controller\V1;
use Ometria\Api\Helper\Format\V1\Products as Helper;
use \Ometria\Api\Controller\V1\Base;
class Products extends Base
{
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
    protected $catalogProductMediaConfig;
    protected $resourceConnection;
    protected $request;
    protected $directoryHelper;
    protected $storeUrlHelper;

    protected $storeIdCache=false;

    /**
    * Prevent twice joining visibility if its added as filter
    */
    protected $needsVisibilityJoin;

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
        \Magento\Catalog\Model\Product\Media\Config $catalogProductMediaConfig,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Directory\Helper\Data $directoryHelper,
        \Ometria\Api\Helper\StoreUrl $storeUrlHelper

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
		$this->catalogProductMediaConfig  = $catalogProductMediaConfig;
		$this->resourceConnection         = $resourceConnection;
		$this->directoryHelper            = $directoryHelper;
		$this->storeUrlHelper             = $storeUrlHelper;
	}

	protected function getArrayKey($array, $key)
	{
	    return array_key_exists($key, $array) ? $array[$key] : null;
	}

	protected function getCustomAttribute($array, $key)
	{
	    $attributes = $this->getArrayKey($array, 'custom_attributes');
	    if(!$attributes) { return; }

	    $item       = array_filter($attributes, function($item) use ($key){
	        return $item['attribute_code'] === $key;
	    });
	    $item = array_shift($item);

	    if($item)
	    {
	        return $item['value'];
	    }
	    return null;
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

	protected function getBaseImageUrl($store_id=false)
	{
	    $store = $this->storeManager->getStore();
	    if($store_id)
	    {
    	    $store = $this->storeManager->getStore($store_id);
	    }
        $baseUrl = 	$store->getBaseUrl(
                \Magento\Framework\UrlInterface::URL_TYPE_MEDIA) .
        $this->catalogProductMediaConfig->getBaseMediaPath();
        return $baseUrl;
	}

	protected function serializeItem($item)
	{
        $tmp = Helper::getBlankArray();

        $tmp['id']          = strval($this->getArrayKey($item, 'id'));
        $tmp['title']       = $this->getArrayKey($item, 'name');
        $tmp['sku']         = $this->getArrayKey($item, 'sku');
        $tmp['url']         = $this->getArrayKey($item, 'url');
        $tmp['image_url']   = $this->getBaseImageUrl() . $this->getCustomAttribute($item,$this->getImageUrlKey());
        $tmp['attributes']  = [];
        $tmp['is_active']   = $this->getArrayKey($item, 'status') !== \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED;
        $tmp['stores']      = $this->getArrayKey($item, 'store_ids');

        if($this->_request->getParam('raw') === 'true') {
            $tmp['_raw'] = $item;
        }

        $tmp = $this->appendPricing($tmp['id'], $tmp);

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

        $items      = $this->apiHelperServiceFilterable->processList($collection, 'Magento\Catalog\Api\Data\ProductInterface');

        if($this->_request->getParam('listing') === 'true')
        {
            try {
                $items = $this->addStoreListingToItems($items, $this->resourceConnection);
            } catch (\Exception $e){
                // pass
            }
        }
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
        foreach($items  as $item){
            $id = $item['id'];
            $item['store_listings'] = isset($store_listings[$id]) ? array_values($store_listings[$id]) : array();
            $ret[] = $item;
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

        $items      = $this->apiHelperServiceFilterable->processList($collection, 'Magento\Catalog\Api\Data\ProductInterface');

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
                'image_url' => $this->getBaseImageUrl() . $this->getCustomAttribute($item,$this->getImageUrlKey())
                );

            $tmp = $this->appendPricing($id, $tmp, $store_currency, $base_currency);

            $store_listings[$id][$storeId] = $tmp;
        }

        return $store_listings;
    }

    protected function appendPricing($product_id, $item, $store_currency=null, $base_currency=null){

        $store_price = $this->getProductPrice(
            $product_id,
            $item,
            \Magento\Catalog\Pricing\Price\RegularPrice::PRICE_CODE,
            $store_currency,
            $base_currency);

        $store_special_price = $this->getProductPrice(
            $product_id,
            $item,
            \Magento\Catalog\Pricing\Price\SpecialPrice::PRICE_CODE,
            $store_currency,
            $base_currency);

        $item['price'] = $store_price;

        if($this->_request->getParam('final_price') === 'true') {
            $store_final_price = $this->getProductPrice(
                $product_id,
                $item,
                \Magento\Catalog\Pricing\Price\FinalPrice::PRICE_CODE,
                $store_currency,
                $base_currency);

            $item['final_price'] = $store_final_price;
        }

        if ($store_special_price) {
            $item['special_price'] = $store_special_price;
            //$item['special_price_dt_from'] = null;
            //$item['special_price_dt_to'] = null;
        }

        return $item;
    }


    protected function getProductPrice(
        $product_id,
        $item,
        $price_code,
        $store_currency=null,
        $base_currency=null
    ){
        $product = $this->productRepository->getById($product_id);
        $price   = $product->getPriceInfo()->getPrice($price_code)->getValue();

        if ($store_currency && $base_currency){
            $price = $this->directoryHelper->currencyConvert(
                $price,
                $base_currency,
                $store_currency
                );
        }

        return $price;
    }
}