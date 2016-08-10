<?php
namespace Ometria\Api\Controller\V1;
use Ometria\Api\Helper\Format\V1\Version as Helper;

use \Ometria\Api\Controller\V1\Base;
class Version extends Base
{
    protected $resultJsonFactory;
    protected $moduleResource;
    const VERSION_STRING = 'Ometria_Magento2_Extension';
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Magento\Framework\Module\ResourceInterface $moduleResource 
	) {
		parent::__construct($context);
		$this->resultJsonFactory = $resultJsonFactory;
		$this->moduleResource     = $moduleResource;
	}
	
    public function execute()
    {
        $data = Helper::getBlankArray();
		$result = $this->resultJsonFactory->create();
		$data['version'] = self::VERSION_STRING . '/' . $this->moduleResource->getDbVersion('Ometria_Api');
		return $result->setData($data);
    }    
}