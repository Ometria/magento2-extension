<?php
namespace Ometria\Api\Controller\V1\Salesrules;
// use Ometria\Api\Helper\Format\V1\Products as Helper;
use \Ometria\Api\Controller\V1\Base;
class Items extends Base
{
    protected $resultJsonFactory;
    protected $ruleRepository;
    protected $apiHelperServiceFilterable;
    
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Ometria\Api\Helper\Service\Filterable\Service $apiHelperServiceFilterable,		
		\Magento\SalesRule\Model\RuleRepository $ruleRepository
		)
	{
	    $this->ruleRepository             = $ruleRepository;
	    $this->resultJsonFactory          = $resultJsonFactory;
	    $this->apiHelperServiceFilterable = $apiHelperServiceFilterable;
	    
	    return parent::__construct($context);
	}
	
    public function execute()
    {          
        $items  = $this->getItemsForJson();        
        $result = $this->resultJsonFactory->create();
        return $result->setData($items);
    }  
    
    public function getItemsForJson()
    {
        $items = $this->apiHelperServiceFilterable->createResponse(
            $this->ruleRepository, 
            'Magento\SalesRule\Api\Data\RuleInterface'
        );
        return $items;    
        // return ['hello'=>'goodbye'];
    }
}