<?php
namespace Ometria\Api\Controller\V1;
use Ometria\Api\Helper\Format\V1\Attributes as Helper;
use \Ometria\Api\Controller\V1\Base;
class Attributes extends Base
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
		

    protected function extractAttributeCodeFromUrl()
    {
        $params = $this->getRequest()->getParams();
        $params = array_keys($params);
        $value  = array_shift($params);
        return $value;
    }
    
    protected function serializeAttribute($attribute)
    {
        $options        = $attribute->getOptions();
        $options        = array_map(function($item){
            return $item->getData();
        }, $options);

        $options        = array_filter($options, function($item){
            return $item['value'];
        });
        
        $type   = $attribute->getAttributeCode();
        foreach($options as $key=>$option)
        {
            $option['@type']    = 'attribute';
            $option['type']     = $type;
            $options[$key]      = $option;
            
            $options[$key]['id']      = $options[$key]['value'];
            $options[$key]['title']   = $options[$key]['label'];
            
            unset($options[$key]['value']);
            unset($options[$key]['label']);
//             unset($options['value']);
//             unset($options['title']);
        }
        
        sort($options);
        return $options;
//         $data            = Helper::getBlankArray();
//         $data['type']    = $attribute->getAttributeCode();
//         $data['id']      = $attribute->getId();
//         $data['title']   = $attribute->getFrontend()->getLabel();
//         $data['options'] = $options;
        
		return $data;    
    }
    
    public function execute()
    {    
        $attribute_code  = $this->extractAttributeCodeFromUrl();
        $attribute       = $this->attributes->addFieldToFilter('attribute_code', $attribute_code)
        ->getFirstItem();
        
        $data            = $this->serializeAttribute($attribute);
        $result = $this->resultJsonFactory->create();
		return $result->setData($data);        
    }    
}