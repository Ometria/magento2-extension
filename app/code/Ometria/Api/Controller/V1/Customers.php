<?php
namespace Ometria\Api\Controller\V1;

use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Ometria\Api\Helper\Format\V1\Customers as Helper;
use Ometria\Api\Controller\V1\Base;

class Customers extends Base
{
    protected $resultJsonFactory;
    protected $repository;
    protected $customerMetadataInterface;
    protected $genderOptions;
    protected $subscriberCollection;
    protected $customerIdsOfNewsLetterSubscribers=[];
    protected $customerDataHelper;
    protected $searchCriteriaBuilder;
    protected $groupRepository;
    protected $customerGroupNames;

    /** @var PsrLoggerInterface */
    private $logger;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Ometria\Api\Helper\Service\Filterable\Service $apiHelperServiceFilterable,
		\Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
		\Magento\Customer\Api\CustomerMetadataInterface $customerMetadataInterface,
		\Magento\Newsletter\Model\ResourceModel\Subscriber\Collection $subscriberCollection,
        \Ometria\Api\Helper\CustomerData $customerDataHelper,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Customer\Api\GroupRepositoryInterface $groupRepository,
        PsrLoggerInterface $logger
	) {
		parent::__construct($context);
		$this->resultJsonFactory            = $resultJsonFactory;
		$this->apiHelperServiceFilterable   = $apiHelperServiceFilterable;
		$this->repository                   = $customerRepository;
		$this->subscriberCollection         = $subscriberCollection;
		$this->customerMetadataInterface    = $customerMetadataInterface;
		$this->customerDataHelper           = $customerDataHelper;
		$this->searchCriteriaBuilder        = $searchCriteriaBuilder;
		$this->groupRepository              = $groupRepository;
        $this->logger                      = $logger;

		$this->genderOptions                = $this->customerMetadataInterface
            ->getAttributeMetadata('gender')
            ->getOptions();
	}

    public function execute()
    {
        $items = $this->getCustomerItems();

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
    private function getCustomerItems()
    {
        return $this->apiHelperServiceFilterable->createResponse(
            $this->repository,
            CustomerInterface::class
        );
    }

    /**
     * @param $items
     * @return array
     */
    private function getCountData($items)
    {
        return [
            'count' => count($items)
        ];
    }

    /**
     * @param $items
     * @return array[]
     * @throws LocalizedException
     */
    public function getItemsData($items)
    {
        try {
            $subscriberCollection = $this->getSubscriberCollection($items);
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to get Subscriber collection in Customer API.',
                [
                    'message' => $e->getMessage(),
                    'url' => $this->_url->getCurrentUrl(),
                    'trace' => $e->getTraceAsString()
                ]
            );
        }

        $items = array_map(function ($item) use ($subscriberCollection) {

            $new = Helper::getBlankArray();
            try {
                $new["@type"]           = "contact";
                $new["id"]              = array_key_exists('id', $item) ? $item['id'] : '';
                $new["email"]           = array_key_exists('email', $item) ? $item['email'] : '';
                $new["prefix"]          = array_key_exists('prefix', $item) ? $item['prefix'] : '';
                $new["firstname"]       = array_key_exists('firstname', $item) ? $item['firstname'] : '';
                $new["middlename"]      = array_key_exists('middlename', $item) ? $item['middlename'] : '';
                $new["lastname"]        = array_key_exists('lastname', $item) ? $item['lastname'] : '';
                $new["gender"]          = $this->customerDataHelper->getGenderLabel($item);
                $new["date_of_birth"]   = array_key_exists('dob', $item) ? $item['dob'] : '';
                $new["marketing_optin"] = $this->getMarketingOption($item, $subscriberCollection);
                $new["country_id"]      = $this->customerDataHelper->getCountryId($item);
                $new["store_id"]        = array_key_exists('store_id', $item) ? $item['store_id'] : null;
            } catch (\Exception $e) {
                $this->logger->error(
                    'Failed to generate Customer API response.',
                    [
                        'message' => $e->getMessage(),
                        'url' => $this->_url->getCurrentUrl(),
                        'trace' => $e->getTraceAsString(),
                        'item' => $item
                    ]
                );
            }

            if ($this->_request->getParam('raw') != null) {
                $new['_raw'] = $item;

                $new['_raw']['_ometria'] = [
                    'group_name' => $this->getCustomerGroupName($item['group_id'])
                ];
            }

            return $new;
        }, $items);

        return $items;
    }

	public function getMarketingOption($item, $subscriber_collection)
	{
	    if (!array_key_exists('id', $item)) {
	        return false;
	    }

	    if (!$this->customerIdsOfNewsLetterSubscribers) {
	        foreach ($subscriber_collection as $subscriber) {
	            $this->customerIdsOfNewsLetterSubscribers[] = $subscriber->getCustomerId();
	        }
	    }

	    return in_array($item['id'], $this->customerIdsOfNewsLetterSubscribers);
	}

	public function getSubscriberCollectionFromCustomerIds($customer_ids)
	{
	    return $this->subscriberCollection
	        ->addFieldToFilter('customer_id', ['in' => $customer_ids])
	        ->addFieldToFilter('subscriber_status', \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED);
	}

	public function getSubscriberCollection($items)
	{
	    $customer_ids = array_map(function($item){
	        return $item['id'];
	    }, $items);

	    return $this->getSubscriberCollectionFromCustomerIds($customer_ids);
	}

    /**
     * @param int $id
     * @return string|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getCustomerGroupName($id)
    {
        if ($this->customerGroupNames === null) {
            $this->customerGroupNames = [];

            $searchCriteria = $this->searchCriteriaBuilder->create();
            $groups = $this->groupRepository->getList($searchCriteria)->getItems();

            foreach ($groups as $_group) {
                $this->customerGroupNames[$_group->getId()] = $_group->getCode();
            }
        }

        return array_key_exists($id, $this->customerGroupNames)
            ? $this->customerGroupNames[$id]
            : null;
    }
}
