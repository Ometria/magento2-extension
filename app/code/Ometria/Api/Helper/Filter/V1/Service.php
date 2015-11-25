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
        \Magento\Framework\Api\Search\FilterGroupBuilder $filterGroupBuilder,
        \Magento\Framework\App\RequestInterface $request
    )
    {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder         = $filterBuilder;
        $this->filterGroupBuilder    = $filterGroupBuilder;
        $this->request               = $request;
    }
    
    public function applyFilertsToSearchCriteria($searchCriteria)
    {
        $groups        = [];

        //entity ids
        $ids = $this->request->getParam('ids');
        if($ids)
        {
            $ids = is_array($ids) ? $ids : [$ids];        
            $group_ids = $this->createSingleFilterFilterGroup('entity_id', $ids, 'in');
            $groups[]      = $group_ids;
        }

        //store ids
        $store_ids = $this->request->getParam('stores');
        if($store_ids)
        {
            $store_ids = is_array($store_ids) ? $store_ids : [$store_ids];        
            $group_stores = $this->createSingleFilterFilterGroup('store_id', $store_ids, 'in');
            $groups[]  = $group_stores;
        }
                                
        //updated_since
        $updated_since = $this->request->getParam('updated_since');
        if($updated_since)
        {
            $updated_since = date('Y-m-d H:i:s',strToTime($updated_since));
            $group_updated = $this->createSingleFilterFilterGroup('updated_at', $updated_since, 'gt');
            $groups[]      = $group_updated;
        }
        
        //created_since
        $created_since = $this->request->getParam('created_since');
        if($created_since)
        {
            $updated_since = date('Y-m-d H:i:s',strToTime($created_since));
            $group_created = $this->createSingleFilterFilterGroup('created_at', $created_since, 'gt');
            $groups[]      = $group_created;        
        }
                 
        //product_type
        $product_type = $this->request->getParam('product_type');
        if($product_type === 'parent')
        {
            $group_configurable = $this->createSingleFilterFilterGroup('type_id', 'configurable', 'eq');        
            $groups[]      = $group_configurable;  
        }
        if($product_type === 'variant')
        {
            $group_simple = $this->createSingleFilterFilterGroup('type_id', 'simple', 'eq');        
            $groups[]      = $group_simple;          
        }
                                      
        $page_size = (int) $this->request->getParam('page_size');
        $page_size = $page_size ? $page_size : 100;                
        
        $current_page = (int) $this->request->getParam('current_page');
        $current_page = $current_page ? $current_page : 1;
        
        return $this->searchCriteriaBuilder
            ->setFilterGroups($groups)
            ->setPageSize($page_size)
            ->setCurrentPage($current_page)
            ->create();
    }
    
    protected function createSingleFilterFilterGroup($field, $value, $conditionType = 'eq')
    {
        return $this->filterGroupBuilder
            ->addFilter($this->createFilter($field,$value,$conditionType))
            ->create();    
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