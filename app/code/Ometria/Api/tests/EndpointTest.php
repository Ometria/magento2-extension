<?php
require_once 'app/autoload.php';
require_once 'BaseTest.php';
use Ometria\Api\Model\Hash;

class EndpointTest extends BaseTest
{
    protected $baseUrl;
    protected function setup()
    {
        $this->baseUrl = $this->getEnv('baseUrl');
    }
    
    public function testOrders()
    {
        $result = $this->assertStatus200('/ometria_api/v1/orders');
    }

    public function testProducts()
    {
        $result = $this->assertStatus200('/ometria_api/v1/products');
    }
    
    public function testCustomers()
    {
        $result = $this->assertStatus200('/ometria_api/v1/customers');
    }

    public function testSubscribers()
    {
        $result = $this->assertStatus200('/ometria_api/v1/subscribers');
    }
    
    public function testStores()
    {
        $result = $this->assertStatus200('/ometria_api/v1/stores');
    }
    
    public function testAttributeTypes()
    {
        $result = $this->assertStatus200('/ometria_api/v1/attribute_types');
    }            
    
    public function testAttributes()
    {
        $result = $this->assertStatus200('/ometria_api/v1/attributes');
    }
    
    public function testCategories()
    {
        $result = $this->assertStatus200('/ometria_api/v1/categories');
    }
    
    public function testVersion()
    {
        $result = $this->assertStatus200('/ometria_api/v1/version');
    }   
    
    public function testGetSettings()
    {
        $result = $this->assertStatus200('/ometria_api/v1/get_settings');
    }
    
    public function testMagentoInfo()
    {
        $result = $this->assertStatus200('/ometria_api/v1/magento_info');
    }
                         
    protected function assertStatus200($url)
    {
        $result = $this->getUrl($this->baseUrl . $url);
        $lines  = preg_split('[\r\n]',$result);
        $first  = array_shift($lines);
        $this->assertTrue(strpos($first, '200 OK') !== false);
    }
    
    protected function getUrl($url)
    {
        $domain      = $this->getDomainFromUrl($this->baseUrl);
        $method_name = $this->getMethodNameFromUrl($url);
        $public_key  = $this->getEnv('public_key');
        $private_key = $this->getEnv('private_key');
        
        $request     = [
            'request_timestamp'=>time(),
        ];        

        // var_dump($domain, $method_name, $public_key, $private_key, $request);
        
        $signature   = Hash::signRequest($domain, $method_name, $public_key, $private_key, $request);
        $request['signature'] = $signature;
        
        $url .= '?' . http_build_query($request);

        // echo "\n" . $url . "\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_HEADER,1);                
        curl_setopt($ch, CURLOPT_FAILONERROR,1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $retValue = curl_exec($ch);			 
        curl_close($ch);
        return $retValue;    
    }
}