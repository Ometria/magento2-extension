<?php
namespace Ometria\Api\Helper\Format\V1;
class Attributes
{
    static public function getBlankArray()
    {
        return [
            "id" =>null,
            "attribute_type"=>null,
            "title"=>null,
            "attribute_code"=>null
        ];
    }
}