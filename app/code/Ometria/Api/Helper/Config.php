<?php
namespace Ometria\Api\Helper;
use Ometria\Api\Controller\V1\Get\Settings;

class Config
{
    const CONFIG_TOP                = 'ometria';
    const CONFIG_TOP_ABANDONEDCARTS = 'ometria_abandonedcarts';
    protected $scopeConfig;
    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function get($path=null, $top=null)
    {
        $top = $top ? $top : self::CONFIG_TOP;
        $config = $this->scopeConfig->getValue($top);
        if($config === null)
        {
            return null;
        }
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
        return self::CONFIG_TOP_ABANDONEDCARTS;
        return self::CONFIG_TOP;
    }
}