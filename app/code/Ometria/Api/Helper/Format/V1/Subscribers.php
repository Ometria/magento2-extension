<?php
namespace Ometria\Api\Helper\Format\V1;
class Subscribers
{
    static public function getBlankArray()
    {
        return [
            "@type"             =>"contact",
            "id"                =>null,
            "email"             =>null,
            "marketing_optin"   =>null,
            "store_id"          =>null
        ];
    }
}