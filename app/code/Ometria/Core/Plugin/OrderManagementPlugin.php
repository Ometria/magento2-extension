<?php
namespace Ometria\Core\Plugin;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Ometria\Core\Helper\Config;
use Ometria\Core\Model\Config\Source\StockPushScope;
use Ometria\Core\Service\Product\Inventory as InventoryService;
use Ometria\Core\Service\PushApi as PushApiService;

class OrderManagementPlugin
{
    /** @var InventoryService */
    private $inventoryService;

    /** @var PushApiService */
    private $pushApiService;

    /** @var Config */
    private $helperConfig;

    /**
     * @param InventoryService $inventoryService
     * @param PushApiService $pushApiService
     * @param Config $helperConfig
     */
    public function __construct(
        InventoryService $inventoryService,
        PushApiService $pushApiService,
        Config $helperConfig
    ) {
        $this->inventoryService = $inventoryService;
        $this->pushApiService = $pushApiService;
        $this->helperConfig = $helperConfig;
    }

    /**
     * Plugin to trigger webhook to Ometria where stock value reaches 0 for
     * an MSI sales channel after an order is placed.
     *
     * @param OrderManagementInterface $subject
     * @param $result
     * @param OrderInterface $order
     * @return mixed
     */
    public function afterPlace(OrderManagementInterface $subject, $result, OrderInterface $order)
    {
        try {
            $this->sendPushNotifications($order);
        } catch (\Exception $e) {
            // Catch all errors to ensure this does not affect order placement
        }

        return $result;
    }

    /**
     * @param OrderInterface $order
     */
    private function sendPushNotifications(OrderInterface $order)
    {
        $stockPushScope = $this->helperConfig->getStockPushScope();

        if ($stockPushScope == StockPushScope::SCOPE_DISABLED) {
            // Return early if stock push disabled
            return;
        }

        foreach ($order->getItems() as $orderItem) {
            // Retrieve the salable qty of the product based on configured scope and after placing an order
            $salableQty = $this->getSalableQty(
                $orderItem->getProduct(),
                $stockPushScope
            );

            // if salable qty is set to 0, then push the is_in_stock to false (null infers manage stock is disabled)
            if ($salableQty !== null && $salableQty == 0) {
                $stockData = $this->inventoryService->getPushApiStockData(
                    (int)$orderItem->getProductId(),
                    false
                );

                $this->pushApiService->pushRequest($stockData);
            }
        }
    }

    /**
     * @param ProductInterface $product
     * @param int $stockPushScope
     * @return float|null
     */
    private function getSalableQty(ProductInterface $product, int $stockPushScope)
    {
        $salableQty = null;

        if ($stockPushScope == StockPushScope::SCOPE_GLOBAL) {
            // Get current salabale quantity (before order placement)
            $salableQty = $this->inventoryService->getGlobalSalableQuantity($product);
        } else if ($stockPushScope == StockPushScope::SCOPE_CHANNEL) {
            // Get current salabale quantity (before order placement)
            $salableQty = $this->inventoryService->getSalableQuantity($product);
        }

        return $salableQty;
    }
}
