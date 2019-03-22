<?php
namespace Ometria\Api\Helper\Filter\V1;
class Service
{
    const PARAM_PAGE_SIZE = 'page_size';
    const PARAM_CURRENT_PAGE = 'current_page';
    
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
    
    protected function applyFilertsToSearchCriteriaEntityId($searchCriteria)
    {
        $groups        = [];    
        $fullAction = $this->request->getFullActionName();
        $ids = $this->request->getParam('ids');
            
        if($ids && $fullAction !== 'ometria_api_v1_orders')
        {
            $ids = is_array($ids) ? $ids : [$ids];        
            $group_ids = $this->createSingleFilterFilterGroup('entity_id', $ids, 'in');
            $groups[]      = $group_ids;
        }

        if($ids && $fullAction === 'ometria_api_v1_orders')
        {
            $ids = is_array($ids) ? $ids : [$ids];        
            $group_ids = $this->createSingleFilterFilterGroup('increment_id', $ids, 'in');
            $groups[]      = $group_ids;
        }    
        return $groups;
    }
    
    public function applyFilertsToSearchCriteria($searchCriteria)
    {
        $groups = [];
        $groups = array_merge($groups, $this->applyFilertsToSearchCriteriaEntityId($searchCriteria));
        $website_ids = $this->request->getParam('website_ids');
        if($website_ids) {
            $website_ids = is_array($website_ids) ? $website_ids : [$website_ids];
            $groups[] = $this->createSingleFilterFilterGroup(
                'website_ids',
                $website_ids,
                'in'
            );
        }

        //store ids
        $store_ids = $this->request->getParam('stores');
        if($store_ids) {
            $store_ids = is_array($store_ids) ? $store_ids : [$store_ids];
            $groups[] = $this->createSingleFilterFilterGroup(
                'store_id',
                $store_ids,
                'in'
            );
        }
        $created_since = $this->request->getParam('created_since');
        if($created_since) {
            $created_since = date('Y-m-d H:i:s', strToTime($created_since));
            $groups[] = $this->createSingleFilterFilterGroup(
                'created_at',
                $created_since,
                'gt'
            );
        }
        $created_before = $this->request->getParam('created_before');
        if($created_before) {
            $created_before = date('Y-m-d H:i:s', strToTime($created_before));
            $groups[] = $this->createSingleFilterFilterGroup(
                'created_at',
                $created_before,
                'lt'
            );
        }
        $updated_since = $this->request->getParam('updated_since');
        if($updated_since) {
            $updated_since = date('Y-m-d H:i:s', strToTime($updated_since));
            $groups[] = $this->createSingleFilterFilterGroup(
                'updated_at',
                $updated_since,
                'gt'
            );
        }
        $updated_before = $this->request->getParam('updated_before');
        if($updated_before) {
            $updated_before = date('Y-m-d H:i:s', strToTime($updated_before));
            $groups[] = $this->createSingleFilterFilterGroup(
                'updated_at',
                $updated_before,
                'lt'
            );
        }

        //product_type
        $product_type = $this->request->getParam('product_type');
        if($product_type === 'parent') {
            $groups[] = $this->createSingleFilterFilterGroup(
                'visibility', 
                [   \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG,
                    \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH], 
                'in');
        }
        if($product_type === 'variant') {
            $groups[] = $this->createSingleFilterFilterGroup(
                'visibility', 
                [\Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE], 
                'in');            
        }

        $rule_id = $this->request->getParam('rule_id');
        if($rule_id) {
            $group_rule_id = $this->createSingleFilterFilterGroup(
                'rule_id', $rule_id, 'eq');
            $groups[]      = $group_rule_id;         
        }
                                              
        $page_size = (int) $this->request->getParam(self::PARAM_PAGE_SIZE);
        $page_size = $page_size ? $page_size : 100;                
        
        $current_page = (int) $this->request->getParam(self::PARAM_CURRENT_PAGE);
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