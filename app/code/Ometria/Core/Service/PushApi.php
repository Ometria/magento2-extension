<?php
namespace Ometria\Core\Service;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\HTTP\Client\CurlFactory;
use Ometria\Core\Helper\Config;

/**
 * PushApi service class used to interface with Ometria push API
 *
 */
class PushApi
{
    const API_URL = 'https://api.ometria.com/v2/push';

    /** @var Json */
    private $jsonEncoder;

    /** @var CurlFactory */
    private $curlFactory;

    /** @var Config */
    private $helperConfig;

    /**
     * @param Json $jsonEncoder
     * @param CurlFactory $curlFactory
     * @param Config $helperConfig
     */
    public function __construct(
        Json $jsonEncoder,
        CurlFactory $curlFactory,
        Config $helperConfig
    ) {
        $this->jsonEncoder = $jsonEncoder;
        $this->curlFactory = $curlFactory;
        $this->helperConfig = $helperConfig;
    }

    /**
     * @param array $postData
     */
    public function pushRequest(
        array $data
    ) {
        $curl = $this->curlFactory->create();

        $curl->addHeader('X-Ometria-Auth', $this->getPushAPIKey());
        $curl->addHeader('Accept', 'application/json');
        $curl->addHeader('Content-type', 'application/json');

        try {
            $curl->post(
                self::API_URL,
                $this->jsonEncoder->serialize($data)
            );
        } catch (\Exception $e) {
            // Silent catch to prevent API breaking execution path
        }

        return $this->jsonEncoder->unserialize($curl->getBody());
    }

    /**
     * @return string
     */
    private function getPushAPIKey()
    {
        return $this->helperConfig->getPushAPIKey();
    }
}
