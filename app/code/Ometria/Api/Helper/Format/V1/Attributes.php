<?php
namespace Ometria\Api\Helper\Format\V1;
class Attributes
{
    static public function getBlankArray()
    {
        return [
            "@type"   => "attribute",
            "type"    => "code",
            "id"=>"",
            "title"=>"",                
        ];
    }
}