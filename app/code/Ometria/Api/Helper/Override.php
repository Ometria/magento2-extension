<?php
namespace Ometria\Api\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Override extends AbstractHelper
{
    /**
     * Used to override PHP limits on execution time and memory if configured to do so
     */
    public function overridePHPLimits()
    {
        if ($this->scopeConfig->isSetFlag('ometria/advanced/override_memory')) {
            ini_set('memory_limit', '-1');
        }

        if ($this->scopeConfig->isSetFlag('ometria/advanced/override_execution')) {
            ini_set('max_execution_time', 0);
        }
    }
}
