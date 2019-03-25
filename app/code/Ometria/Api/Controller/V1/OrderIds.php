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
use Ometria\Api\Controller\V1\Base;

class OrderIds extends Base
{
    const CREATED_SINCE    = 'created_since';
    const CREATED_BEFORE   = 'created_before';
    const UPDATED_SINCE    = 'updated_since';
    const UPDATED_BEFORE   = 'updated_before';
    const SINCE_CONDITION  = 'gteq';
    const BEFORE_CONDITION = 'lteq';

    /** @var OrderCollectionFactory */
    private $orderCollectionFactory;

    /** @var JsonFactory */
    private $resultJsonFactory;

    /**
     * @param Context $context
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        OrderCollectionFactory $orderCollectionFactory,
        JsonFactory $resultJsonFactory
    )
    {
        parent::__construct($context);

        $this->orderCollectionFactory = $orderCollectionFactory;
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
