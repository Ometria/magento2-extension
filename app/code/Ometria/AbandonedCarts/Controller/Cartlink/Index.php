<?php
namespace Ometria\AbandonedCarts\Controller\Cartlink;

use Ometria\Core\Helper\CartToken;

class Index extends \Magento\Framework\App\Action\Action
{
    const CART_LINK_QUOTE_INVALID     = 'Cart link is incorrect or expired';
    const CART_LINK_TOKEN_INVALID     = 'Deeplink is incorrect or expired';

    protected $customerModelSession;
    protected $abandonedCartsHelperConfig;
    protected $controllerResultRedirectFactory;
    protected $salesModelQuote;
    protected $messageManager;
    protected $checkoutSession;
    protected $session;
    protected $cookieHelper;
    protected $visitor;
    protected $cart;
    protected $cartTokenHelper;


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
        ?CartToken $cartTokenHelper = null
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

        // Optional and lazily resolved so this route keeps working against a stale
        // generated/ directory.
        $this->cartTokenHelper                  = $cartTokenHelper
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(CartToken::class);

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

        // Normalise both params before use; either may arrive as a non scalar.
        $token = $this->getRequest()->getParam('token');
        $token = is_string($token) ? $token : '';
        $id    = (int)$this->getRequest()->getParam('id');

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
                // Compared against the token stored on the quote row.
                if (!$this->cartTokenHelper->isValid($quote->getData(CartToken::COLUMN), $token))
                {
                    $this->messageManager->addNotice(self::CART_LINK_TOKEN_INVALID);
                    return $this->resultFactory->create(
                        \Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT
                        )->setUrl('/');
                }
            }

            $customerId = $quote->getCustomerId();

            if ($customerId) {
                if (!$this->customerModelSession->isLoggedIn()) {
                    $returnUrl = $this->_url->getUrl(
                        'omcart/cartlink/index',
                        ['id' => $id, 'token' => $token]
                    );
                    $this->customerModelSession->setBeforeAuthUrl($returnUrl);

                    return $this->resultFactory->create(
                        \Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT
                    )->setUrl($this->_url->getUrl('customer/account/login'));
                }

                if ((int)$customerId !== (int)$this->customerModelSession->getCustomerId()) {
                    $this->messageManager->addNotice(self::CART_LINK_QUOTE_INVALID);
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

            if (!$customerId) {
                $this->visitor->setData($data)->save();
            }

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
