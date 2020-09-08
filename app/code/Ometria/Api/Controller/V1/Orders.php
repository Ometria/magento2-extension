<?php
namespace Ometria\Api\Controller\V1;

use Ometria\Api\Helper\Format\V1\Orders as Helper;
use Ometria\Api\Controller\V1\Base;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

class Orders extends Base
{
    protected $resultJsonFactory;
    protected $repository;
    protected $paymentsCollection;
    protected $salesOrderAddressFactory;
    protected $ordersCollection;
    protected $orderValidTester;
    protected $weeeHelper;

    /** @var PsrLoggerInterface */
    private $logger;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Ometria\Api\Helper\Service\Filterable\Service $apiHelperServiceFilterable,
		\Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
		\Magento\Sales\Model\ResourceModel\Order\Payment\Collection $paymentsCollection,
		\Magento\Sales\Model\Order\AddressFactory $salesOrderAddressFactory,
		\Magento\Sales\Model\ResourceModel\Order\Collection $ordersCollection,
		\Ometria\Api\Helper\Order\IsValid $orderValidTester,
        \Magento\Weee\Helper\Data $weeeHelper,
        PsrLoggerInterface $logger
	) {
		parent::__construct($context);
		$this->resultJsonFactory           = $resultJsonFactory;
		$this->apiHelperServiceFilterable  = $apiHelperServiceFilterable;
		$this->repository                  = $orderRepository;
		$this->salesOrderAddressFactory    = $salesOrderAddressFactory;
		$this->paymentsCollection          = $paymentsCollection;
		$this->ordersCollection            = $ordersCollection;
		$this->orderValidTester            = $orderValidTester;
		$this->weeeHelper                  = $weeeHelper;
        $this->logger                      = $logger;
	}

	protected function fixMissingCustomerNames($item)
	{
	    if (!$item['customer']['firstname']) {
	        $item['customer']['firstname'] = $item['billing_address']['firstname'];
	    }

	    if (!$item['customer']['lastname']) {
	        $item['customer']['lastname'] = $item['billing_address']['lastname'];
	    }

	    return $item;
	}

	protected function formatItems($items)
	{
	    foreach ($items as $key => $item) {
	        $new = Helper::getBlankArray();
            $new = [
                '@type' => 'order',
                'id' =>             $item['entity_id'],
                'status' =>         $item['status'],
                'state'  =>         $item['state'],
                'is_valid' =>       $this->orderValidTester->fromItem($item),
                'customer' =>   [
                    'id'        => $item['customer_id'],
                    'firstname' => $item['customer_firstname'],
                    'lastname'  => $item['customer_lastname'],
                    'email'     => $item['customer_email']
                ],

                'lineitems' =>  [],

                'timestamp' =>      $item['created_at'],
                'subtotal' =>       $item['subtotal'],
                'discount' =>       $item['discount_amount'],
                'shipping' =>       $item['shipping_amount'],
                'tax' =>            $item['tax_amount'],
                'grand_total' =>    $item['grand_total'],
                'total_refunded' => $item['total_refunded'],
                'currency' =>       $item['order_currency_code'],
                'channel' =>        'online',
                'store' =>          $item['store_id'],
                'payment_method' =>  null,
                'shipping_method' => $item['shipping_method'],
                'shipping_address' => [
                    'id'           => $item['shipping_address_id'],
                    'city'         => '',
                    'state'        => '',
                    'postcode'     => '',
                    'country_code' => '',
                ],

                'billing_address' => [
                    'id'           => $item['billing_address_id'],
                    'city'         => '',
                    'state'        => '',
                    'postcode'     => '',
                    'country_code' => '',
                ],

                'coupon_code'       => $item['coupon_code'],
                'ip_address'        => $item['remote_ip'],
                'x_forwarded_for'   => $item['x_forwarded_for'],
                'increment_id'      => $item['increment_id']
            ];

            if ($this->_request->getParam('raw') === 'true') {
                $new['_raw'] = $item;
            }

	        $items[$key] = $new;
	    }
	    return $items;
	}

	protected function addAddresses($items)
	{
	    foreach ($items as $key => $item) {
	        $shipping = $this->salesOrderAddressFactory->create()
	            ->load($item['shipping_address']['id']);

	        $billing = $this->salesOrderAddressFactory->create()
	            ->load($item['billing_address']['id']);

            $item['shipping_address']['firstname']      = $shipping->getFirstname();
            $item['shipping_address']['lastname']       = $shipping->getLastname();
            $item['shipping_address']['city']           = $shipping->getCity();
            $item['shipping_address']['state']          = $shipping->getRegion();
            $item['shipping_address']['postcode']       = $shipping->getPostcode();
            $item['shipping_address']['country_code']   = $shipping->getCountryId();

            $item['billing_address']['firstname']      = $shipping->getFirstname();
            $item['billing_address']['lastname']       = $shipping->getLastname();
            $item['billing_address']['city']            = $billing->getCity();
            $item['billing_address']['state']           = $billing->getRegion();
            $item['billing_address']['postcode']        = $billing->getPostcode();
            $item['billing_address']['country_code']    = $billing->getCountryId();

            unset($item['shipping_address']['id']);
            unset($item['billing_address']['id']);
            $item = $this->fixMissingCustomerNames($item);
            $items[$key] = $item;
	    }

	    return $items;
	}

	protected function addPayments($items)
	{
	    $order_ids = array_map(function($item){
	        return $item['id'];
	    }, $items);

	    $this->paymentsCollection->addFieldToFilter(
	        'parent_id',
	        ['in' => $order_ids]
        );

	    $indexedByParentId = [];
	    foreach ($this->paymentsCollection as $payment) {
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

	protected function indexLineItemsByParentAndChild($line_items)
	{
        $indexedParentChild = [];
        foreach ($line_items as $line_item) {
            if (!$line_item->getParentItemId()) {
                $indexedParentChild[$line_item->getId()] = [
                    'parent'   => $line_item->getData(),
                    'children' => []
                ];
                continue;
            }
            $indexedParentChild[$line_item->getParentItemId()]['children'][] = $line_item->getData();
        }
        return $indexedParentChild;
	}

	protected function addLineItems($items)
	{
	    $order_ids = array_map(function($item){
	        return $item['id'];
	    }, $items);

	    $this->ordersCollection->addFieldToFilter(
	        'entity_id',
	        ['in' => $order_ids]
        );

	    foreach ($items as $key => $item) {
	        $order = $this->ordersCollection->getItemById($item['id']);

	        if (!$order) {
	            continue;
	        }

	        $lineItems = $order->getItemsCollection();

            $indexedParentChild = $this
                ->indexLineItemsByParentAndChild($lineItems);

            $newLineItems = [];
            foreach ($indexedParentChild as $lineItem) {
                $new = [
                    "product" => [
                        "id"    => $lineItem['parent']['product_id'],
                        "sku"   => $lineItem['parent']['sku'],
                        "title" => $lineItem['parent']['name'],
                        "price" => $lineItem['parent']['price'],
                    ]
                ];

                $childrenIds = array_map(function($item) {
                    return $item['product_id'];
                }, $lineItem['children']);

                $childrenSkus = array_map(function($item) {
                    return $item['sku'];
                }, $lineItem['children']);

                $new["variant_id"]        = implode(',', $childrenIds);
                $new["variant_sku"]       = implode(',', $childrenSkus);
                $new["sku"]               = $lineItem['parent']['sku'];
                $new["quantity"]          = $lineItem['parent']['qty_ordered'];
                $new["unit_price"]        = $lineItem['parent']['price'];
                $new["subtotal"]          = $lineItem['parent']['row_total'];
                $new["discount"]          = $this->formatLineItemDiscount($lineItem['parent']['discount_amount']);
                $new["discount_percent"]  = $lineItem['parent']['discount_percent'];
                $new["tax"]               = $lineItem['parent']['tax_amount'];
                $new["tax_percent"]       = $lineItem['parent']['tax_percent'];

                $_orderItem = $lineItems->getItemById($lineItem['parent']['item_id']);
                $new["total"] = (string) $this->calculateLineItemTotal($_orderItem);

                if ($this->_request->getParam('raw') === 'true') {
                    $new['_raw'] = $lineItem;
                }

                $newLineItems[] = $new;
            }
            $item['lineitems'] = $newLineItems;
            $items[$key]        = $item;
	    }
        return $items;
	}

    /**
     * Format the line item discount to only show negative when needed
     * @param $amount
     * @return string
     */
	private function formatLineItemDiscount($amount) {
	    return (int) $amount > 0 ? sprintf('-%s', $amount) : $amount;
    }

    /**
     * @param \Magento\Sales\Model\Order\Item|\Magento\Quote\Model\Quote\Item\AbstractItem $item
     * @return mixed
     * @see \Magento\Weee\Block\Item\Price\Renderer::getTotalAmount
     */
    protected function calculateLineItemTotal($item)
    {
        // just in case getItemById() returned null
        if (!$item) {
            return null;
        }

        $totalAmount = $item->getRowTotal()
            - $item->getDiscountAmount()
            + $item->getTaxAmount()
            + $item->getDiscountTaxCompensationAmount()
            + $this->weeeHelper->getRowWeeeTaxInclTax($item);

        return $totalAmount;
	}

	protected function replaceIdWithIncrementId($items)
	{
	    $items = array_map(function($item) {
	        $item['id'] = $item['increment_id'];
	        return $item;
	    }, $items);

	    return $items;
	}

    public function execute()
    {
        try {
            // null used instead of actual type 'Magento\Sales\Api\Data\OrderInterface' here
            // due to triggering a Notice: Array to string conversion. A bug?
            $items = $this->apiHelperServiceFilterable->createResponse(
                $this->repository,
                null
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to generate Order API items list from search criteria.',
                [
                    'message' => $e->getMessage(),
                    'url' => $this->_url->getCurrentUrl(),
                    'trace' => $e->getTraceAsString()
                ]
            );
        }

        try {
            $items = $this->formatItems($items);
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to format Order API items.',
                [
                    'message' => $e->getMessage(),
                    'url' => $this->_url->getCurrentUrl(),
                    'trace' => $e->getTraceAsString()
                ]
            );
        }

        try {
            $items = $this->addPayments($items);
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to add payment information to Order API items.',
                [
                    'message' => $e->getMessage(),
                    'url' => $this->_url->getCurrentUrl(),
                    'trace' => $e->getTraceAsString()
                ]
            );
        }

        try {
            $items = $this->addAddresses($items);
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to add billing/shipping address information to Order API items.',
                [
                    'message' => $e->getMessage(),
                    'url' => $this->_url->getCurrentUrl(),
                    'trace' => $e->getTraceAsString()
                ]
            );
        }

        try {
            $items = $this->addLineItems($items);
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to add line item information to Order API items.',
                [
                    'message' => $e->getMessage(),
                    'url' => $this->_url->getCurrentUrl(),
                    'trace' => $e->getTraceAsString()
                ]
            );
        }

        try {
            $items = $this->replaceIdWithIncrementId($items);
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to replace Order API items entity ID with increment ID.',
                [
                    'message' => $e->getMessage(),
                    'url' => $this->_url->getCurrentUrl(),
                    'trace' => $e->getTraceAsString()
                ]
            );
        }

		$result = $this->resultJsonFactory->create();

		return $result->setData($items);
    }
}
