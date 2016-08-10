<?php
namespace Ometria\Api\Controller\V1\Attribute;
use Ometria\Api\Helper\Format\V1\Attribute\Types as Helper;
use \Ometria\Api\Controller\V1\Base;
class Types extends Base
{
    protected $resultJsonFactory;
    protected $attributes;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection $attributes		
	) {
		parent::__construct($context);
		$this->resultJsonFactory = $resultJsonFactory;
		$this->attributes = $attributes;		
	}

    protected function serializeAttribute($attribute)
    {
        $item = Helper::getBlankArray();
        $item['id'] = $attribute->getId();
        $item['title'] = $attribute->getFrontendLabel();
        $item['attribute_code'] = $attribute->getAttributeCode();
        switch($attribute->getData('frontend_input'))
        {
            case 'multiselect':
                $item['attribute_type'] = 'OPTION_LIST';
                break;
            case 'select':
                $item['attribute_type'] = 'OPTION';
                break; 
            case 'boolean';               
                $item['attribute_type'] = 'OPTION';
                break;             
            default:
                $item['attribute_type'] = 'FREETEXT';
        }

        return $item;   
    }		
    	
    public function execute()
    {
        $data = [];
        // $tmp  = [];
        foreach($this->attributes as $attribute)
        {
            // $tmp[$attribute->getFrontendInput()] = 0;
            $data[] = $this->serializeAttribute($attribute);
        }

		$result = $this->resultJsonFactory->create();		
		return $result->setData($data);
    
    }    
}