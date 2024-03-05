<?php
namespace Ometria\Api\Controller\V1;

use Magento\Customer\Api\CustomerMetadataInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Newsletter\Model\ResourceModel\Subscriber\Collection as SubscriberCollection;
use Ometria\Api\Helper\CustomerData;
use Ometria\Api\Helper\Format\V1\Customers as Helper;
use Ometria\Api\Helper\Service\Filterable\Service as FilterableService;
use Ometria\Core\Service\Customer\RewardPoints as RewardPointsService;

class Customers extends Base
{
    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var CustomerRepositoryInterface */
    private $repository;

    /** @var CustomerMetadataInterface */
    private $customerMetadataInterface;

    /** @var SubscriberCollection */
    private $subscriberCollection;

    /** @var CustomerData */
    private $customerDataHelper;

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var GroupRepositoryInterface */
    private $groupRepository;

    /** @var RewardPointsService */
    private $rewardPointsService;

    /** @var array */
    private $customerIdsOfNewsLetterSubscribers = [];

    /** @var null|array */
    private $customerGroupNames;

    /** @var array */
    private $genderOptions;
    private $apiHelperServiceFilterable;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        FilterableService $apiHelperServiceFilterable,
        CustomerRepositoryInterface $customerRepository,
        CustomerMetadataInterface $customerMetadataInterface,
        SubscriberCollection $subscriberCollection,
        CustomerData $customerDataHelper,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        GroupRepositoryInterface $groupRepository,
        RewardPointsService $rewardPointsService
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
        $this->rewardPointsService          = $rewardPointsService;
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
        $subscriberCollection = $this->getSubscriberCollection($items);

        $items = array_map(function ($item) use ($subscriberCollection) {

            $new = Helper::getBlankArray();

            $new["@type"] = "contact";
            $new["id"] = array_key_exists('id', $item) ? $item['id'] : '';
            $new["email"] = array_key_exists('email', $item) ? $item['email'] : '';
            $new["prefix"] = array_key_exists('prefix', $item) ? $item['prefix'] : '';
            $new["firstname"] = array_key_exists('firstname', $item) ? $item['firstname'] : '';
            $new["middlename"] = array_key_exists('middlename', $item) ? $item['middlename'] : '';
            $new["lastname"] = array_key_exists('lastname', $item) ? $item['lastname'] : '';
            $new["gender"] = $this->customerDataHelper->getGenderLabel($item);
            $new["date_of_birth"] = array_key_exists('dob', $item) ? $item['dob'] : '';
            $new["marketing_optin"] = $this->getMarketingOption($item, $subscriberCollection);
            $new["country_id"] = $this->customerDataHelper->getCountryId($item);
            $new["store_id"] = array_key_exists('store_id', $item) ? $item['store_id'] : null;

            if ($this->_request->getParam('raw') != null) {
                $new['_raw'] = $item;

                $new['_raw']['_ometria'] = [
                    'group_name' => $this->getCustomerGroupName($item['group_id'])
                ];
            }

            return $new;
        }, $items);

        if ($this->rewardPointsService->isRewardsAvailable()) {
            $this->appendRewardPoints($items);
        }

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

    /**
     * @param $items
     * @return SubscriberCollection
     */
    public function getSubscriberCollection($items)
    {
        $customerIds = $this->getCustomerIds($items);

        return $this->subscriberCollection
            ->addFieldToFilter('customer_id', ['in' => $customerIds])
            ->addFieldToFilter('subscriber_status', \Magento\Newsletter\Model\Subscriber::STATUS_SUBSCRIBED);
    }

    /**
     * Add reward points to items
     *
     * @param $items
     */
    private function appendRewardPoints(&$items)
    {
        $customerIds = $this->getCustomerIds($items);

        $rewardPointsCollection = $this->rewardPointsService->getRewardPointsCollection($customerIds)
            ->addFieldToFilter('customer_id', ['in' => $customerIds]);

        $websiteIds = $this->_request->getParam('website_ids');
        if ($websiteIds) {
            $rewardPointsCollection->addWebsiteFilter($websiteIds);
        }

        foreach ($items as &$item) {
            $reward = $rewardPointsCollection->getItemByColumnValue('customer_id', $item['id']);
            if ($reward) {
                $item['reward_points'] = $reward->getPointsBalance();
            }
        }
    }

    /**
     * @param $items
     * @return array
     */
    private function getCustomerIds($items)
    {
        $customerIds = array_map(function($item){
            return $item['id'];
        }, $items);

        return $customerIds;
    }

    /**
     * @param $id
     * @return mixed|string|null
     * @throws LocalizedException
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
