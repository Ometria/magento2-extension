<?php
namespace Ometria\Core\Model\Config\Source;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Option\ArrayInterface;

class PreferredProduct implements ArrayInterface
{
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var ProductAttributeRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ProductAttributeRepositoryInterface $attributeRepository
     */
    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductAttributeRepositoryInterface $attributeRepository
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $optionArray = ['label' => '-- Not Set --', 'value' => ''];

        foreach ($this->getViableAttributes() as $viableAttribute) {
            $optionArray[] = [
                'value' => $viableAttribute->getAttributeCode(),
                'label' => $viableAttribute->getDefaultFrontendLabel()
            ];
        }

        return $optionArray;
    }

    /**
     * @return ProductAttributeInterface[]
     */
    private function getViableAttributes()
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();

        $attributeRepository = $this->attributeRepository->getList(
            $searchCriteria
        );

        return $attributeRepository->getItems();
    }
}
