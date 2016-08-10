<?php
namespace Ometria\Api\Controller\V1;
use Ometria\Api\Helper\Format\V1\Subscribers as Helper;

use \Ometria\Api\Controller\V1\Base;
class Subscribers extends Base
{
    protected $resultJsonFactory;
    protected $subscriberFactory;
    protected $helperOmetriaApiFilter;
    protected $searchCriteria;
    protected $customerCollection;
    protected $customerDataHelper;
    
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Magento\Newsletter\Model\SubscriberFactory $subscriberFactory,
		\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria,
		\Ometria\Api\Helper\Filter\V1\Service $helperOmetriaApiFilter,
		\Magento\Customer\Model\ResourceModel\Customer\Collection $customerCollection,
	    \Ometria\Api\Helper\CustomerData $customerDataHelper			
	) {
		parent::__construct($context);
		$this->customerCollection       = $customerCollection;		
		$this->resultJsonFactory        = $resultJsonFactory;
		$this->subscriberFactory        = $subscriberFactory;
		$this->helperOmetriaApiFilter   = $helperOmetriaApiFilter;
		$this->searchCriteria           = $searchCriteria;
		$this->customerDataHelper       = $customerDataHelper;
	}
	
	protected function getMarketingOption($status)
	{
	    switch($status)
	    {
	        case \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED:
	            return true;
	        case \Magento\Newsletter\Model\Subscriber::STATUS_UNSUBSCRIBED:
	            return false;	  
	        default;
	            return null;          
	    }
	}
	
	protected function addCustomerDataAndFormat($items)
	{
	    $customer_ids = array_map(function($item){
	        return array_key_exists('customer_id', $item) ? $item['customer_id'] : 0;
	    }, $items);
	    
	    $customer_ids = array_filter($customer_ids, function($item){
	        return $item > 0;
	    });
        
        $this->customerCollection->addFieldToFilter('entity_id', $customer_ids);
        
        $new_items = [];    
        foreach($items as $key=>$item)
        {            
            $new         = Helper::getBlankArray();
            $new["@type"]             = "contact";
            $new["id"]                = array_key_exists('subscriber_id', $item) ? $item['subscriber_id'] : '';
            $new["email"]             = array_key_exists('subscriber_email', $item) ? $item['subscriber_email'] : '';
            $status                   = array_key_existS('subscriber_status', $item) ? $item['subscriber_status'] : '';            
            $new["marketing_optin"]   = $this->getMarketingOption($status);            
            $new['store_id']          = $item['store_id'];                        
            $new_items[] = $new;
        }
        return $new_items;
	}
	
    public function execute()
    {
        $searchCriteria = $this->helperOmetriaApiFilter
            ->applyFilertsToSearchCriteria($this->searchCriteria);

        $collection = $this->subscriberFactory->create()->getCollection();

        foreach ($searchCriteria->getFilterGroups() as $group) {
            $this->addFilterGroupToCollection($group, $collection);
        }
               
        if($page_size = $searchCriteria->getPageSize())
        {   
            $collection->setPageSize($page_size);
        }                       

        if($current_page = $searchCriteria->getCurrentPage())
        {   
            $collection->setCurPage($current_page);
        }
        
        $items      = array_map(function($item){
            return $item->getData();
        }, $collection->getItems());
        sort($items);
        
        $items      = $this->addCustomerDataAndFormat($items);
        
		$result = $this->resultJsonFactory->create();
		return $result->setData($items);
    }  
    
    protected function addFilterGroupToCollection(
        \Magento\Framework\Api\Search\FilterGroup $filterGroup,
        \Magento\Newsletter\Model\ResourceModel\Subscriber\Collection $collection
    ) {
        $fields = [];
        foreach ($filterGroup->getFilters() as $filter) {
            $condition = $filter->getConditionType() ? $filter->getConditionType() : 'eq';
            $fields[] = ['attribute' => $filter->getField(), $condition => $filter->getValue()];
        }
        
        if(count($fields) > 1)
        {
            throw new \Exception("Can't handle multiple OR filters");
        }
        if ($fields) {
            $attribute = $this->swapAttributes($fields[0]['attribute']);
            unset($fields[0]['attribute']);
            $filter = $fields[0];
            $collection->addFieldToFilter($attribute, $filter);
        }        
    }    
    
    protected function swapAttributes($attribute)
    {
        switch($attribute)
        {
            case 'updated_at';
                return 'change_status_at';
            default:
                return $attribute;
        }
    }  
}