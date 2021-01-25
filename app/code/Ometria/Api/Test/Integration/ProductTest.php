<?php
namespace Ometria\Api\Test\Api;

use Magento\TestFramework\Request;
use Magento\TestFramework\TestCase\AbstractController;
use Ometria\Api\Api\Data\ProductInterface;
use Ometria\Api\Model\Hash;

/**
 * Test the Products API
 */
class ProductTest extends AbstractController
{
    const PRODUCT_V1_PATH = 'ometria_api/V1/products';
    const PRODUCT_V2_PATH = 'ometria_api/V2/products';

    /** @var Hash */
    private $hash;

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->hash = $this->_objectManager->create(Hash::class);
    }

    /**
     * @magentoConfigFixture current_store ometria/general/apikey test-public-key
     * @magentoConfigFixture current_store ometria/general/privatekey test-private-key
     * @magentoConfigFixture current_store ometria/advanced/preferred_product_attribute preferred_product_sku
     * @magentoDataFixture Magento/Catalog/_files/multiple_mixed_products_2.php
     */
    public function testProductV1()
    {
        $url = $this->getSignedUrl(
            self::PRODUCT_V1_PATH . '?final_price=true&listing=true'
        );

        $this->getRequest()->setMethod(Request::METHOD_GET);
        $this->dispatch($url);

        $this->assertSame(200, $this->getResponse()->getHttpResponseCode());
        $this->assertJson($this->getResponse()->getContent());
        $this->assertContains('Configurable Product 12345', $this->getResponse()->getContent());

        return $this->getResponse();
    }

    /**
     * @magentoConfigFixture current_store ometria/general/apikey test-public-key
     * @magentoConfigFixture current_store ometria/general/privatekey test-private-key
     * @magentoConfigFixture current_store ometria/advanced/preferred_product_attribute preferred_product_sku
     * @magentoDataFixture Magento/Catalog/_files/multiple_mixed_products_2.php
     */
    public function testProductV2()
    {
        $url = $this->getSignedUrl(
            self::PRODUCT_V2_PATH . '?listing=true'
        );

        $this->getRequest()->setMethod(Request::METHOD_GET);
        $this->dispatch($url);

        $this->assertSame(200, $this->getResponse()->getHttpResponseCode());
        $this->assertJson($this->getResponse()->getContent());
        $this->assertContains('Configurable Product 12345', $this->getResponse()->getContent());

        return $this->getResponse();
    }

    /**
     * @depends testProductV1
     * @depends testProductV2
     */
    public function testCompareV1andV2Response(\Magento\Framework\App\Response\Http $productResponseV1, $productResponseV2)
    {
        $productsV1 = $this->removeUntestableData($productResponseV1->getContent());
        $productsV2 = $this->removeUntestableData($productResponseV2->getContent());

        $this->assertJsonStringEqualsJsonString($productsV1, $productsV2);
    }

    /**
     * Manipulate the data to remove elements which are untestable between the V1 and V2 APIs
     *
     * @param $products
     * @return false|string
     */
    private function removeUntestableData($products)
    {
        $json = json_decode($products, true);

        foreach ($json as &$product) {
            /**
             * Due to the Magento testing framework creating new attributes for each test, the auto increment IDs will show as a
             * difference in the response. For this reason the IDs are set to 0 here for the configurable attributes that would
             * exhibit this issue.
             */
            foreach ($product['attributes'] as &$attribute) {
                if ($attribute['type'] = 'test_configurable') {
                    $attribute['id'] = 0;
                }
            }

            /**
             * Unset tax and final_price_incl_tax values that are only present in V2 API
             */
            unset($product[ProductInterface::FINAL_PRICE_INCL_TAX]);
            unset($product[ProductInterface::TAX_AMOUNT]);
        }

        return json_encode($json);
    }

    /**
     * @param $url
     * @param $data
     * @return string
     */
    private function getSignedUrl($url)
    {
        $time = time();
        $parsedUrl = parse_url($url);

        $data = [
            'request_timestamp' => $time
        ];

        if (isset($parsedUrl['query'])) {
            foreach (explode('&', $parsedUrl['query']) as $param) {
                list($key, $value) = explode('=', $param);
                $data[$key] = $value;
            }
        }

        $signature = $this->hash->signRequest(
            'localhost',
            'products',
            'test-public-key',
            'test-private-key',
            $data
        );

        $data['signature'] = $signature;

        return sprintf('%s?%s',
            $parsedUrl['path'],
            http_build_query($data)
        );
    }
}
