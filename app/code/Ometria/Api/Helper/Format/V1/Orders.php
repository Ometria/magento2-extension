<?php
namespace Ometria\Api\Helper\Format\V1;
class Orders
{
    static public function getBlankArray()
    {
        return [
            "@type"  => "order",
            "id"     => "123553",
            "status" => "complete",
            "is_valid" => true,
            "customer" => true,
            "lineitems"=>[],
            "timestamp"=>"2015-01-02T09:00:00+00",
            "subtotal"=>99.99,
            "discount"=>-10.00,
            "shipping"=>0,
            "tax"=>0,
            "grand_total"=>99.99,
            "total_refunded"=>0.00,
            "currency"=>"GBP",
            "channel"=>"online",
            "store"=>"mysite.com/en",
            "payment_method"=>"card",
            "shipping_method"=>"standard",
            "shipping_address"=>((object)[]),            
            "billing_address"=>((object)[]),
            "coupon_code"=>"FJ45-TJ5Y-5YK3-T894"
        ];
    }
}