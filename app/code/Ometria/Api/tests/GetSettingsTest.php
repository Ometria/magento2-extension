<?php
require_once 'app/autoload.php';
require_once 'BaseTest.php';

use Ometria\Api\Helper\Format\V1\Get\Settings as Helper;
class GetSettingsTest extends BaseTest //\PHPUnit_Framework_TestCase
{
    protected $baseUrl;
    protected function setup()
    {
        $this->baseUrl = 'http://magento-2-dev-docs.dev';
    }
    
    public function testOrders()
    {
        $blank_results = Helper::getBlankArray();
        $result = $this->getUrl('/ometria_api/v1/get_settings');        
        $result = json_decode($result);        
        
        $keys1 = array_keys($blank_results);
        $keys2 = array_keys((array) $result);
        
        $this->assertTrue(is_object($result) || is_array($result));
        $this->assertEquals($keys1, $keys2);
    }

}