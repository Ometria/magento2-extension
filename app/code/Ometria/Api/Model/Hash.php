<?php
namespace Ometria\Api\Model;

use Magento\Framework\App\RequestInterface;

class Hash
{
    /** @var RequestInterface */
    private $request;

    /**
     * @param RequestInterface $request
     */
    public function __construct(
        RequestInterface $request
    ) {
        $this->request = $request;
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
    {
        $data = array_filter($data);

        if (array_key_exists('request_timestamp', $data)) {
            $data['request_timestamp'] = (int) $data['request_timestamp'];
        }

        ksort($data);

        $data = json_encode($data);
        $buffer = array($domain, $method, $data, $public_key);
        $buffer_str = implode(":", $buffer);

        return hash_hmac('sha256', $buffer_str, $private_key);
    }

    /**
     * @param $method
     * @param $public_key
     * @param $private_key
     * @return bool
     */
    public function checkRequest($method, $public_key, $private_key)
    {
        $data = $this->request->getParams();
        $writer = new \Zend_Log_Writer_Stream(BP. '/var/log/hash.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info("hash Module");
        $logger->info($data['signature']);

        if (!isset($data['signature'])) {
            return false;
        }

        // Dont check the signature param
        $signature = $data['signature'];
        unset($data['signature']);

        $logger->info("Http Host");
        $logger->info($_SERVER['HTTP_HOST']);
        $calculated_signature = $this->signRequest("a08f83576e0494fa3a0e1743f07c4455-1153874368.eu-west-1.elb.amazonaws.com", $method, $public_key, $private_key, $data);
        $logger->info(print_r($calculated_signature));



        if ($calculated_signature != $signature) {
            return false;
        }

        // check dates to prevent replay attacks
        $ts = (int) $data['request_timestamp'];
        $logger->info(print_r($ts));
        if ($ts < time() - 3600) {
            return false;
        }
        $logger->info("Before returning true");
        return true;
    }
}
