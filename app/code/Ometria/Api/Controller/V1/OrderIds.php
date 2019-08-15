<?php

namespace Ometria\Api\Controller\V1;

use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Exception\InputException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Store\Model\StoreManagerInterface;
use Ometria\Api\Controller\V1\Base;

class OrderIds extends Base
{
    const STORE_ID         = 'store_id';
    const CREATED_SINCE    = 'created_since';
    const CREATED_BEFORE   = 'created_before';
    const UPDATED_SINCE    = 'updated_since';
    const UPDATED_BEFORE   = 'updated_before';
    const SINCE_CONDITION  = 'gteq';
    const BEFORE_CONDITION = 'lteq';
    const EQUAL_CONDITION  = 'eq';
    const CURRENT_PAGE     = 'current_page';
    const PAGE_SIZE        = 'page_size';

    /** @var OrderCollectionFactory */
    private $orderCollectionFactory;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var JsonFactory */
    private $resultJsonFactory;

    /**
     * @param Context $context
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        OrderCollectionFactory $orderCollectionFactory,
        StoreManagerInterface $storeManager,
        JsonFactory $resultJsonFactory
    )
    {
        parent::__construct($context);

        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->storeManager = $storeManager;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * @return Json
     */
    public function execute()
    {
        $items = $this->getOrderIds();

        $result = $this->resultJsonFactory->create();
        return $result->setData($items);
    }

    /**
     * @return array
     */
    private function getOrderIds()
    {
        $collection = $this->getOrderCollection();

        foreach ($this->getFilters() as $attribute => $filters) {
            foreach ($filters as $condition => $value) {
                if ($value !== null) {
                    $collection->addFieldToFilter(
                        $attribute,
                        array($condition => $value)
                    );
                }
            }
        }

        return $collection->getData();
    }

    /**
     * @return OrderCollection
     */
    private function getOrderCollection()
    {
        $collection = $this->orderCollectionFactory->create();

        $collection->addAttributeToSelect(OrderInterface::ENTITY_ID);
        $collection->addAttributeToSelect(OrderInterface::INCREMENT_ID);
        $collection->addAttributeToSelect(OrderInterface::CREATED_AT);
        $collection->addAttributeToSelect(OrderInterface::UPDATED_AT);

        if ($pageSize = $this->getRequestParam(self::PAGE_SIZE)) {
            $collection->setPageSize($pageSize);
        }

        if ($currentPage = $this->getRequestParam(self::CURRENT_PAGE)) {
            $collection->setCurPage($currentPage);
        }

        return $collection;
    }

    /**
     * @return array
     */
    private function getFilters()
    {
        return [
            OrderInterface::CREATED_AT => [
                self::SINCE_CONDITION => $this->formatDate(
                    $this->getRequestParam(self::CREATED_SINCE)
                ),
                self::BEFORE_CONDITION => $this->formatDate(
                    $this->getRequestParam(self::CREATED_BEFORE)
                )
            ],
            OrderInterface::UPDATED_AT => [
                self::SINCE_CONDITION => $this->formatDate(
                    $this->getRequestParam(self::UPDATED_SINCE)
                ),
                self::BEFORE_CONDITION => $this->formatDate(
                    $this->getRequestParam(self::UPDATED_BEFORE)
                )
            ],
            OrderInterface::STORE_ID => [
                self::EQUAL_CONDITION => $this->getStoreFilterId()
            ]
        ];
    }

    /**
     * @param $key
     * @return mixed
     */
    private function getRequestParam($key)
    {
        return $this->getRequest()->getParam($key, null);
    }

    /**
     * @return int
     */
    private function getStoreFilterId()
    {
        if ($this->getRequestParam(self::STORE_ID) !== null) {
            $storeId = (int) $this->getRequestParam(self::STORE_ID);
        } else {
            $storeId = $this->storeManager->getStore()->getId();
        }

        return $storeId;
    }

    /**
     * @param $date
     * @return false|string
     * @throws InputException
     */
    private function formatDate($date)
    {
        // Don't format dates that have not been set a value.
        if ($date === null) {
            return $date;
        }

        $timestamp = strToTime($date);

        if ($timestamp === false) {
            throw new InputException(__(sprintf('Invalid date filter parameter provided: %s', $date)));
        }

        return date(Mysql::DATETIME_FORMAT, $timestamp);
    }
}
