<?php
namespace Ometria\AbandonedCarts\Controller\Cartlink;

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

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerModelSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Ometria\AbandonedCarts\Helper\Config $abandonedCartsHelperConfig,
        \Magento\Quote\Model\Quote $salesModelQuote,
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
            if (!$quote || !$quote->getId() || !$quote->getIsActive())
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
                    $this->messageManager->addNotice(self::CART_LINK_TOKEN_INVALID);
                    return $this->resultFactory->create(
                        \Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT
                        )->setUrl('/');
                }
            }

            $this->checkoutSession->setQuoteId($quote->getId());
            $data = $this->session->getVisitorData();
            $data['quote_id'] = $quote->getId();
            $data['last_visit_at'] = $data['last_visit_at'] ?? (new \DateTime())->format(\Magento\Framework\Stdlib\DateTime::DATETIME_PHP_FORMAT);
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
