<?php
namespace Ometria\Api\Helper\Format\V1;
class Categories
{
    static public function getBlankArray()
    {
        return [
            "entity_id" =>null,
            "id"=>null,
            "parent_id"=>null,
            "name"=>null,
            "position"=>null,
            "level"=>null,
            "is_active"=>null,
            "product_count"=>null,
            "children_data"=>null
        ];
    }
}