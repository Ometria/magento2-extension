<?php
namespace Ometria\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Ometria\Core\Helper\MageConfig;

class Config extends AbstractHelper
{
    /** @var MageConfig */
    private $coreHelperMageConfig;
    protected $logger;

    public function __construct(
        Context $context,
        MageConfig $coreHelperMageConfig
    ) {
        parent::__construct($context);

        $this->coreHelperMageConfig = $coreHelperMageConfig;
        $this->logger               = $context->getLogger();
    }

    public function isEnabled()
    {
        return $this->coreHelperMageConfig->get('ometria/general/enabled');
    }

    public function isDebugMode()
    {
        return $this->coreHelperMageConfig->get('ometria/advanced/debug');
    }

    // Is data layer configured?
    public function isUnivarEnabled()
    {
        return $this->coreHelperMageConfig->get('ometria/advanced/univar');
    }

    public function isPingEnabled()
    {
        return $this->coreHelperMageConfig->get('ometria/advanced/ping');
    }

    public function isScriptDeferred()
    {
        return $this->coreHelperMageConfig->get('ometria/advanced/scriptload');
    }

    public function getAPIKey($store_id = null)
    {
        if ($store_id) {
            return $this->coreHelperMageConfig->get('ometria/general/apikey', $store_id);
        } else {
            return $this->coreHelperMageConfig->get('ometria/general/apikey');
        }
    }

    /**
     * @return string
     */
    public function getPushAPIKey()
    {
        return (string) $this->scopeConfig->getValue(
            'ometria/general/pushapikey',
            ScopeInterface::SCOPE_STORE
        );
    }

    public function isConfigured()
    {
        return $this->isEnabled() && $this->getAPIKey() != "";
    }

    public function log($message, $level = \Psr\Log\LogLevel::DEBUG)
    {
        $this->logger->log($level, $message);
    }

    public function isSkuMode()
    {
        return $this->coreHelperMageConfig->get('ometria/advanced/productmode') == 'sku';
    }

    /**
     * @return bool
     */
    public function canUseConfigurableImage()
    {
        return (bool) $this->coreHelperMageConfig->get('ometria/advanced/use_configurable_image');
    }

    /**
     * @return string
     */
    public function getPreferredProductAttribute()
    {
        return (string) $this->coreHelperMageConfig->get('ometria/advanced/preferred_product_attribute');
    }

    /**
     * @return string
     */
    public function getStockPushScope()
    {
        return (string) $this->coreHelperMageConfig->get('ometria/advanced/stock_push_scope');
    }

    /**
     * @return bool
     */
    public function isCookiebotEnabled()
    {
        return (bool) $this->coreHelperMageConfig->get('ometria/advanced/enable_cookiebot');
    }

    /**
     * @return string
     */
    public function getCookiebotClass()
    {
        return (string) $this->coreHelperMageConfig->get('ometria/advanced/cookiebot_classification');
    }

    /**
     * @param null
     * @return string
     */
    public function getLogConfig()
    {
        $statusLogValue = $this->scopeConfig->getValue('ometria/advanced/show_log');
        return $statusLogValue;
    }
}
