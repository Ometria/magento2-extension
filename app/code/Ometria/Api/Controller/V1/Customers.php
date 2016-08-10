<?php
namespace Ometria\Api\Controller\V1;
use Ometria\Api\Helper\Format\V1\Customers as Helper;

use \Ometria\Api\Controller\V1\Base;
class Customers extends Base
{
    protected $resultJsonFactory;
    protected $repository;
    protected $customerMetadataInterface;
    protected $genderOptions;
    protected $subscriberCollection;
    protected $customerIdsOfNewsLetterSubscribers=[];
    protected $customerDataHelper;
    
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Ometria\Api\Helper\Service\Filterable\Service $apiHelperServiceFilterable,
		\Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
		\Magento\Customer\Api\CustomerMetadataInterface $customerMetadataInterface,
		\Magento\Newsletter\Model\ResourceModel\Subscriber\Collection $subscriberCollection,
        \Ometria\Api\Helper\CustomerData $customerDataHelper
	) {
		parent::__construct($context);
		$this->resultJsonFactory            = $resultJsonFactory;
		$this->apiHelperServiceFilterable   = $apiHelperServiceFilterable;
		$this->repository                   = $customerRepository;
		$this->subscriberCollection         = $subscriberCollection;
		$this->customerMetadataInterface    = $customerMetadataInterface;
		$this->customerDataHelper           = $customerDataHelper;
		
		$this->genderOptions                = $this->customerMetadataInterface
            ->getAttributeMetadata('gender')
            ->getOptions();
	}
	
	public function getMarketingOption($item, $subscriber_collection)
	{	        
	    if(!array_key_exists('id', $item))
	    {
	        return false;
	    }
	    if(!$this->customerIdsOfNewsLetterSubscribers)
	    {
	        foreach($subscriber_collection as $subscriber)
	        {
	            $this->customerIdsOfNewsLetterSubscribers[] = 
	                $subscriber->getCustomerId();
	        }
	    }	

	    return in_array($item['id'], $this->customerIdsOfNewsLetterSubscribers);	    
	}

	public function getSubscriberCollectionFromCustomerIds($customer_ids)
	{
	    return $this->subscriberCollection
	        ->addFieldToFilter('customer_id', ['in'=>$customer_ids])
	        ->addFieldToFilter('subscriber_status',
	            \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED);	        	
	}
	
	public function getSubscriberCollection($items)
	{
	    $customer_ids = array_map(function($item){
	        return $item['id'];
	    },$items);
	    
	    return $this->getSubscriberCollectionFromCustomerIds($customer_ids);
	}
			
    public function execute()
    {
        $items = $this->apiHelperServiceFilterable->createResponse(
            $this->repository,             
            '\Magento\Customer\Api\Data\CustomerInterface'
        );
                
        $subscriber_collection = $this->getSubscriberCollection($items);
        
        $items = array_map(function($item) use ($subscriber_collection) {

            $new = Helper::getBlankArray();
            
            $new["@type"]             = "contact";
            $new["id"]                = array_key_exists('id', $item) ? $item['id'] : '';
            $new["email"]             = array_key_exists('email', $item) ? $item['email'] : '';
            $new["prefix"]            = array_key_exists('prefix', $item) ? $item['prefix'] : '';
            $new["firstname"]         = array_key_exists('firstname', $item) ? $item['firstname'] : '';
            $new["middlename"]        = array_key_exists('middlename', $item) ? $item['middlename'] : '';
            $new["lastname"]          = array_key_exists('lastname', $item) ? $item['lastname'] : '';
            $new["gender"]            = $this->customerDataHelper->getGenderLabel($item);
            $new["date_of_birth"]     = array_key_exists('dob', $item) ? $item['dob'] : '';
            $new["marketing_optin"]   = $this->getMarketingOption($item, $subscriber_collection);
            $new["country_id"]        = $this->customerDataHelper->getCountryId($item);    
            $new["store_id"]          = $item['store_id'];
            return $new;
        }, $items);
        
		$result = $this->resultJsonFactory->create();
		return $result->setData($items);
    }    
}