<?php
namespace Ometria\Api\Helper;
use Ometria\Api\Controller\V1\Get\Settings;

class Config
{
    const CONFIG_TOP = 'ometria';
    protected $scopeConfig;
    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function get($path=null)
    {
        $config = $this->scopeConfig->getValue($this->getTopLevelName());
        if(!$path)
        {
            return $config;
        }
        $parts = explode('/',$path);
        
        foreach($parts as $part)
        {
            if(!array_key_exists($part, $config))
            {
                return null;
            }
            $config = $config[$part];
        }
        
        return $config;
    }
    
    protected function getTopLevelName()
    {
        return self::CONFIG_TOP;
    }
}