<?php
namespace Ometria\Api\Helper\Service\Filterable\Service;

use Ometria\Core\Helper\Product as ProductHelper;

class Product extends \Ometria\Api\Helper\Service\Filterable\Service
{
    protected $urlModel;
    protected $storeUrlHelper;
    protected $dataObjectProcessor;
    protected $helperOmetriaApiFilter;
    protected $searchCriteria;

    /** @var ProductHelper */
    private $productHelper;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @param \Ometria\Api\Helper\Filter\V1\Service $helperOmetriaApiFilter
     * @param \Magento\Framework\Reflection\DataObjectProcessor $dataObjectProcessor
     * @param \Magento\Catalog\Model\Product\Url $urlModel
     * @param \Ometria\Api\Helper\StoreUrl $storeUrlHelper
     * @param ProductHelper $productHelper
     */
    public function __construct(
		\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria,
		\Ometria\Api\Helper\Filter\V1\Service $helperOmetriaApiFilter,
		\Magento\Framework\Reflection\DataObjectProcessor $dataObjectProcessor ,
		\Magento\Catalog\Model\Product\Url $urlModel,
        \Ometria\Api\Helper\StoreUrl $storeUrlHelper,
        ProductHelper $productHelper
    ) {
        $this->urlModel       = $urlModel;
        $this->storeUrlHelper = $storeUrlHelper;
        $this->productHelper  = $productHelper;
        return parent::__construct($searchCriteria, $helperOmetriaApiFilter, $dataObjectProcessor);
    }

    public function processList($list, $serialize_as, $imageId = 'image')
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

            $new['parent_id'] = $item->getParentId();
            $new['url'] = $item->getProductUrl();
            $new['category_ids'] = $item->getCategoryIds();
            $new['store_ids'] = $item->getStoreIds();
            $new['image_url'] = $this->productHelper->getProductImageUrl($item, $imageId);
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
