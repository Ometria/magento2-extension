<?php
namespace Ometria\Api\Model\ResourceModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

class Product extends AbstractDb
{
    /** @var MetadataPool */
    private $metadataPool;

    /**
     * @param Context $context
     * @param MetadataPool $metadataPool
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        MetadataPool $metadataPool,
        $connectionName = null
    ) {
        $this->metadataPool = $metadataPool;

        parent::__construct($context, $connectionName);
    }

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
        $metadata = $this->metadataPool->getMetadata(ProductInterface::class);
        $select = $connection->select()
            ->from(
                ['link_table' => $connection->getTableName('catalog_product_super_link')],
                ['link_id', 'product_id']
            )->join(
                ['e' => $this->metadataPool->getMetadata(ProductInterface::class)->getEntityTable()],
                'e.' . $metadata->getLinkField() . ' = link_table.parent_id',
                ['e.entity_id']
            )->where(
                'link_table.product_id IN(?)',
                $childIds
            )
            ->order(
                'link_id ASC'
            );

        $result = $connection->fetchAll($select);
        foreach ($result as $_row) {
            $childToParentIds[$_row['product_id']] = $_row['entity_id'];
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
        $metadata = $this->metadataPool->getMetadata(ProductInterface::class);
        $select = $connection->select()
            ->from(
                ['link_table' => $connection->getTableName('catalog_product_bundle_selection')],
                ['selection_id', 'product_id']
            )->join(
                ['e' => $this->metadataPool->getMetadata(ProductInterface::class)->getEntityTable()],
                'e.' . $metadata->getLinkField() . ' = link_table.parent_product_id',
                ['e.entity_id']
            )->where(
                'link_table.product_id IN(?)',
                $childIds
            )->order(
                'selection_id ASC'
            );

        $result = $connection->fetchAll($select);
        foreach ($result as $_row) {
            $childToParentIds[$_row['product_id']] = $_row['entity_id'];
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
        $metadata = $this->metadataPool->getMetadata(ProductInterface::class);
        $select = $connection->select()
            ->from(
                ['link_table' => $connection->getTableName('catalog_product_link')],
                ['link_id', 'linked_product_id']
            )->join(
                ['e' => $this->metadataPool->getMetadata(ProductInterface::class)->getEntityTable()],
                'e.' . $metadata->getLinkField() . ' = link_table.product_id',
                ['e.entity_id']
            )->where(
                'link_type_id = ?',
                \Magento\GroupedProduct\Model\ResourceModel\Product\Link::LINK_TYPE_GROUPED
            )
            ->where(
                'link_table.linked_product_id IN(?)',
                $childIds
            )->order(
                'link_id ASC'
            );

        $result = $connection->fetchAll($select);

        foreach ($result as $_row) {
            $childToParentIds[$_row['linked_product_id']] = $_row['entity_id'];
        }

        return $childToParentIds;
    }
}
