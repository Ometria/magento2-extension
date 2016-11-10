<?php
namespace Ometria\AbandonedCarts\Block;
use stdClass;
class Redirect extends \Magento\Framework\View\Element\Template
{
    protected $request;
    protected $salesModelQuote;
    protected $scopeConfig;
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Quote\Model\Quote $salesModelQuote,         
        array $data = []
    )
    {    
        $this->request = $context->getRequest();
        $this->scopeConfig = $context->getScopeConfig();
        $this->salesModelQuote = $salesModelQuote;
        return parent::__construct($context, $data);
    }
    
    protected function getQuoteIdFromRequest()
    {
        $quote_id   = $this->_request->getParam('id');
        $quote      = $this->salesModelQuote->load($quote_id);
    }
    
    protected function getUrlModelParams($quote_id)
    {
        $quote_id   = $quote_id ? $quote_id : $this->getQuoteIdFromRequest();
        $quote      = $this->salesModelQuote->load($quote_id);
        $params     = [];
        if($quote->getStoreId())
        {
            $params = ['_scope'=>$quote->getStoreId()];
        }
        return $params;    
    }
    
    protected function getCheckoutUrl()
    {
        $path = $this->scopeConfig->getValue('ometria_abandonedcarts/abandonedcarts/cartpath');
        $path = $path ? $path : 'checkout/cart';
        $path = trim($path, '/');
        return $path;
    }
    
    public function getJsonCheckoutData($quote_id=null)
    {
        $o = new stdClass;
        $params = $this->getUrlModelParams($quote_id);    
        $o->url = $this->_urlBuilder->getBaseUrl($params) . 
            $this->getCheckoutUrl();
        
        return json_encode($o);
    }
}
