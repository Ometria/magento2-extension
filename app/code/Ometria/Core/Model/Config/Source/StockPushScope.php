<?php
namespace Ometria\Core\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class StockPushScope implements ArrayInterface
{
    const SCOPE_DISABLED = 0;
    const SCOPE_GLOBAL = 1;
    const SCOPE_CHANNEL = 2;


    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::SCOPE_DISABLED, 'label' => __('Disabled')],
            ['value' => self::SCOPE_GLOBAL, 'label' => __('Global')],
            ['value' => self::SCOPE_CHANNEL, 'label' => __('Sales Channel')]
        ];
    }
}
