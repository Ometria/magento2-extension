<?php
require_once 'app/autoload.php';
require_once 'BaseTest.php';

use Ometria\Api\Helper\Format\V1\Products as Helper;
class ProductsTest extends BaseTest
{
    protected $baseUrl;
    protected function setup()
    {
        $this->baseUrl = $this->getEnv('baseUrl');
    }
    
    public function testProducts()
    {
        $blank_results = Helper::getBlankArray();
        $result = $this->getUrl('/ometria_api/v1/products');        
        $result = json_decode($result);        
        
        $keys1 = array_keys($blank_results);
        $keys2 = array_keys((array) $result[0]);
        
        $this->assertTrue(is_object($result) || is_array($result));
        $this->assertEquals($keys1, $keys2);
    }
}