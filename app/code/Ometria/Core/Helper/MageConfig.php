<?php
namespace Ometria\Core\Helper;
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
        $config = $this->scopeConfig->getValue($top);

        if($config === null)
        {
            return null;
        }

        // $parts = explode('/',$path);
        
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
}
