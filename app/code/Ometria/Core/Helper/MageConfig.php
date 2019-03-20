<?php
namespace Ometria\Core\Helper;

use Magento\Store\Model\ScopeInterface;

class MageConfig
{
    protected $scopeConfig;
    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function get($path)
    {
        $parts = explode('/', $path);
        $top   = array_shift($parts);
        $config = $this->scopeConfig->getValue(
            $top,
            ScopeInterface::SCOPE_STORE
        );

        if($config === null) {
            return null;
        }

        foreach($parts as $part) {
            if(!array_key_exists($part, $config)) {
                return null;
            }
            $config = $config[$part];
        }
        
        return $config;
    }
}
