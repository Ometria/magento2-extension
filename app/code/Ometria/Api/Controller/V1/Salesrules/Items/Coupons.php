<?php
namespace Ometria\Api\Controller\V1\Salesrules\Items;
// use Ometria\Api\Helper\Format\V1\Products as Helper;
use \Ometria\Api\Controller\V1\Base;
class Coupons extends Base
{
    protected $resultJsonFactory;
    protected $couponRepository;
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\SalesRule\Model\CouponRepository $couponRepository,		
        \Ometria\Api\Helper\Service\Filterable\Service $apiHelperServiceFilterable,				
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory)
	{
	    $this->couponRepository           = $couponRepository;
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
            $this->couponRepository, 
            null//'Magento\SalesRule\Api\Data\CouponInterface'            
        );
        return $items;
    }}