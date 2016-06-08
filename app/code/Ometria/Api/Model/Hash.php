<?php
namespace Ometria\Api\Model;
/**
* Static helper class, so we can test from the simpler harness without
* a fully bootstrapped Magento enviornment
*/
class Hash
{
    static public function signRequest($domain, $method, $public_key, $private_key, $data=array()){
        $data = array_filter($data);    // Normalise data
        if(array_key_exists('request_timestamp', $data))
        {
            $data['request_timestamp'] = (int) $data['request_timestamp'];
        }
        ksort($data);
        $data = json_encode($data);        
        $buffer = array($domain, $method, $data, $public_key);
        $buffer_str = implode(":", $buffer);

        return hash_hmac('sha256', $buffer_str, $private_key);
    }

    static public function checkRequest($method, $public_key, $private_key){
        $data = $_REQUEST;
        if (!isset($data['signature'])) return false;

        $signature = $data['signature'];
        unset($data['signature']);      // Dont check the signature param

        $calculated_signature = self::signRequest($_SERVER['HTTP_HOST'], $method, $public_key, $private_key, $data);


        if ($calculated_signature!=$signature) return false;
        
        // Optional: check dates to prevent replay attacks
        // $ts_str = $data['request_timestamp'];        
        // $ts = strtotime($ts_str);
        $ts     = (int) $data['request_timestamp'];

        if ($ts<time()-3600) return false;

        return true;
    } 
}