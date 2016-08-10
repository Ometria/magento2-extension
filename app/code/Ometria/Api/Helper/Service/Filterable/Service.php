<?php
namespace Ometria\Api\Helper\Service\Filterable;
use ArrayObject;
class Service
{
    protected $searchCriteria;
    protected $dataObjectProcessor;

    public function __construct(
		\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria,
		\Ometria\Api\Helper\Filter\V1\Service $helperOmetriaApiFilter,
		\Magento\Framework\Reflection\DataObjectProcessor $dataObjectProcessor    
    )
    {
		$this->searchCriteria         = $searchCriteria;
		$this->helperOmetriaApiFilter = $helperOmetriaApiFilter;
		$this->dataObjectProcessor    = $dataObjectProcessor;    
    }
    
    public function createResponse($repository, $serialize_as)
    {
        $searchCriteria = $this->helperOmetriaApiFilter
            ->applyFilertsToSearchCriteria($this->searchCriteria);
            
        $list = $repository->getList($searchCriteria);

        $items = [];
        foreach($list->getItems() as $item)
        {
            $new;
            if($serialize_as)
            {
                $new = $this->dataObjectProcessor->buildOutputDataArray(
                    $item,
                    $serialize_as                
                );        
            }            
            else if(is_callable([$item, 'getData']))
            {
                $new = $item->getData();
            }
            else
            {
                $new = $item;
            }
            $items[] = $new;
        }
        
        return $items;    
    }
}