<?php
namespace Ometria\Api\Plugin\Magento\Checkout\Controller\Cart;
class CouponPost
{
    protected $couponFactory;
    protected $request;
    protected $dateManager;
    
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\SalesRule\Model\CouponFactory $couponFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateManager
        
    )
    {
        $this->dateManager   = $dateManager;
        $this->request       = $request;
        $this->couponFactory = $couponFactory;
    }
            
    public function beforeExecute($subject)
    {      
        if($this->request->getParam('remove') !== '0' && $this->request->getParam('remove') !== null)
        {
            return;
        }
        
        $coupon = $this->couponFactory->create()->loadByCode($this->request->getParam('coupon_code'));
        if(!$coupon->getData('expiration_date'))
        {
            return;
        }
        
        $expiration_date = $coupon->getData('expiration_date');
                
        if(strToTime($this->dateManager->date()) < strToTime($expiration_date))
        {
            return;
        }

        //we've got an expired coupon here
    }
}
