<?php
namespace Ometria\Api\Plugin;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use Ometria\Api\Helper\Override as OverrideHelper;

class BaseController
{
    /** @var OverrideHelper */
    private $overrideHelper;

    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var Http */
    private $request;

    public function __construct(
        OverrideHelper $overrideHelper,
		JsonFactory $resultJsonFactory,
        Http $request
    ) {
        $this->overrideHelper = $overrideHelper;
        $this->resultJsonFactory = $resultJsonFactory;
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
            $result = $this->resultJsonFactory->create();
           // $result->setData(['error' => htmlspecialchars($e->getMessage())]);   
           $result->setData(['error' => get_class($e)." code ".$e->getCode() .
                   " in " . basename($e->getFile()) . " line " . $e->getLine() ]); 
        }

        return $result;
    }
}
