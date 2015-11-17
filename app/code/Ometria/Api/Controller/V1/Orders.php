<?php
namespace Ometria\Api\Controller\V1;

use Ometria\Api\Helper\Format\V1\Orders as Helper;

class Orders extends \Magento\Framework\App\Action\Action
{
    protected $resultJsonFactory;
    
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
	) {
		parent::__construct($context);
		$this->resultJsonFactory = $resultJsonFactory;
	}
	
    public function execute()
    {
		$result = $this->resultJsonFactory->create();
		$data   = Helper::getBlankArray();
		return $result->setData([$data]);
		// return $result->setData(['success' => true]);
    }    
}