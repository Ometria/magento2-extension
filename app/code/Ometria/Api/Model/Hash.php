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

        if ($ts < time() - 1200) {
            return false;
        }

        return true;
    }
}
