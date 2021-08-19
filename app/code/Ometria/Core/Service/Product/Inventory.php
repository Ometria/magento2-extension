<?php
namespace Ometria\Core\Service\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\IsProductSalableInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Inventory service class used to interface with Magento product inventory
 *
 * Note the ObjectManager use here is to allow backwards compatibility between legacy and MSI inventory
 * models which may or may not be present in the merchant's codebase.
 */
class Inventory
{
    /** @var ModuleManager */
    private $moduleManager;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var int */
    private $stockId;

    /**
     * @param ModuleManager $moduleManager
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ModuleManager $moduleManager,
        StoreManagerInterface $storeManager
    ) {
        $this->moduleManager = $moduleManager;
        $this->storeManager = $storeManager;
    }

    /**
     * @return bool
     */
    public function isMSIAvailable()
    {
        return $this->moduleManager->isEnabled('Magento_Inventory');
    }

    /**
     * @param ProductInterface $product
     * @return bool
     */
    public function getStockStatus(ProductInterface $product)
    {
        if ($this->isMSIAvailable()) {
            return $this->getMSIStockStatus($product);
        }

        return $this->getLegacyStockStatus($product);
    }

    /**
     * @param ProductInterface $product
     * @return float
     */
    public function getSalableQuantity(ProductInterface $product)
    {
        if ($this->isMSIAvailable()) {
            return $this->getMSISalableQuantity($product);
        }

        return $this->getLegacySalableQuantity($product);
    }

    /**
     * @param int $id
     * @param bool $isInStock
     * @return array
     */
    public function getPushApiStockData(int $id, bool $isInStock)
    {
        return [
            [
                "@type" => "product",
                "id" => $id,
                "is_in_stock" => $isInStock,
                "@merge" => true
            ]
        ];
    }

    /**
     * @param ProductCollection $collection
     */
    public function addLegacyStockFilterToCollection(ProductCollection $collection)
    {
        if (!$this->isMSIAvailable()) {
            /** @var StockHelper $stockHelper */
            $stockHelper = ObjectManager::getInstance()->get(StockHelper::class);
            $stockHelper->addIsInStockFilterToCollection($collection);
        }
    }

    /**
     * @param ProductInterface $product
     * @param $stockId
     * @return bool
     */
    private function getMSIStockStatus(ProductInterface $product)
    {
        /** @var IsProductSalableInterface $isProductSalable */
        $isProductSalable = ObjectManager::getInstance()->get(IsProductSalableInterface::class);
        return $isProductSalable->execute($product->getSku(), $this->getMSIStockId());
    }

    /**
     * @param ProductInterface $product
     * @param $stockId
     * @return float
     */
    private function getMSISalableQuantity(ProductInterface $product)
    {
        /** @var GetProductSalableQtyInterface $getProductSalableQty */
        $getProductSalableQty = ObjectManager::getInstance()->get(GetProductSalableQtyInterface::class);

        try {
            $qty = $getProductSalableQty->execute($product->getSku(), $this->getMSIStockId());
        } catch (\Exception $e) {
            $qty = 0.;
        }

        return $qty;
    }

    /**
     * @return int|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getMSIStockId()
    {
        if (!$this->stockId) {
            $websiteCode = $this->storeManager->getWebsite()->getCode();

            /** @var StockResolverInterface $stockResolver */
            $stockResolver = ObjectManager::getInstance()->get(StockResolverInterface::class);

            $this->stockId = $stockResolver->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode)->getStockId();
        }

        return $this->stockId;
    }

    /**
     * Get the legacy stock model is_in_stock value
     *
     * @param ProductInterface $product
     * @return bool
     */
    private function getLegacyStockStatus(ProductInterface $product)
    {
        $stockItem = $this->getStockItem((int) $product->getId());

        return (bool) $stockItem->getIsInStock();
    }

    /**
     * Get the legacy stock model qty value
     *
     * @param ProductInterface $product
     * @return float
     */
    private function getLegacySalableQuantity(ProductInterface $product)
    {
        $stockItem = $this->getStockItem((int) $product->getId());

        return $stockItem->getManageStock() ? $stockItem->getQty() : 0.;
    }

    /**
     * @param int $productId
     * @return StockItemInterface
     */
    private function getStockItem(int $productId)
    {
        /** @var StockRegistryInterface $stockRegistry */
        $stockRegistry = ObjectManager::getInstance()->get(StockRegistryInterface::class);

        return $stockRegistry->getStockItem($productId);
    }
}
