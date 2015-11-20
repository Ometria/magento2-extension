<?php
namespace Ometria\Api\Helper\Filter\V1;
class Service
{
    protected $searchCriteriaBuilder;
    protected $filterBuilder;
    protected $filterGroupBuilder;
    
    public function __construct(
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Framework\Api\Search\FilterGroupBuilder $filterGroupBuilder 
    )
    {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder         = $filterBuilder;
        $this->filterGroupBuilder    = $filterGroupBuilder;
    }
    
    public function applyFilertsToSearchCriteria($searchCriteria)
    {
        //should apply as an AND -- but is applying an OR
        $filter_group = $this->filterGroupBuilder
            ->addFilter($this->createFilter('price','34'))
            ->addFilter($this->createFilter('sku','24-MB01'))
            ->create();
            
        return $this->searchCriteriaBuilder
            ->setFilterGroups([$filter_group])
            ->setPageSize(100)
            ->create();
            
//         return $this->searchCriteriaBuilder
//             ->addFilters([
//                 $this->createFilter('sku','24-MB01'),
//                 $this->createFilter('created_at','2015-10-19','<')
//             ])->setPageSize(100)->create();            
        // return $searchCriteria;
    }
    
    /**
    * Targeting public beta -- no addFilter on searchCriteriaBuilder
    */    
    protected function createFilter($field, $value, $conditionType = 'eq')
    {
        return  $this->filterBuilder->setField($field)
                ->setValue($value)
                ->setConditionType($conditionType)
                ->create();
    }
}