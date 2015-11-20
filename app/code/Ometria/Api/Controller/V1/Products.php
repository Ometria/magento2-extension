<?php
namespace Ometria\Api\Controller\V1;
class Products extends \Magento\Framework\App\Action\Action
{
    protected $resultJsonFactory;
    protected $productRepository;
    protected $searchCriteria;
    protected $dataObjectProcessor;
    
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
		\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria,
		\Ometria\Api\Helper\Filter\V1\Service $helperOmetriaApiFilter,
		\Magento\Framework\Reflection\DataObjectProcessor $dataObjectProcessor
	) {
		parent::__construct($context);
		$this->resultJsonFactory      = $resultJsonFactory;
		$this->productRepository      = $productRepository;
		$this->searchCriteria         = $searchCriteria;
		$this->helperOmetriaApiFilter = $helperOmetriaApiFilter;
		$this->dataObjectProcessor    = $dataObjectProcessor;
	}
	
    public function execute()
    {
        $searchCriteria = $this->helperOmetriaApiFilter
            ->applyFilertsToSearchCriteria($this->searchCriteria);
            
        $list = $this->productRepository->getList($searchCriteria);

        $items = [];
        foreach($list->getItems() as $item)
        {
            $items[] = $this->dataObjectProcessor->buildOutputDataArray(
                $item,
                'Magento\Catalog\Api\Data\ProductInterface'
            );        
        }
        
		$result = $this->resultJsonFactory->create();
		return $result->setData($items);
    }    
}