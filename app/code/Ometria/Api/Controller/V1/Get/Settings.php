<?php
namespace Ometria\Api\Controller\V1\Get;
class Settings extends \Magento\Framework\App\Action\Action
{
    protected $resultJsonFactory;
    protected $scopeConfig;
    
    const CONFIG_TOP = 'ometria';
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	) {
		parent::__construct($context);
		$this->resultJsonFactory = $resultJsonFactory;
		$this->scopeConfig = $scopeConfig;
	}
	
    public function execute()
    {
        $values = $this->scopeConfig->getValue(self::CONFIG_TOP);    
        $data   = [self::CONFIG_TOP=>$values];
		$result = $this->resultJsonFactory->create();		
		return $result->setData($data);
    }    
}