<?php
namespace Ometria\Api\Plugin;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use Ometria\Api\Helper\Override as OverrideHelper;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

class BaseController
{
    /** @var OverrideHelper */
    private $overrideHelper;

    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var PsrLoggerInterface */
    private $logger;

    /** @var Http */
    private $request;

    public function __construct(
        OverrideHelper $overrideHelper,
		JsonFactory $resultJsonFactory,
        PsrLoggerInterface $logger,
        Http $request
    ) {
        $this->overrideHelper = $overrideHelper;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
        $this->request = $request;
    }

    /**
     * @param $subject
     * @param $proceed
     * @param $request
     * @return Json
     */
    public function aroundDispatch($subject, $proceed, $request)
    {
        // Override PHP limits if configured to do so
        $this->overrideHelper->overridePHPLimits();

        $result = $this->resultJsonFactory->create();

        try {
            $result = $proceed($request);
        } catch (\Exception $e) {
            $this->logger->error(
                'General API dispatch error.',
                [
                    'message' => $e->getMessage(),
                    'url' => $this->request->getUriString(),
                    'trace' => $e->getTraceAsString()
                ]
            );
            $result->setData(['error' => $e->getMessage()]);
        }

        return $result;
    }
}
