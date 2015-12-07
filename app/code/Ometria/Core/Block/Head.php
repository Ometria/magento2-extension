<?php
namespace Ometria\Core\Block;
class Head extends \Magento\Framework\View\Element\Template
{
    protected $scopeConfig;
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\View\Element\Template\Context $context, 
        array $data = []
    )
    {
        $this->scopeConfig = $scopeConfig;
        return parent::__construct($context, $data);
    }
    
    public function getAPIKey()
    {
        return $this->scopeConfig->getValue('ometria/general/apikey');
    }
}