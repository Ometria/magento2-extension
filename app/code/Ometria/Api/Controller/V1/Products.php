<?php
namespace Ometria\Api\Controller\V1;
class Products extends \Magento\Framework\App\Action\Action
{
    protected $resultJsonFactory;
    protected $apiHelperServiceFilterable;
    protected $productRepository;
    
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Ometria\Api\Helper\Service\Filterable\Service $apiHelperServiceFilterable,
		\Magento\Catalog\Api\ProductRepositoryInterface $productRepository
	) {
		parent::__construct($context);
		$this->resultJsonFactory          = $resultJsonFactory;
		$this->apiHelperServiceFilterable = $apiHelperServiceFilterable;
		$this->productRepository          = $productRepository;
	}
	
    public function execute()
    {        
        $items = $this->apiHelperServiceFilterable->createResponse($this->productRepository, 'Magento\Catalog\Api\Data\ProductInterface');
		$result = $this->resultJsonFactory->create();
		return $result->setData($items);
    }    
}