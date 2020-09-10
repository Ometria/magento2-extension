<?php
namespace Ometria\Core\Logger;

use Monolog\Logger;
use Ometria\Core\Helper\Config as ConfigHelper;

class Ometria extends Logger
{
    /**
     * Ometria constructor.
     * @param ConfigHelper $configHelper
     * @param string $name
     * @param array $handlers
     * @param array $processors
     */
    public function __construct(
        ConfigHelper $configHelper,
        $name,
        $handlers = array(),
        $processors = array()
    ) {
        parent::__construct($name, $handlers, $processors);

        $this->configHelper = $configHelper;
    }

    /**
     * @param int $level
     * @param string $message
     * @param array $context
     * @return bool|void
     */
    public function addRecord($level, $message, array $context = array())
    {
        if ($this->configHelper->isLogEnabled() == true) {
            parent::addRecord($level, $message, $context);
        }
    }
}
