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

    /**
     * @param $repository
     * @param $serializeAs
     * @return array
     */
    public function createResponse($repository, $serializeAs)
    {
        $searchCriteria = $this->helperOmetriaApiFilter
            ->applyFilertsToSearchCriteria($this->searchCriteria);

        $list = $repository->getList($searchCriteria);

        $items = [];

        foreach($list->getItems() as $item) {
            if ($serializeAs) {
                $new = $this->dataObjectProcessor->buildOutputDataArray(
                    $item,
                    $serializeAs
                );
            } else if(is_callable([$item, 'getData'])) {
                $new = $item->getData();
            } else {
                $new = $item;
            }

            $items[] = $new;
        }

        return $items;
    }
}
