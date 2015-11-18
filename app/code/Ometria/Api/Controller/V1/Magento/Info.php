<?php
namespace Ometria\Api\Controller\V1\Magento;
use Ometria\Api\Helper\Format\V1\Magento\Info as Helper;

class Info extends \Magento\Framework\App\Action\Action
{
    protected $resultJsonFactory;
    protected $scopeConfig;
    protected $app;
    
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	) {
		parent::__construct($context);
		$this->resultJsonFactory = $resultJsonFactory;
		$this->scopeConfig  = $scopeConfig;
	}
	
    public function execute()
    {
        $data = Helper::getBlankArray();
		$result = $this->resultJsonFactory->create();
		
		$data['version_magento']  = \Magento\Framework\AppInterface::VERSION;
		$data['version_php']      = phpversion();
		$data['timezone_php']     = date_default_timezone_get();
		$data['timezone_magento'] = $this->scopeConfig->getValue('general/locale/timezone');
		return $result->setData($data);
    }    
}