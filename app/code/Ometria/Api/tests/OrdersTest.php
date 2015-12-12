<?php
require_once 'app/autoload.php';
require_once 'BaseTest.php';

use Ometria\Api\Helper\Format\V1\Orders as Helper;
class OrdersTest extends BaseTest
{
    protected $baseUrl;
    protected function setup()
    {
        $this->baseUrl = $this->getEnv('baseUrl');
    }
    
    public function testOrders()
    {
        $blank_results = Helper::getBlankArray();
        $result = $this->getUrl('/ometria_api/v1/orders');        
        $result = json_decode($result);        
        
        $keys1 = array_keys($blank_results);
        $keys2 = array_keys((array) $result[0]);
        
        $this->assertTrue(is_object($result) || is_array($result));
        sort($keys1);
        sort($keys2);
        $this->assertEquals($keys1, $keys2);
    }

//     public function testProducts()
//     {
//         $result = $this->assertStatus200('/ometria_api/v1/products');
//     }
//     
//     public function testCustomers()
//     {
//         $result = $this->assertStatus200('/ometria_api/v1/customers');
//     }
// 
//     public function testSubscribers()
//     {
//         $result = $this->assertStatus200('/ometria_api/v1/subscribers');
//     }
//     
//     public function testStores()
//     {
//         $result = $this->assertStatus200('/ometria_api/v1/stores');
//     }
//     
//     public function testAttributeTypes()
//     {
//         $result = $this->assertStatus200('/ometria_api/v1/attribute_types');
//     }            
//     
//     public function testAttributes()
//     {
//         $result = $this->assertStatus200('/ometria_api/v1/attributes');
//     }
//     
//     public function testCategories()
//     {
//         $result = $this->assertStatus200('/ometria_api/v1/categories');
//     }
//     
//     public function testVersion()
//     {
//         $result = $this->assertStatus200('/ometria_api/v1/version');
//     }   
//     
//     public function testGetSettings()
//     {
//         $result = $this->assertStatus200('/ometria_api/v1/get_settings');
//     }
//     
//     public function testMagentoInfo()
//     {
//         $result = $this->assertStatus200('/ometria_api/v1/magento_info');
//     }
    
//     protected function getUrl($url)
//     {
//         $ch = curl_init();
//         curl_setopt($ch, CURLOPT_URL,$this->baseUrl . $url);                       
//         curl_setopt($ch, CURLOPT_FAILONERROR,1);
//         curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
//         curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
//         curl_setopt($ch, CURLOPT_TIMEOUT, 15);
//         $retValue = curl_exec($ch);			 
//         curl_close($ch);
//         return $retValue;    
//     }
}