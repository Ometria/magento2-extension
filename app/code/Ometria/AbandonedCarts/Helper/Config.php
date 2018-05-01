<?php
namespace Ometria\AbandonedCarts\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\Session\Config as SessionConfig;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    const XML_CONFIG_CART_PATH     = 'ometria_abandonedcarts/abandonedcarts/cartpath';
    const XML_CONFIG_LINK_ENABLED  = 'ometria_abandonedcarts/abandonedcarts/enabled';
    const XML_CONFIG_TOKEN_ENABLED = 'ometria_abandonedcarts/abandonedcarts/check_token';

    /**
     * @return mixed
     */
    public function getCartPath()
    {
        return $this->scopeConfig->getValue(
            self::XML_CONFIG_CART_PATH
        );
    }

    /**
     * @return bool
     */
    public function isDeeplinkEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_CONFIG_LINK_ENABLED
        );
    }

    /**
     * @return bool
     */
    public function shouldCheckDeeplinkgToken()
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_CONFIG_TOKEN_ENABLED
        );
    }

    /**
     * @return mixed
     */
    public function getCookieLifeTime()
    {
        return $this->scopeConfig->getValue(
            SessionConfig::XML_PATH_COOKIE_LIFETIME,
            ScopeInterface::SCOPE_STORE
        );
    }
}