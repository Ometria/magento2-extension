<?php
namespace Ometria\Api\Helper\Service\Filterable\Service;

class Product extends \Ometria\Api\Helper\Service\Filterable\Service
{
    protected $urlModel;
    protected $storeUrlHelper;
    public function __construct(
		\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria,
		\Ometria\Api\Helper\Filter\V1\Service $helperOmetriaApiFilter,
		\Magento\Framework\Reflection\DataObjectProcessor $dataObjectProcessor ,
		\Magento\Catalog\Model\Product\Url $urlModel,
        \Ometria\Api\Helper\StoreUrl $storeUrlHelper	  
    )
    {
        $this->urlModel         = $urlModel;
        $this->storeUrlHelper   = $storeUrlHelper;
        return parent::__construct($searchCriteria, $helperOmetriaApiFilter, $dataObjectProcessor);
    }
    
    public function processList($list, $serialize_as)
    {
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
            else
            {
                $new = $item->getData();
            }
            
            $new['url'] = $item->getProductUrl();            
            $new['category_ids'] = $item->getCategoryIds();
            $new['store_ids'] = $item->getStoreIds();
            $this->storeUrlHelper->saveAllStoreUrls($item);                        
            $items[] = $new;
        }
        
        return $items;     
    }
    
    public function createResponse($repository, $serialize_as)
    {
        $searchCriteria = $this->helperOmetriaApiFilter
            ->applyFilertsToSearchCriteria($this->searchCriteria);
            
        $list = $repository->getList($searchCriteria, $serialize_as);
        
        return $this->processList($list, $serialize_as);   
    }
}