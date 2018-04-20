<?php
namespace Ometria\AbandonedCarts\Controller\Cartlink;

use Psr\Log\LoggerInterface;

class Index extends \Magento\Framework\App\Action\Action
{
    const CART_LINK_QUOTE_INVALID = 'Cart link is incorrect or expired';
    const CART_LINK_TOKEN_INVALID = 'Deeplink is incorrect or expired';

    protected $customerModelSession;
    protected $abandonedCartsHelperConfig;
    protected $controllerResultRedirectFactory;
    protected $salesModelQuote;
    protected $messageManager;        
    protected $checkoutSession;
    protected $session;
    protected $cookieHelper;

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerModelSession, 
        \Magento\Checkout\Model\Session $checkoutSession,        
        \Ometria\AbandonedCarts\Helper\Config $abandonedCartsHelperConfig,
        \Magento\Quote\Model\Quote $salesModelQuote,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\Session\SessionManagerInterface $session,
        \Magento\Customer\Model\Visitor $visitor, 
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieHelper,
        LoggerInterface $logger
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
        $this->logger                           = $logger;

        return parent::__construct($context);
    }
    
    public function execute()
    {
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
                $this->messageManager->addNotice(self::CART_LINK_QUOTE_INVALID);
                return $this->resultFactory->create(
                    \Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT
                    )->setUrl('/');
            }

            if ($helper->shouldCheckDeeplinkgToken())
            {
                $computed_token = substr(md5($quote->getCreatedAt().$quote->getId()), 0, 12);
                if ($token!=$computed_token) 
                {
                    // Log any token mismatches
                    $this->logger->warning(
                        self::CART_LINK_TOKEN_INVALID,
                        [
                            'token' => $computed_token,
                            'quote' => [
                                'id' => $quote->getId(),
                                'created_at' => $quote->getCreatedAt()
                            ]
                        ]);

                    $this->messageManager->addNotice(self::CART_LINK_TOKEN_INVALID);
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
                                      
            return $this->resultFactory->create(
                \Magento\Framework\Controller\ResultFactory::TYPE_PAGE
            );
        } 
        else 
        {
            return $this->resultFactory->create(
                \Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT
                )->setUrl('/');
        }
    }
}