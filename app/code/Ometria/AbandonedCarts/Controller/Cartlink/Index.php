<?php
namespace Ometria\AbandonedCarts\Controller\Cartlink;
class Index extends \Magento\Framework\App\Action\Action
{
    protected $customerModelSession;
    protected $abandonedCartsHelperConfig;
    protected $controllerResultRedirectFactory;
    protected $salesModelQuote;
    protected $messageManager;        
    protected $checkoutSession;
    
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerModelSession, 
        \Magento\Checkout\Model\Session $checkoutSession,        
        \Ometria\AbandonedCarts\Helper\Config $abandonedCartsHelperConfig, 
        \Magento\Framework\Controller\Result\RedirectFactory $controllerResultRedirectFactory, 
        \Magento\Quote\Model\Quote $salesModelQuote,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Checkout\Model\Cart $cart
    )
    {
        $this->messageManager                   = $messageManager;
        $this->customerModelSession             = $customerModelSession;
        $this->abandonedCartsHelperConfig       = $abandonedCartsHelperConfig;
        $this->controllerResultRedirectFactory  = $controllerResultRedirectFactory;
        $this->salesModelQuote                  = $salesModelQuote;
        $this->checkoutSession                  = $checkoutSession;
        $this->cart                             = $cart;
        return parent::__construct($context);
    }
    
    public function execute()
    {
        $message_incorrect_link = 'Cart link is incorrect or expired';
        $session = $this->customerModelSession;
        $helper  = $this->abandonedCartsHelperConfig;

        if (!$helper->isDeeplinkEnabled())
        {
            return $this->resultFactory->create(
                \Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT
                )->setUrl('/');
        }
        
        $token = $this->getRequest()->getParam('token');
        $id = $this->getRequest()->getParam('id');

        $is_ok = false;

        if ($id && $token)
        {
            $quote = $this->salesModelQuote->load($id);
            if (!$quote || !$quote->getId())
            {
                $this->messageManager->addNotice($message_incorrect_link);
                return $this->resultFactory->create(
                    \Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT
                    )->setUrl('/');
            }

            if ($helper->shouldCheckDeeplinkgToken())
            {
                $computed_token = substr(md5($quote->getCreatedAt().$quote->getId()), 0, 12);
                if ($token!=$computed_token) 
                {
                    $this->messageManager->addNotice($message_incorrect_link);
                    return $this->resultFactory->create(
                        \Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT
                        )->setUrl('/');
                }
            }
            
            $quote->setIsActive(true);
            $quote->save();
            $this->checkoutSession->setQuoteId($quote->getId());           
            

            $cart_path = $helper->getCartUrl();
                                                
            if (substr($cart_path,0,7)=='http://' || substr($cart_path,0,8)=='https://')
            {
                return $this->resultFactory->create(
                    \Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT
                    )->setUrl($cart_path);
            } 
            else 
            {
                return $this->resultFactory->create(
                    \Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT
                    )->setUrl($cart_path);
            }
        } 
        else 
        {
            return $this->resultFactory->create(
                \Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT
                )->setUrl('/');
        }
    }
}