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
    protected $session;
    protected $cookieHelper;
    
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerModelSession, 
        \Magento\Checkout\Model\Session $checkoutSession,        
        \Ometria\AbandonedCarts\Helper\Config $abandonedCartsHelperConfig, 
//         \Magento\Framework\Controller\Result\RedirectFactory $controllerResultRedirectFactory, 
        \Magento\Quote\Model\Quote $salesModelQuote,
//         \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\Session\SessionManagerInterface $session,
        \Magento\Customer\Model\Visitor $visitor, 
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieHelper     
    )
    {
        $this->visitor                          = $visitor;
        $this->session                          = $session;
        $this->messageManager                   = $context->getMessageManager();
        $this->customerModelSession             = $customerModelSession;
        $this->abandonedCartsHelperConfig       = $abandonedCartsHelperConfig;
        $this->controllerResultRedirectFactory  = $context->getResultRedirectFactory();
        $this->salesModelQuote                  = $salesModelQuote;
        $this->checkoutSession                  = $checkoutSession;
        $this->cart                             = $cart;
        $this->cookieHelper                     = $cookieHelper;        
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
            $data = $this->session->getVisitorData();
            $data['quote_id'] = $quote->getId();
            $this->session->setVisitorData($data);
            $this->visitor->setData($data)->save();
            // $this->cookieHelper->deleteCookie('mage-cache-sessid');
            
            $cart_path = $helper->getCartUrl();
                                      
            return $this->resultFactory->create(
                \Magento\Framework\Controller\ResultFactory::TYPE_PAGE);                                                
            // return $this->resultFactory->create(
            //     \Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT
            //     )->setUrl($cart_path);                
        } 
        else 
        {
            return $this->resultFactory->create(
                \Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT
                )->setUrl('/');
        }
    }
}