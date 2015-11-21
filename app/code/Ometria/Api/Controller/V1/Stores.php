<?php
namespace Ometria\Api\Controller\V1;
class Stores extends \Magento\Framework\App\Action\Action
{
    protected $resultJsonFactory;
    protected $storeFactory;
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Magento\Store\Model\StoreFactory $storeFactory
	) {
		parent::__construct($context);
		$this->resultJsonFactory = $resultJsonFactory;
		$this->storeFactory      = $storeFactory;
	}
	
    public function execute()
    {
        $stores = $this->storeFactory->create()->getCollection()->getItems();
        $stores = array_map(function($item){
            return $item->getData();
        }, $stores);
        sort($stores);
        
		$result = $this->resultJsonFactory->create();
		return $result->setData($stores);
    }    
}