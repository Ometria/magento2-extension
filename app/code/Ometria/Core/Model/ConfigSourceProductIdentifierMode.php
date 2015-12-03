<?php
namespace Ometria\Core\Model;
class ConfigSourceProductIdentifierMode  implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'id', 'label' => __('Product ID')], 
            ['value' => 'sku', 'label' => __('Product SKU')]
        ];    
    }
}
