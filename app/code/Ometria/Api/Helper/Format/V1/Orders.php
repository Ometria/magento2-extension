<?php
namespace Ometria\Api\Helper\Format\V1;
class Orders
{
    static public function getBlankArray()
    {
        return [
            "@type"  => "order",
            'state'  => '',
            "id"     => "",
            "increment_id" => "",
            "status" => "",
            "state"  => "",
            'ip_address'=>"",
            'x_forwarded_for'=>'',            
            "is_valid" => '',
            "customer" => self::getBlankCustomer(),
            "lineitems"=>[self::getBlankLineItem()],
            "timestamp"=>"",
            "subtotal"=>'',
            "discount"=>'',
            "shipping"=>'',
            "tax"=>'',
            "grand_total"=>'',
            "total_refunded"=>'',
            "currency"=>"",
            "channel"=>"",
            "store"=>"",
            "payment_method"=>"",
            "shipping_method"=>"",
            "shipping_address"=>self::getBlankAddress(),            
            "billing_address"=>self::getBlankAddress(),
            "coupon_code"=>""
        ];
    }
    
    static public function getBlankAddress()
    {
        return [
            "city"          => "",
            "state"         => "",
            "postcode"      => "",
            "country_code"  => ""        
        ];
    }
        
    static public function getBlankLineItem()
    {
        return [
            "product"           => '',
            "variant_id"        => "",
            "variant_options"   => '',
            "sku"               => '',
            "quantity"          => '',
            "unit_price"        => '',
            "total"             => ''
        ];
    }
    
    static public function getBlankCustomer()
    {
        return [
            "id"            => "",
            "firstname"     => "",
            "lastname"      => "",
            "email"         => ""        
        ];
    }
    
    
}