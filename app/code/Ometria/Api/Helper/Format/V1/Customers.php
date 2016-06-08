<?php
namespace Ometria\Api\Helper\Format\V1;
class Customers
{
    static public function getBlankArray()
    {
        return [
            "@type"             => "contact",
            "id"                => "",
            "email"             => "",
            "prefix"            => "",
            "firstname"         => "",
            "middlename"        => "",
            "lastname"          => "",
            "gender"            => "",
            "date_of_birth"     => "",
            "store_id"          => "",
            "marketing_optin"   => true,
            "country_id"        => "GB"
        ];
    }
}