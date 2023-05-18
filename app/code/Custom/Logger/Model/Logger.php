<?php

namespace Custom\Logger\Model;

use Psr\Log\LoggerInterface;
use Monolog\Logger as MonologLogger;
use Magento\Framework\Logger\Handler\Base;
use Magento\Framework\Filesystem\DriverInterface;

class Logger extends MonologLogger implements LoggerInterface
{
    public function __construct(
        DriverInterface $filesystem,
        $name = 'custom',
        $handlers = [],
        $processors = []
    ) {
        $logFilePath = '/var/log/custom.log';
        $handler = new Base($filesystem, $logFilePath);
        $handlers[] = $handler;
        parent::__construct($name, $handlers, $processors);
    }
}

