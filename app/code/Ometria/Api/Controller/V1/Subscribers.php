<?php
namespace Ometria\Api\Controller\V1;
use Ometria\Api\Helper\Format\V1\Subscribers as Helper;

class Subscribers extends \Magento\Framework\App\Action\Action
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
		\Magento\Customer\Model\Resource\Customer\Collection $customerCollection,
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
            $customer = $this->customerCollection->getItemById($item['customer_id']);
            $customer_data = $customer ? $customer->getData() : [];
            
            $new         = Helper::getBlankArray();
            $new["@type"]             = "contact";
            $new["id"]                = array_key_exists('id', $customer_data) ? $customer_data['id'] : '';
            $new["email"]             = array_key_exists('subscriber_email', $item) ? $item['subscriber_email'] : '';
            $new["prefix"]            = array_key_exists('prefix', $customer_data) ? $customer_data['prefix'] : '';
            $new["firstname"]         = array_key_exists('firstname', $customer_data) ? $customer_data['firstname'] : '';
            $new["middlename"]        = array_key_exists('middlename', $customer_data) ? $customer_data['middlename'] : '';
            $new["lastname"]          = array_key_exists('lastname', $customer_data) ? $customer_data['lastname'] : '';
            $new["gender"]            = $this->customerDataHelper->getGenderLabel($customer_data);
            $new["date_of_birth"]     = array_key_exists('dob', $customer_data) ? $customer_data['dob'] : '';
            $new["marketing_optin"]   = true;
            $new["country_id"]        = $this->customerDataHelper->getCountryId($customer_data);  
                        
            $new_items[] = $new;
        }
        return $new_items;
	}
	
    public function execute()
    {
        $searchCriteria = $this->helperOmetriaApiFilter
            ->applyFilertsToSearchCriteria($this->searchCriteria);

        $collection = $this->subscriberFactory->create()->getCollection();
        $collection->addFieldToFilter('subscriber_status',
	            \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED);
        
        foreach ($searchCriteria->getFilterGroups() as $group) {
            $this->addFilterGroupToCollection($group, $collection);
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
        \Magento\Newsletter\Model\Resource\Subscriber\Collection $collection
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
            $attribute = $fields[0]['attribute'];
            unset($fields[0]['attribute']);
            $filter = $fields[0];
            $collection->addFieldToFilter($attribute, $filter);
        }        
    }      
}