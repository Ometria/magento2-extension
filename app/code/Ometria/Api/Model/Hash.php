<?php
namespace Ometria\Api\Model;

use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;

class Hash
{
    /** @var RequestInterface */
    private $request;
    
    protected $logger;

    /**
     * @param RequestInterface $request
     */
    public function __construct(
	    RequestInterface $request,
	    LoggerInterface $logger
    ) {
	    $this->request = $request;
	    $this->logger = $logger;
    }

    /**
     * @param $domain
     * @param $method
     * @param $public_key
     * @param $private_key
     * @param array $data
     * @return string
     */
    public function signRequest($domain, $method, $public_key, $private_key, $data=array())
    {   $this->logger->info("##################################### Inside signRequest ############################### ");
        $data = array_filter($data);

        if (array_key_exists('request_timestamp', $data)) {
            $data['request_timestamp'] = (int) $data['request_timestamp'];
        }

        ksort($data);

        $data = json_encode($data);
        $buffer = array($domain, $method, $data, $public_key);
	$buffer_str = implode(":", $buffer);
	$this->logger->info('&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&');
        $this->logger->info('This is a custom log message.');
	$this->logger->info('Inside SignRequest to check if we are getting public key or not !!');
	$this->logger->info('&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&&');
        return hash_hmac('sha256', $buffer_str, $private_key);
    }

    /**
     * @param $method
     * @param $public_key
     * @param $private_key
     * @return bool
     */
    public function checkRequest($method, $public_key, $private_key)
    {   $this->logger->info("##################################### Inside CheckRequest ############################### ");
        $data = $this->request->getParams();
        $this->logger->info('***************************************************************************************');
        $this->logger->info('This is a custom log message.');
        $this->logger->info('Inside checkRequest to check if we are getting public key or not !!');
        $this->logger->info($method);
        $this->logger->info($public_key);
	$this->logger->info($private_key);
	$this->logger->info('***************************************************************************************');


        if (!isset($data['signature'])) {
            return false;
        }

        // Dont check the signature param
        $signature = $data['signature'];
        unset($data['signature']);

        $calculated_signature = $this->signRequest($_SERVER['HTTP_HOST'], $method, $public_key, $private_key, $data);

        if ($calculated_signature != $signature) {
            return false;
        }

        // check dates to prevent replay attacks
        $ts = (int) $data['request_timestamp'];

        if ($ts < time() - 3600) {
            return false;
        }

        return true;
    }
}
