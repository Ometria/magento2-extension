<?php
namespace Ometria\Api\Controller\V1;
class Customers extends \Magento\Framework\App\Action\Action
{
    protected $resultJsonFactory;
    protected $repository;
    
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Ometria\Api\Helper\Service\Filterable\Service $apiHelperServiceFilterable,
		\Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
	) {
		parent::__construct($context);
		$this->resultJsonFactory = $resultJsonFactory;
		$this->apiHelperServiceFilterable = $apiHelperServiceFilterable;
		$this->repository          = $customerRepository;
		
	}
	
    public function execute()
    {
        $items = $this->apiHelperServiceFilterable->createResponse(
            $this->repository,             
            '\Magento\Customer\Api\Data\CustomerInterface'
        );
		$result = $this->resultJsonFactory->create();
		return $result->setData($items);
    }    
}