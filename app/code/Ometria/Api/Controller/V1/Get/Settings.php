<?php
namespace Ometria\Api\Controller\V1\Get;
use Ometria\Api\Helper\Config;
class Settings extends \Magento\Framework\App\Action\Action
{
    protected $resultJsonFactory;    
    protected $helperConfig;
   
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		Config $helperConfig
	) {
		parent::__construct($context);
		$this->resultJsonFactory = $resultJsonFactory;
		$this->helperConfig = $helperConfig;
	}
	
    public function execute()
    {
        $values = $this->helperConfig->get();    
        $data   = [Config::CONFIG_TOP=>$values];
		$result = $this->resultJsonFactory->create();		
		return $result->setData($data);
    }    
}