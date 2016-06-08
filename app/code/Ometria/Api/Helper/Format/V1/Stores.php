<?php
namespace Ometria\Api\Helper\Format\V1;
class Stores
{
    static public function getBlankArray()
    {
        return [
            "id"         => null,
            "title"      => null,
            "group_id"   => null,
            "website_id" => null,
            "url"        => null
        ];
    }
}