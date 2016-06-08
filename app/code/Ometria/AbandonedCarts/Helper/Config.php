<?php
namespace Ometria\AbandonedCarts\Helper;

use \Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\App\Helper\AbstractHelper;
use \Magento\Framework\App\Helper\Context;

class Config extends AbstractHelper {
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(Context $context
//         ScopeConfigInterface $scopeConfig
    )
    {
        $this->scopeConfig = $context->getScopeConfig();

        parent::__construct($context);
    }


    public function getCartUrl() {
        return $this->scopeConfig->getValue('ometria_abandonedcarts/abandonedcarts/cartpath');
    }

    public function isDeeplinkEnabled() {
        return $this->scopeConfig->isSetFlag('ometria_abandonedcarts/abandonedcarts/enabled');
    }

    public function shouldCheckDeeplinkgToken() {
        return $this->scopeConfig->isSetFlag('ometria_abandonedcarts/abandonedcarts/check_token');
    }
}