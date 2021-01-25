<?php
namespace Ometria\Api\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Product extends AbstractDb
{
    protected function _construct()
    {
    }

    /**
     * Bulk version of the native method to retrieve relationships one by one.
     * @see \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable::getParentIdsByChild
     *
     * @param array $childIds
     * @return array
     */
    public function getConfigurableProductParentIds(array $childIds)
    {
        $childToParentIds = [];

        $connection = $this->getConnection();

        $select = $connection->select()
            ->from(
                $this->getConnection()->getTableName('catalog_product_super_link'),
                ['product_id', 'parent_id']
            )
            ->where(
                'product_id IN (?)',
                $childIds
            )
            // order by the oldest links first so the iterator will end with the most recent link
            ->order('link_id ASC');

        $result = $connection->fetchAll($select);
        foreach ($result as $_row) {
            $childToParentIds[$_row['product_id']] = $_row['parent_id'];
        }

        return $childToParentIds;
    }

    /**
     * Bulk version of the native method to retrieve relationships one by one.
     * @see \Magento\Bundle\Model\ResourceModel\Selection::getParentIdsByChild
     *
     * @param array $childIds
     * @return array
     */
    public function getBundleProductParentIds(array $childIds)
    {
        $childToParentIds = [];

        $connection = $this->getConnection();

        $select = $connection->select()
            ->from(
                $this->getConnection()->getTableName('catalog_product_bundle_selection'),
                ['parent_product_id', 'product_id']
            )
            ->where(
                'product_id IN (?)',
                $childIds
            )
            // order by the oldest selections first so the iterator will end with the most recent link
            ->order('selection_id ASC');

        $result = $connection->fetchAll($select);
        foreach ($result as $_row) {
            $childToParentIds[$_row['product_id']] = $_row['parent_product_id'];
        }

        return $childToParentIds;
    }

    /**
     * Bulk version of the native method to retrieve relationships one by one.
     * @see \Magento\GroupedProduct\Model\ResourceModel\Product\Link::getParentIdsByChild
     *
     * @param array $childIds
     * @return array
     */
    public function getGroupedProductParentIds(array $childIds)
    {
        $childToParentIds = [];

        $connection = $this->getConnection();

        $select = $connection->select()
            ->from(
                $this->getConnection()->getTableName('catalog_product_link'),
                ['product_id', 'linked_product_id']
            )
            ->where(
                'linked_product_id IN (?)',
                $childIds
            )
            ->where(
                'link_type_id = ?',
                \Magento\GroupedProduct\Model\ResourceModel\Product\Link::LINK_TYPE_GROUPED
            )
            // order by the oldest links first so the iterator will end with the most recent link
            ->order('link_id ASC');

        $result = $connection->fetchAll($select);
        foreach ($result as $_row) {
            $childToParentIds[$_row['linked_product_id']] = $_row['product_id'];
        }

        return $childToParentIds;
    }
}
