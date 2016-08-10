<?php
namespace Ometria\Api\Controller\V1\Salesrules\Insert;
use \Ometria\Api\Controller\V1\Base;
// use Ometria\Api\Helper\Format\V1\Products as Helper;
class Coupons extends Base
{
    protected $context;
    protected $resultJsonFactory;
    protected $couponRepository;
    protected $couponFactory;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Magento\SalesRule\Model\CouponRepository $couponRepository,
		\Magento\SalesRule\Model\CouponFactory $couponFactory
		)
	{	    	
        $this->resultJsonFactory = $resultJsonFactory;
		$this->couponRepository  = $couponRepository;
		$this->couponFactory     = $couponFactory;
			
	    return parent::__construct($context);
	}
	
	protected function getRuleId()
	{
	    return $this->getRequest()->getParam('rule_id');;
	}
	
	protected function getCouponCodes()
	{
	    return $this->getRequest()->getParam('codes');
	}
	
	protected function getUsageLimit()
	{
	    return $this->getRequest()->getParam('usage_limit');
	}
	
	protected function getUsageLimitPerCustomer()
	{
	    return $this->getRequest()->getParam('usage_limit_per_customer');
	}
	
	protected function getExpirationDate()
	{
	    $expire_ts = strToTime($this->getRequest()->getParam('expiration_date'));
        $expiration_date = date('Y-m-d H:i:s', $expire_ts);
	    return $expiration_date;
	}
	
    public function execute()
    {
        $coupons = [];
        
        $usage_limit        = $this->getUsageLimit();
        $usage_per_customer = $this->getUsageLimitPerCustomer();
        $expiration_date    = $this->getExpirationDate();
        
        foreach($this->getCouponCodes() as $code)
        {
            $coupon = $this->couponFactory->create();        
            $coupon->setRuleId($this->getRuleId())            
            ->setCode($code)
            ->setUsageLimit($usage_limit)
            ->setUsagePerCustomer($usage_per_customer)
            ->setExpirationDate($expiration_date)            
            ->setCreatedAt(time())
            ->setType($coupon::TYPE_GENERATED);        
                                      
            //use repository to get validation                                      
            $this->couponRepository->save($coupon);        
            
            //cheat and set our own expiration date
            $coupon->setExpirationDate($expiration_date)->save();            
            $coupons[] = $coupon->getData();
        }
        $result = $this->resultJsonFactory->create();
        return $result->setData($coupons);        
    }
}