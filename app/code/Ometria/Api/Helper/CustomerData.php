<?php
namespace Ometria\Api\Helper;
class CustomerData
{
    protected $genderOptions;
	protected $customerMetadataInterface;
	
	public function __construct(
		\Magento\Customer\Api\CustomerMetadataInterface $customerMetadataInterface
	) {
		$this->customerMetadataInterface    = $customerMetadataInterface;
		
		$this->genderOptions                = $this->customerMetadataInterface
            ->getAttributeMetadata('gender')
            ->getOptions();	
    }
    		
	public function getGenderLabel($item)
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
	
	public function getCountryId($item)
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
}