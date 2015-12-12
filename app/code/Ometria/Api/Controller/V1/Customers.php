<?php
namespace Ometria\Api\Controller\V1;
use Ometria\Api\Helper\Format\V1\Customers as Helper;

class Customers extends \Magento\Framework\App\Action\Action
{
    protected $resultJsonFactory;
    protected $repository;
    protected $customerMetadataInterface;
    protected $genderOptions;
    protected $subscriberCollection;
    protected $customerIdsOfNewsLetterSubscribers=[];
    
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Ometria\Api\Helper\Service\Filterable\Service $apiHelperServiceFilterable,
		\Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
		\Magento\Customer\Api\CustomerMetadataInterface $customerMetadataInterface,
		\Magento\Newsletter\Model\Resource\Subscriber\Collection $subscriberCollection
	) {
		parent::__construct($context);
		$this->resultJsonFactory            = $resultJsonFactory;
		$this->apiHelperServiceFilterable   = $apiHelperServiceFilterable;
		$this->repository                   = $customerRepository;
		$this->subscriberCollection         = $subscriberCollection;
		$this->customerMetadataInterface    = $customerMetadataInterface;
		
		$this->genderOptions                = $this->customerMetadataInterface
            ->getAttributeMetadata('gender')
            ->getOptions();
	}
	
	protected function getGenderLabel($item)
	{
	    $value = array_key_exists('gender', $item) ? $item['gender'] : false;
        foreach($this->genderOptions as $option)
        {
            if($option->getValue() == $value)
            {
                return $option->getLabel();
            }
        }
        
        return '';
	}
	
	protected function getMarketingOption($item, $subscriber_collection)
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
	
	protected function getCountryId($item)
	{
	    $addresses = array_key_exists('addresses', $item) ? $item['addresses'] : [];
	    foreach($addresses as $address)
	    {
	        if(array_key_exists('country_id', $address))
	        {
	            return $address['country_id'];
	        }	
	    }
	    return false;
	}
	
	protected function getSubscriberCollectionFromCustomerIds($customer_ids)
	{
	    return $this->subscriberCollection
	        ->addFieldToFilter('customer_id', ['in'=>$customer_ids])
	        ->addFieldToFilter('subscriber_status',
	            \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED);	        	
	}
	
	protected function getSubscriberCollection($items)
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
            $new["gender"]            = $this->getGenderLabel($item);
            $new["date_of_birth"]     = array_key_exists('dob', $item) ? $item['dob'] : '';
            $new["marketing_optin"]   = $this->getMarketingOption($item, $subscriber_collection);
            $new["country_id"]        = $this->getCountryId($item);    
            return $new;
        }, $items);
        
		$result = $this->resultJsonFactory->create();
		return $result->setData($items);
    }    
}