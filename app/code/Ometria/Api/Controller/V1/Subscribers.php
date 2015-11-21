<?php
namespace Ometria\Api\Controller\V1;
class Subscribers extends \Magento\Framework\App\Action\Action
{
    protected $resultJsonFactory;
    protected $subscriberFactory;
    protected $helperOmetriaApiFilter;
    protected $searchCriteria;
    
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Magento\Newsletter\Model\SubscriberFactory $subscriberFactory,
		\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria,
		\Ometria\Api\Helper\Filter\V1\Service $helperOmetriaApiFilter		
	) {
		parent::__construct($context);
		$this->resultJsonFactory        = $resultJsonFactory;
		$this->subscriberFactory        = $subscriberFactory;
		$this->helperOmetriaApiFilter   = $helperOmetriaApiFilter;
		$this->searchCriteria           = $searchCriteria;
	}
	
    public function execute()
    {
        $searchCriteria = $this->helperOmetriaApiFilter
            ->applyFilertsToSearchCriteria($this->searchCriteria);

        $collection = $this->subscriberFactory->create()->getCollection();

        foreach ($searchCriteria->getFilterGroups() as $group) {
            $this->addFilterGroupToCollection($group, $collection);
        }
                
        $items      = array_map(function($item){
            return $item->getData();
        }, $collection->getItems());
        sort($items);
		$result = $this->resultJsonFactory->create();
		return $result->setData($items);
    }  
    
    protected function addFilterGroupToCollection(
        \Magento\Framework\Api\Search\FilterGroup $filterGroup,
        \Magento\Newsletter\Model\Resource\Subscriber\Collection $collection
    ) {
        $fields = [];
        foreach ($filterGroup->getFilters() as $filter) {
            $condition = $filter->getConditionType() ? $filter->getConditionType() : 'eq';
            $fields[] = ['attribute' => $filter->getField(), $condition => $filter->getValue()];
        }
        if(count($fields) > 1)
        {
            throw new \Exception("Can't handle multiple OR filters");
        }
        if ($fields) {
            $attribute = $fields[0]['attribute'];
            unset($fields[0]['attribute']);
            $filter = $fields[0];
            $collection->addFieldToFilter($attribute, $filter);
        }
    }      
}