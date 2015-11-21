<?php
namespace Ometria\Api\Controller\V1;

use Ometria\Api\Helper\Format\V1\Orders as Helper;

class Orders extends \Magento\Framework\App\Action\Action
{
    protected $resultJsonFactory;
    protected $repository;
    
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Ometria\Api\Helper\Service\Filterable\Service $apiHelperServiceFilterable,
		\Magento\Sales\Api\OrderRepositoryInterface $orderRepository
	) {
		parent::__construct($context);
		$this->resultJsonFactory          = $resultJsonFactory;
		$this->apiHelperServiceFilterable = $apiHelperServiceFilterable;
		$this->repository                 = $orderRepository;
	}
	
    public function execute()
    {
        $items = $this->apiHelperServiceFilterable->createResponse(
            $this->repository, 
            null                //actual type triggers Notice: Array to string conversion. A bug?
            //'Magento\Sales\Api\Data\OrderInterface'
        );
		$result = $this->resultJsonFactory->create();
		return $result->setData($items);
		// return $result->setData(['success' => true]);
    }    
}