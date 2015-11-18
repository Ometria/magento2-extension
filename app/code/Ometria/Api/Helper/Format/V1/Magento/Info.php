<?php
namespace Ometria\Api\Helper\Format\V1\Magento;
class Info
{
    static public function getBlankArray()
    {
        return [
            "version_magento"=>"",
            "version_php"=>"",
            "timezone_php"=>"",
            "timezone_magento"=>"",                                    
        ];
    }
}