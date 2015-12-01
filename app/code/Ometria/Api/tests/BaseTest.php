<?php
require_once(__DIR__ . '/../Model/Hash.php');
use Ometria\Api\Model\Hash;

class BaseTest extends \PHPUnit_Framework_TestCase
{    
    protected function getUrl($url)
    {
        $domain      = $this->getDomainFromUrl($this->baseUrl);
        $method_name = $this->getMethodNameFromUrl($url);
        $public_key  = 'abc123';
        $private_key = '123abc';
        
        $request     = [
            'request_timestamp'=>time(),
        ];        

        // var_dump($domain, $method_name, $public_key, $private_key, $request);
        
        $signature   = Hash::signRequest($domain, $method_name, $public_key, $private_key, $request);
        $request['signature'] = $signature;
        
        $url .= '?' . http_build_query($request);
        echo $url,"\n";
                        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$this->baseUrl . $url);                       
        curl_setopt($ch, CURLOPT_FAILONERROR,1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $retValue = curl_exec($ch);			 
        curl_close($ch);
        return $retValue;    
    }
    
    protected function getDomainFromUrl($url)
    {
        $parts = explode('://', $url);
        return $parts[1];
    }
    
    protected function getMethodNameFromUrl($url)
    {
        $parts = explode('/',$url);
        $flag  = false;
        foreach($parts as $part)
        {
            if($flag)
            {
                return $part;
            }
            if($part === 'v1' || $part === 'v2')
            {
                $flag = true;
            }
        }
    }
}