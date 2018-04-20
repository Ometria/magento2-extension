<?php
namespace Ometria\AbandonedCarts\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Model\Quote as QuoteModel;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Framework\Session\SessionManagerInterface as Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Ometria\AbandonedCarts\Helper\Config as ConfigHelper;

class Redirect extends Template
{
    const CART_NOT_FOUND_MSG = 'Cart link is incorrect or expired';

    /** @var ConfigHelper */
    private $configHelper;

    /** @var QuoteModel */
    private $quoteModel;

    /** @var Session */
    private $session;

    /**
     * @param Context $context
     * @param ConfigHelper $configHelper
     * @param QuoteModel $quoteModel
     * @param Session $session
     * @param array $data
     */
    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        QuoteModel $quoteModel,
        Session $session,
        array $data = []
    ) {
        $this->configHelper = $configHelper;
        $this->quoteModel = $quoteModel;
        $this->session = $session;

        parent::__construct($context, $data);
    }

    /**
     * @return mixed
     */
    public function getCookieLifeTime()
    {
        return $this->configHelper->getCookieLifeTime();
    }

    /**
     * @return string
     */
    public function getRedirectUrl()
    {
        $params = [];

        $currentStore = $this->_storeManager->getStore();
        $quoteStore = $this->getStoreFromQuote();

        if ($currentStore->getId() != $quoteStore->getId()) {
            $params['_query']['___store'] = $quoteStore->getCode();
            $params['_query']['___from_store'] = $currentStore->getCode();
        }

        return $this->_urlBuilder->getUrl(
            $this->getRedirectPath(),
            $params
        );
    }

    /**
     * @return string
     */
    private function getRedirectPath()
    {
        $redirectPath = $this->configHelper->getCartPath();

        if (!isset($redirectPath)) {
            $redirectPath = 'checkout/cart';
        }

        return ltrim($redirectPath, '/');
    }

    /**
     * @return array
     */
    private function getStoreFromQuote()
    {
        $quote = $this->getQuoteFromSession();

        if ($quote !== false && $quote->getStoreId()) {
            return $this->_storeManager->getStore($quote->getStoreId());
        }

        /**
         * If for any reason the quote was not found or storeId is not
         * set on it, the current store context will be used for the redirect.
         */
        return $this->_storeManager->getStore();
    }

    /**
     * @return bool|QuoteModel
     * @throws NoSuchEntityException
     */
    private function getQuoteFromSession()
    {
        $data = $this->session->getVisitorData();

        /**
         * This should never happen as the controller would catch
         * the condition but just incase, return false here to
         * allow the block to still render.
         */
        if (!isset($data['quote_id'])) {
            return false;
        }

        /**
         * Using the quote model rather than repository here is not ideal
         * however during loading of a quote using the repository, its storeId
         * is hard coded to the current store context.
         *
         * @see \Magento\Quote\Model\QuoteRepository::loadQuote()
         */
        $quote = $this->quoteModel->loadByIdWithoutStore($data['quote_id']);

        return $quote;
    }
}
