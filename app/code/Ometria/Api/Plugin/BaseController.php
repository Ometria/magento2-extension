<?php
namespace Ometria\Api\Plugin;
use Exception;
class BaseController
{
    protected $resultJsonFactory;
    public function __construct(
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory)
    {
        $this->resultJsonFactory = $resultJsonFactory;
    }
    
    public function aroundDispatch($subject, $proceed, $request)
    {        
        $result = null;
        try
        {
            $result = $proceed($request);
        }
        catch (Exception $e){
            $result = $this->resultJsonFactory->create();
            $result->setData(['error'=>$e->getMessage()]);
        }

        
        return $result;
    }
}
