<?php
namespace Ometria\Api\Controller\V1;
use Ometria\Api\Helper\Format\V1\Products as Helper;
class Products extends \Magento\Framework\App\Action\Action
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

        $tmp['id']          = $this->getArrayKey($item, 'id');
        $tmp['title']       = $this->getArrayKey($item, 'name');
        $tmp['sku']         = $this->getArrayKey($item, 'sku');
        $tmp['price']       = $this->getArrayKey($item, 'price');
        $tmp['url']         = $this->getArrayKey($item, 'url');              
        $tmp['image_url']   = $this->getBaseImageUrl() . $this->getCustomAttribute($item,$this->getImageUrlKey());
        $tmp['attributes']  = [];
        $tmp['is_active']   = $this->getArrayKey($item, 'status') !== \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED;
        $tmp['stores']      = [$this->getArrayKey($item, 'store_ids')];	
        
        if($item['type_id'] === 'configurable' && $item['price'] == 0)
        {   
            $product = $this->productRepository->getById($item['id']);            
            $price   = $product->getPriceInfo()->getPrice(
                \Magento\Catalog\Pricing\Price\FinalPrice::PRICE_CODE
                )->getValue();
            $tmp['price'] = $price;
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
        
        $collection->joinAttribute('status', 'catalog_product/status', 'entity_id', null, 'inner');
        
        if($this->needsVisibilityJoin)
        {
            $collection->joinAttribute('visibility', 'catalog_product/visibility', 'entity_id', null, 'inner');
        }
        
        $items      = $this->apiHelperServiceFilterable->processList($collection, 'Magento\Catalog\Api\Data\ProductInterface');
                     
        $items      = array_map(function($item){
            return $this->serializeItem($item);                
        }, $items);
        
        $items = array_values($items);	
        return $items;
	}
	
    public function execute()
    {          
        $items  = $this->getItemsForJson();        
        if($this->_request->getParam('listing') === 'true')
        {
            $items       = $this->addStoreListingToItems($items,        
                $this->resourceConnection);             
        }                
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
    
    
    protected function getIdsAndCastAsIntFromItems($items)
    {
        $product_ids = array_map(function($item){
            return (int) $item['id'];
        }, $items);
        $ids = implode(',', $product_ids);
        return $ids;    
    }
    
    protected function queryForStoreListingData($resourceConnection, $items)
    {
        //can't bind IN with PDO -- fetching IDs, casting as 
        //ints, and concating with "," on our own.  Int cast
        //performs safe SQL escaping
        $ids            = $this->getIdsAndCastAsIntFromItems($items);                        
        $db  = $resourceConnection->getConnection();        
        $sql = '
            SELECT eav_attribute.attribute_code, main_table.value_id, main_table.attribute_id, main_table.store_id, main_table.entity_id, value 
            FROM catalog_product_entity_datetime main_table  
            LEFT JOIN eav_attribute ON eav_attribute.attribute_id = main_table.attribute_id
            WHERE entity_id IN ('.$ids.')        
            UNION
        
            SELECT eav_attribute.attribute_code, main_table.value_id, main_table.attribute_id, main_table.store_id, main_table.entity_id, value 
            FROM catalog_product_entity_decimal main_table  
            LEFT JOIN eav_attribute ON eav_attribute.attribute_id = main_table.attribute_id
            WHERE entity_id IN ('.$ids.')        
            UNION
        
            SELECT eav_attribute.attribute_code, main_table.value_id, main_table.attribute_id, main_table.store_id, main_table.entity_id, value 
            FROM catalog_product_entity_int main_table  
            LEFT JOIN eav_attribute ON eav_attribute.attribute_id = main_table.attribute_id
            WHERE entity_id IN ('.$ids.')            
            UNION
        
            SELECT eav_attribute.attribute_code, main_table.value_id, main_table.attribute_id, main_table.store_id, main_table.entity_id, value 
            FROM catalog_product_entity_text main_table  
            LEFT JOIN eav_attribute ON eav_attribute.attribute_id = main_table.attribute_id
            WHERE entity_id IN ('.$ids.')             
            UNION
        
            SELECT eav_attribute.attribute_code, main_table.value_id, main_table.attribute_id, main_table.store_id, main_table.entity_id, value 
            FROM catalog_product_entity_varchar main_table  
            LEFT JOIN eav_attribute ON eav_attribute.attribute_id = main_table.attribute_id
            WHERE entity_id IN ('.$ids.')            
        ';

        $result = $db->query($sql);
        return $result;    
    }
    
    protected function getStoreListingsFromResults($results)
    {
        $store_listings = [];
        while($row = $results->fetch())
        {
            if(!isset($store_listings[$row['entity_id']])){ 
                $store_listings[$row['entity_id']] = [];
            }
            if(!isset($store_listings[$row['entity_id']][$row['store_id']]))
            {
                $store_listings[$row['entity_id']][$row['store_id']] = [];
            }
            $store_listings[$row['entity_id']][$row['store_id']][
                $row['attribute_code']
            ] = $row['value'];
        }
        return $store_listings;    
    }
    
    protected function addStoreListingToItems($items, $resourceConnection)
    {
        $result         = $this->queryForStoreListingData($resourceConnection, $items);
        $store_listings = $this->getStoreListingsFromResults($result);
        
        foreach($items as $key=>$item)
        {
            $product_id = $item['id'];
            if(!isset($store_listings[$product_id])) { continue; }
            $items[$key]['store_listing'] = 
                $this->normalizeStoreListing($store_listings[$product_id], $product_id, $item['stores'][0]);                
        }     
        
        return $items;    
    }
    
    protected function getStores()
    {
        return $this->storeManager->getStores();
    }
    
    protected function getStoreIds()
    {
        if(!$this->storeIdCache)
        {
            $stores = $this->getStores();
            $this->storeIdCache = array_map(function($store){
                return $store->getId();
            }, $stores);
        }
    
        return $this->storeIdCache;
    }
    
    protected function getStoreListingKeysToKeep()
    {
        return ['name','price','status','visibility','store_id','url',
            'image_url','thumbnail_url','small_image_url','store_currency',
            'store_price'];         
    }
    
    protected function addImageUrlsToListing($listing, $url_base)
    {        
        foreach(['thumbnail', 'image', 'small_image'] as $image_type)
        {
            if(!isset($listing[$image_type]) || !$listing[$image_type])
            {
                $listing[$image_type . '_url'] = null;
                continue;
            }
            $listing[$image_type . '_url'] = $url_base . $listing['thumbnail'];
        }    
        return $listing;
    }
    
    protected function addStorePriceInformationToListing($listing, $store)
    {
        if(isset($listing['price']))
        {
            $listing['store_price'] = $this->directoryHelper->currencyConvert(
                $listing['price'], 
                $store->getBaseCurrency()->getCode(),
                $store->getCurrentCurrency()->getCode()
            );
        }
        return $listing;    
    }

    protected function addSpecialPriceInformationToListing($listing, $store)
    {
        if(isset($listing['special_price']))
        {
            $listing['store_special_price'] = $this->directoryHelper->currencyConvert(
                $listing['special_price'], 
                $store->getBaseCurrency()->getCode(),
                $store->getCurrentCurrency()->getCode()
            );
        }   
        return $listing;    
    }
        
    protected function addCurrencyAndPriceInformationToListing($listing, $store)
    {
        $listing['store_currency']  = $store->getCurrentCurrency()->getCode();                    
        $listing                    = $this->addStorePriceInformationToListing($listing, $store);    
        $listing                    = $this->addSpecialPriceInformationToListing($listing, $store);
        return $listing;         
    }
    
    protected function removeUnneededKeysFromListing($listing)
    {
        $to_keep = $this->getStoreListingKeysToKeep();    
        foreach($listing as $key=>$value)
        {
            if(in_array($key, $to_keep)){ continue; }
            unset($listing[$key]);
        }    
        return $listing;
    }
    
    protected function initilizeListingForStore($store_listing, $store)
    {
        $store_id        = $store->getId();
        $listing             = $store_listing[0];
        if(array_key_exists($store_id, $store_listing))
        {
            $listing = $store_listing[$store_id];
        }            
        $listing['store_id']        = $store->getId();                            
        return $listing;
    }
    
    protected function generateProductUrlForListing($listing, $product_id, $store)
    {
        $listing['url'] = $this->storeUrlHelper
            ->getStoreUrlByProductIdAndStoreId($product_id, $store->getId());    
        return $listing;
    }
    
    protected function normalizeStoreListing($store_listing, $product_id, $real_store_ids)
    {           
        foreach($this->getStores() as $store)
        {    
            if(!in_array($store->getId(), $real_store_ids))
            {
                continue;
            }      
            $listing = $this->initilizeListingForStore($store_listing, $store);            
            $listing = $this->addImageUrlsToListing($listing, $this->getBaseImageUrl($listing['store_id']));            
            $listing = $this->addCurrencyAndPriceInformationToListing($listing, $store);
            $listing = $this->generateProductUrlForListing($listing, $product_id, $store);
            $listing = $this->removeUnneededKeysFromListing($listing);     
            $listings[]  = $listing;
        }
        return $listings;
    }    
    
}