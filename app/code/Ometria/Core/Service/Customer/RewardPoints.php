<?php
namespace Ometria\Core\Service\Customer;

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
use Magento\Reward\Model\ResourceModel\Reward\CollectionFactory as RewardCollectionFactory;
use Magento\Reward\Model\ResourceModel\Reward\Collection as RewardCollection;

/**
 * Customer Reward Points service class used to interface with Magento Commerce customer reward points
 *
 * Note the ObjectManager use here is to allow compatibility with Magento Open Source where Reward Points
 * is not included in the core code.
 */
class RewardPoints
{
    /** @var ModuleManager */
    private $moduleManager;

    /**
     * @param ModuleManager $moduleManager
     */
    public function __construct(
        ModuleManager $moduleManager
    ) {
        $this->moduleManager = $moduleManager;
    }

    /**
     * @return bool
     */
    public function isRewardsAvailable()
    {
        return $this->moduleManager->isEnabled('Magento_Reward');
    }

    /**
     * @return RewardCollection
     */
    public function getRewardPointsCollection()
    {
        /** @var RewardCollection $rewardCollection */
        $rewardCollection = ObjectManager::getInstance()->get(RewardCollectionFactory::class)->create();
        return $rewardCollection;
    }
}
