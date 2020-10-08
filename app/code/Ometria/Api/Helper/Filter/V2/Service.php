<?php
namespace Ometria\Api\Helper\Filter\V2;

use Magento\Framework\App\RequestInterface;
use Magento\Eav\Model\Entity\Collection\AbstractCollection;
use Magento\Catalog\Model\Product\Visibility;

class Service
{
    const PARAM_ENTITY_IDS = 'ids';
    const PARAM_WEBSITE_IDS = 'website_ids';
    const PARAM_STORES = 'stores';
    const PARAM_CREATED_SINCE = 'created_since';
    const PARAM_CREATED_BEFORE = 'created_before';
    const PARAM_UPDATED_SINCE = 'updated_since';
    const PARAM_UPDATED_BEFORE = 'updated_before';
    const PARAM_PRODUCT_TYPE = 'product_type';
    const PARAM_PAGE_SIZE = 'page_size';
    const PARAM_CURRENT_PAGE = 'current_page';
    const PARAM_PRODUCT_STORE = 'product_store';
    const PARAM_COUNT = 'count';
    const PARAM_PRODUCT_LISTING = 'listing';
    const PARAM_PRODUCT_IMAGE = 'product_image';
    const PARAM_PRODUCT_DEFAULT_IMAGE_ID = 'image';
    const DEFAULT_PAGE_SIZE = 100;
    const DEFAULT_PAGE = 1;

    /** @var RequestInterface */
    private $request;

    /**
     * @param RequestInterface $request
     */
    public function __construct(
        RequestInterface $request
    ) {
        $this->request = $request;
    }

    /**
     * @param AbstractCollection $collection
     */
    public function applyFiltersToCollection(AbstractCollection $collection)
    {
        // Set page size
        $pageSize = $this->request->getParam(self::PARAM_PAGE_SIZE, self::DEFAULT_PAGE_SIZE);
        $collection->setPageSize($pageSize);

        // Set current page
        $currentPage = $this->request->getParam(self::PARAM_CURRENT_PAGE, self::DEFAULT_PAGE);
        $collection->setCurPage($currentPage);

        if ($entityIds = $this->request->getParam(self::PARAM_ENTITY_IDS)) {
            $entityIds = is_array($entityIds) ? $entityIds : [$entityIds];
            $collection->addFieldToFilter(
                'entity_id',
                $entityIds
            );
        }

        if ($websiteIds = $this->request->getParam(self::PARAM_WEBSITE_IDS)) {
            $collection->addWebsiteFilter($websiteIds);
        }

        if ($storeIds = $this->request->getParam(self::PARAM_STORES)) {
            $storeIds = is_array($storeIds) ? $storeIds : [$storeIds];
            foreach ($storeIds as $storeId) {
                $collection->addStoreFilter($storeId);
            }
        }

        if ($createdSince = $this->request->getParam(self::PARAM_CREATED_SINCE)) {
            $createdSince = date('Y-m-d H:i:s', strToTime($createdSince));
            $collection->addFieldToFilter(
                'created_at',
                ['gt' => $createdSince]
            );
        }

        if ($createdBefore = $this->request->getParam(self::PARAM_CREATED_BEFORE)) {
            $createdBefore = date('Y-m-d H:i:s', strToTime($createdBefore));
            $collection->addFieldToFilter(
                'created_at',
                ['lt' => $createdBefore]
            );
        }

        if ($updatedSince = $this->request->getParam(self::PARAM_UPDATED_SINCE)) {
            $updatedSince = date('Y-m-d H:i:s', strToTime($updatedSince));
            $collection->addFieldToFilter(
                'updated_at',
                ['gt' => $updatedSince]
            );
        }

        if ($updatedBefore = $this->request->getParam(self::PARAM_UPDATED_BEFORE)) {
            $updatedBefore = date('Y-m-d H:i:s', strToTime($updatedBefore));
            $collection->addFieldToFilter(
                'updated_at',
                ['lt' => $updatedBefore]
            );
        }

        if ($this->request->getParam(self::PARAM_PRODUCT_TYPE) == 'parent') {
            $collection->addFieldToFilter(
                'visibility',
                [
                    'in' => [
                        Visibility::VISIBILITY_IN_CATALOG,
                        Visibility::VISIBILITY_BOTH
                    ]
                ]
            );
        }

        if ($this->request->getParam(self::PARAM_PRODUCT_TYPE) == 'variant') {
            $collection->addFieldToFilter(
                'visibility',
                [
                    'in' => [Visibility::VISIBILITY_NOT_VISIBLE]
                ]
            );
        }
    }
}
