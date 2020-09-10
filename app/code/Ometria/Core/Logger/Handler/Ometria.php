<?php
namespace Ometria\Core\Logger\Handler;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Ometria extends Base
{
    protected $loggerType = Logger::DEBUG;
    protected $fileName = '/var/log/ometria.log';
}
