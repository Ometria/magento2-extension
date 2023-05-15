<?php
namespace Ometria\Api\Controller\V1;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json as ResultJson;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order\AddressFactory as OrderAddressFactory;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Item\Collection as OrderItemCollection;
use Magento\Sales\Model\ResourceModel\Order\Payment\CollectionFactory as PaymentCollectionFactory;
use Magento\Weee\Helper\Data as WeeeHelper;
use Ometria\Api\Helper\Format\V1\Orders as Helper;
use Ometria\Api\Helper\Order\IsValid as OrderIsValidHelper;
use Ometria\Api\Helper\Service\Filterable\Service as FilterableService;
use Ometria\Core\Helper\Config;

class Orders extends Base
{
    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var FilterableService */
    private $apiHelperServiceFilterable;

    /** @var OrderRepositoryInterface */
    private $repository;

    /** @var PaymentCollectionFactory */
    private $paymentCollectionFactory;

    /** @var OrderAddressFactory */
    private $orderAddressFactory;

    /** @var OrderCollectionFactory */
    private $orderCollectionFactory;

    /** @var OrderIsValidHelper */
    private $orderValidTester;

    /** @var WeeeHelper */
    private $weeeHelper;

    /** @var Config */
    protected $helperConfig;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param FilterableService $apiHelperServiceFilterable
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentCollectionFactory $paymentCollectionFactory
     * @param OrderAddressFactory $orderAddressFactory
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param OrderIsValidHelper $orderValidTester
     * @param WeeeHelper $weeeHelper
     * @param Config $helperConfig
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        FilterableService $apiHelperServiceFilterable,
        OrderRepositoryInterface $orderRepository,
        PaymentCollectionFactory $paymentCollectionFactory,
        OrderAddressFactory $orderAddressFactory,
        OrderCollectionFactory $orderCollectionFactory,
        OrderIsValidHelper $orderValidTester,
        WeeeHelper $weeeHelper,
        Config $helperConfig
    ) {
        parent::__construct($context);

        $this->resultJsonFactory = $resultJsonFactory;
        $this->apiHelperServiceFilterable = $apiHelperServiceFilterable;
        $this->repository = $orderRepository;
        $this->orderAddressFactory = $orderAddressFactory;
        $this->paymentCollectionFactory = $paymentCollectionFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderValidTester = $orderValidTester;
        $this->weeeHelper = $weeeHelper;
        $this->helperConfig = $helperConfig;
    }

    /**
     * @return ResultJson
     */
    public function execute()
    {
        $items = $this->getOrderItems();

        if ($this->_request->getParam('count') != null) {
            $data = $this->getCountData($items);
        } else {
            $data = $this->getItemsData($items);
        }
        return $this->resultJsonFactory->create()->setData($data);
    }

    /**
     * @return array
     */
    private function getOrderItems()
    {
        $items = $this->apiHelperServiceFilterable->createResponse(
            $this->repository,
            null
        );

        return $items;
    }

    /**
     * @param array $items
     * @return array
     */
    private function getCountData(array $items)
    {
        return [
            'count' => count($items)
        ];
    }

    /**
     * @param array $items
     * @return array
     */
    private function getItemsData(array $items)
    {
        $items = $this->formatItems($items);
        $items = $this->addPayments($items);
        $items = $this->addAddresses($items);
        $items = $this->addLineItems($items);
        $items = $this->replaceIdWithIncrementId($items);
        // Getting Show Logs value 
        $statusLogValue = $this->helperConfig->getLogConfig();
        if ($statusLogValue){
            $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/order-details.log');
            $logger = new \Zend_Log();
            $logger->addWriter($writer);
            $logger->info("Order details -----------------");
            $logger->info(print_r($items, true));
            $logger->info("-------------------------------");
        }
        return $items;
    }

    /**
     * @param array $items
     * @return array
     */
    private function formatItems(array $items)
    {
        foreach ($items as $key => $item) {
            $new = Helper::getBlankArray();
            $new = [
                '@type'               => 'order',
                'channel'             => 'online',
                'store'               => $item['store_id'],
                'id'                  => $item['entity_id'],
                'increment_id'        => $item['increment_id'],
                'status'              => $item['status'],
                'state'               => $item['state'],
                'is_valid'            => $this->orderValidTester->fromItem($item),
                'timestamp'           => $item['created_at'],
                'base_currency'       => $item['base_currency_code'],
                'base_subtotal'       => $item['base_subtotal'],
                'base_discount'       => $item['base_discount_amount'],
                'base_shipping'       => $item['base_shipping_amount'],
                'base_tax'            => $item['base_tax_amount'],
                'base_grand_total'    => $item['base_grand_total'],
                'base_total_refunded' => $item['base_total_refunded'],
                'currency'            => $item['order_currency_code'],
                'subtotal'            => $item['subtotal'],
                'discount'            => $item['discount_amount'],
                'shipping'            => $item['shipping_amount'],
                'tax'                 => $item['tax_amount'],
                'grand_total'         => $item['grand_total'],
                'total_refunded'      => $item['total_refunded'],
                'coupon_code'         => $item['coupon_code'],
                'payment_method'      => null,
                'shipping_method'     => $item['shipping_method'],
                'ip_address'          => $item['remote_ip'],
                'x_forwarded_for'     => $item['x_forwarded_for'],
                'lineitems'           => [],
                'customer'            => [
                    'id'        => $item['customer_id'],
                    'firstname' => $item['customer_firstname'],
                    'lastname'  => $item['customer_lastname'],
                    'email'     => $item['customer_email']
                ],
                'shipping_address'    => [
                    'id'           => $item['shipping_address_id'],
                    'city'         => '',
                    'state'        => '',
                    'postcode'     => '',
                    'country_code' => ''
                ],
                'billing_address'     => [
                    'id'           => $item['billing_address_id'],
                    'city'         => '',
                    'state'        => '',
                    'postcode'     => '',
                    'country_code' => ''
                ]
            ];

            if ($this->_request->getParam('raw') === 'true') {
                $new['_raw'] = $item;
            }

            $items[$key] = $new;
        }
        return $items;
    }

    /**
     * @param array $items
     * @return array
     */
    private function addPayments(array $items)
    {
        $order_ids = array_map(function ($item) {
            return $item['id'];
        }, $items);

        $paymentCollection = $this->paymentCollectionFactory->create()
            ->addFieldToFilter(
                'parent_id',
                [
                    'in' => $order_ids
                ]
            );

        $indexedByParentId = [];

        foreach ($paymentCollection as $payment) {
            $indexedByParentId[(int)$payment->getParentId()] = $payment;
        }

        foreach ($items as $key => $item) {
            if (!array_key_exists((int)$item['id'], $indexedByParentId)) {
                continue;
            }
            $item['payment_method'] = $indexedByParentId[$item['id']]->getMethod();
            $items[$key] = $item;
        }

        return $items;
    }

    /**
     * @param array $items
     * @return array
     */
    private function addAddresses(array $items)
    {
        foreach ($items as $key => $item) {
            $shipping = $this->orderAddressFactory->create()
                ->load($item['shipping_address']['id']);

            $billing = $this->orderAddressFactory->create()
                ->load($item['billing_address']['id']);

            $item['shipping_address']['firstname']    = $shipping->getFirstname();
            $item['shipping_address']['lastname']     = $shipping->getLastname();
            $item['shipping_address']['city']         = $shipping->getCity();
            $item['shipping_address']['state']        = $shipping->getRegion();
            $item['shipping_address']['postcode']     = $shipping->getPostcode();
            $item['shipping_address']['country_code'] = $shipping->getCountryId();
            $item['billing_address']['firstname']     = $billing->getFirstname();
            $item['billing_address']['lastname']      = $billing->getLastname();
            $item['billing_address']['city']          = $billing->getCity();
            $item['billing_address']['state']         = $billing->getRegion();
            $item['billing_address']['postcode']      = $billing->getPostcode();
            $item['billing_address']['country_code']  = $billing->getCountryId();

            unset($item['shipping_address']['id']);
            unset($item['billing_address']['id']);

            $item = $this->fixMissingCustomerNames($item);
            $items[$key] = $item;
        }

        return $items;
    }

    /**
     * @param array $item
     * @return array
     */
    private function fixMissingCustomerNames(array $item)
    {
        if (!$item['customer']['firstname']) {
            $item['customer']['firstname'] = $item['billing_address']['firstname'];
        }

        if (!$item['customer']['lastname']) {
            $item['customer']['lastname'] = $item['billing_address']['lastname'];
        }

        return $item;
    }

    /**
     * @param array $items
     * @return array
     */
    private function addLineItems(array $items)
    {
        $order_ids = array_map(function ($item) {
            return $item['id'];
        }, $items);

        $orderCollection = $this->orderCollectionFactory->create()
            ->addFieldToFilter(
                'entity_id',
                [
                    'in' => $order_ids
                ]
            );

        foreach ($items as $key => $item) {
            $order = $orderCollection->getItemById($item['id']);

            if (!$order) {
                continue;
            }

            /** @var OrderItemCollection $lineItems */
            $lineItems = $order->getItemsCollection();
            $indexedParentChild = $this->indexLineItemsByParentAndChild($lineItems);
            $newLineItems = [];

            foreach ($indexedParentChild as $lineItem) {
                $new = [
                    "product" => [
                        "id"         => $lineItem['parent']['product_id'],
                        "sku"        => $lineItem['parent']['sku'],
                        "title"      => $lineItem['parent']['name'],
                        "base_price" => $lineItem['parent']['base_price'],
                        "price"      => $lineItem['parent']['price']
                    ]
                ];

                $childrenIds = array_map(function ($item) {
                    return $item['product_id'];
                }, $lineItem['children']);

                $childrenSkus = array_map(function ($item) {
                    return $item['sku'];
                }, $lineItem['children']);

                $new["variant_id"]       = implode(',', $childrenIds);
                $new["variant_sku"]      = implode(',', $childrenSkus);
                $new["sku"]              = $lineItem['parent']['sku'];
                $new["quantity"]         = $lineItem['parent']['qty_ordered'];
                $new["base_unit_price"]  = $lineItem['parent']['base_price'];
                $new["base_subtotal"]    = $lineItem['parent']['base_row_total'];
                $new["base_discount"]    = $this->formatLineItemDiscount($lineItem['parent']['base_discount_amount']);
                $new["base_tax"]         = $lineItem['parent']['base_tax_amount'];
                $new["unit_price"]       = $lineItem['parent']['price'];
                $new["subtotal"]         = $lineItem['parent']['row_total'];
                $new["tax"]              = $lineItem['parent']['tax_amount'];
                $new["discount"]         = $this->formatLineItemDiscount($lineItem['parent']['discount_amount']);
                $new["tax_percent"]      = $lineItem['parent']['tax_percent'];
                $new["discount_percent"] = $lineItem['parent']['discount_percent'];

                /** @var OrderItemInterface $orderItem */
                $orderItem = $lineItems->getItemById($lineItem['parent']['item_id']);

                if ($orderItem) {
                    $new["base_total"] = (string)$this->calculateLineItemTotal(
                        $orderItem->getBaseRowTotal(),
                        $orderItem->getBaseDiscountAmount(),
                        $orderItem->getBaseTaxAmount(),
                        $orderItem->getBaseDiscountTaxCompensationAmount(),
                        $this->weeeHelper->getBaseRowWeeeTaxInclTax($orderItem)
                    );

                    $new["total"] = (string)$this->calculateLineItemTotal(
                        $orderItem->getRowTotal(),
                        $orderItem->getDiscountAmount(),
                        $orderItem->getTaxAmount(),
                        $orderItem->getDiscountTaxCompensationAmount(),
                        $this->weeeHelper->getRowWeeeTaxInclTax($orderItem)
                    );
                }

                if ($this->_request->getParam('raw') === 'true') {
                    $new['_raw'] = $lineItem;
                }

                $newLineItems[] = $new;
            }

            $item['lineitems'] = $newLineItems;
            $items[$key] = $item;
        }
        return $items;
    }

    /**
     * @param OrderItemCollection $lineItems
     * @return array
     */
    private function indexLineItemsByParentAndChild(OrderItemCollection $lineItems)
    {
        $indexedParentChild = [];
        foreach ($lineItems as $lineItem) {
            if (!$lineItem->getParentItemId()) {
                $indexedParentChild[$lineItem->getId()] = [
                    'parent' => $lineItem->getData(),
                    'children' => []
                ];
                continue;
            }
            $indexedParentChild[$lineItem->getParentItemId()]['children'][] = $lineItem->getData();
        }

        return $indexedParentChild;
    }

    /**
     * Format the line item discount to only show negative when needed
     *
     * @param string $amount
     * @return string
     */
    private function formatLineItemDiscount(string $amount)
    {
        return (float)$amount > 0 ? sprintf('-%s', $amount) : $amount;
    }

    /**
     * @param $rowTotal
     * @param $discountAmount
     * @param $taxAmount
     * @param $discountTax
     * @param $weeeTax
     * @return float
     */
    private function calculateLineItemTotal($rowTotal, $discountAmount, $taxAmount, $discountTax, $weeeTax)
    {
        $totalAmount = $rowTotal
            - $discountAmount
            + $taxAmount
            + $discountTax
            + $weeeTax;

        return (float)$totalAmount;
    }

    /**
     * @param array $items
     * @return array
     */
    private function replaceIdWithIncrementId(array $items)
    {
        $items = array_map(function ($item) {
            $item['id'] = $item['increment_id'];
            return $item;
        }, $items);

        return $items;
    }
}
