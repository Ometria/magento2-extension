<?php
namespace Ometria\Core\Plugin;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Ometria\Core\Service\Product\Inventory as InventoryService;
use Ometria\Core\Service\PushApi as PushApiService;

class OrderManagementPlugin
{
    /** @var InventoryService */
    private $inventoryService;

    /** @var PushApiService */
    private $pushApiService;

    /**
     * @param InventoryService $inventoryService
     * @param PushApiService $pushApiService
     */
    public function __construct(
        InventoryService $inventoryService,
        PushApiService $pushApiService
    ) {
        $this->inventoryService = $inventoryService;
        $this->pushApiService = $pushApiService;
    }

    /**
     * Plugin to trigger webhook to Ometria where stock value reaches 0 for
     * an MSI sales channel after an order is placed.
     *
     * @param OrderManagementInterface $subject
     * @param $result
     */
    public function afterPlace(OrderManagementInterface $subject, $result, OrderInterface $order)
    {
        foreach ($order->getItems() as $orderItem) {
            // Get current salabale quantity (before order placement)
            $salableQty = $this->inventoryService->getSalableQuantity($orderItem->getProduct());

            // Calculate new salabale quantity (after order placement)
            $salableQtyAfterOrder = $salableQty - $orderItem->getQtyOrdered();
            if ($salableQtyAfterOrder <= 0) {
                $this->pushApiService->pushRequest(
                    $this->inventoryService->getPushApiStockData(
                        (int) $orderItem->getProductId(),
                        false
                    )
                );
            }
        }

        return $result;
    }
}
