<?php
namespace Ometria\Api\Controller\V1\Magento;
use Ometria\Api\Helper\Format\V1\Magento\Info as Helper;
use ReflectionClass;
use \Ometria\Api\Controller\V1\Base;
class Info extends Base
{
    protected $resultJsonFactory;
    protected $scopeConfig;
    protected $app;
    protected $helperMetadata;
    
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Framework\App\ProductMetadataInterface $helperMetadata
	) {
		parent::__construct($context);
		$this->resultJsonFactory = $resultJsonFactory;
		$this->scopeConfig       = $scopeConfig;
		$this->helperMetadata    = $helperMetadata;
	}
	
	protected function getMagentoVersion()
	{
	    return $this->helperMetadata->getVersion();
	}
	
    public function execute()
    {
        $data = Helper::getBlankArray();
		$result = $this->resultJsonFactory->create();
		
		$data['version_magento']  = $this->getMagentoVersion();
		$data['version_php']      = phpversion();
		$data['timezone_php']     = date_default_timezone_get();
		$data['timezone_magento'] = $this->scopeConfig->getValue('general/locale/timezone');
		return $result->setData($data);
    }    
}