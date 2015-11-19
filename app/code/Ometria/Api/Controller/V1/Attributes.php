<?php
namespace Ometria\Api\Controller\V1;
use Ometria\Api\Helper\Format\V1\Attributes as Helper;
class Attributes extends \Magento\Framework\App\Action\Action
{
    protected $resultJsonFactory;
    protected $attributes;
    
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Magento\Catalog\Model\Resource\Product\Attribute\Collection $attributes
		
	) {
		parent::__construct($context);
		$this->resultJsonFactory = $resultJsonFactory;
		$this->attributes = $attributes;
	}
		

    protected function extractAttributeCodeFromUrl()
    {
        $params = $this->getRequest()->getParams();
        $params = array_keys($params);
        if(count($params) > 1)
        {
            throw new \Exception("Invalid Request");
        }
        $value = array_shift($params);
        return $value;
    }
    public function execute()
    {    
        $attribute_code = $this->extractAttributeCodeFromUrl();
        $attribute = $this->attributes->addFieldToFilter('attribute_code', $attribute_code)
        ->getFirstItem();
        var_dump(get_class($attribute));
        exit;
		$result = $this->resultJsonFactory->create();
		return $result->setData(['success' => true]);        
    }    
}