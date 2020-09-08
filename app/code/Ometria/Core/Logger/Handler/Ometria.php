<?php
namespace Ometria\Core\Logger\Handler;

use Monolog\Logger;
use Magento\Framework\Logger\Handler\Base;

class Ometria extends Base
{
    protected $fileName = '/var/log/ometria.log';
    protected $loggerType = Logger::ERROR;
}
