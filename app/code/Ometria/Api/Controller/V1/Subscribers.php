<?php
namespace Ometria\Api\Controller\V1;
class Subscribers extends \Magento\Framework\App\Action\Action
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
		return $result->setData(['success' => true]);
    }    
}