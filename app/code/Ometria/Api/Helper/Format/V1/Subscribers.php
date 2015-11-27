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
            "prefix"            =>null,
            "firstname"         =>null,
            "middlename"        =>null,
            "lastname"          =>null,
            "gender"            =>null,
            "date_of_birth"     =>null,
            "marketing_optin"   =>null,
            "country_id"        =>null
        ];
    }
}