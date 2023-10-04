<?php
namespace Ometria\Api\Controller\V1\Salesrules\Delete;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\SalesRule\Model\Service\CouponManagementService;
use Ometria\Api\Controller\V1\Base;

class Coupons extends Base
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;
    /**
     * @var CouponManagementService
     */
    protected $couponmgmtservice;
    /**
     * Coupons constructor.
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param CouponManagementService $couponmgmtservice
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CouponManagementService $couponmgmtservice
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->couponmgmtservice = $couponmgmtservice;
        parent::__construct($context);
    }
    /**
     * Execute
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {

        $ruleId = $this->getRequest()->getParam('rule_id');
        $couponCodesToDelete = $this->getRequest()->getParam('codes');

        if (empty($couponCodesToDelete) || !is_array($couponCodesToDelete)) {
            $result = $this->resultJsonFactory->create();
            $result->setData(['error' => 'Invalid or empty list of coupons to delete']);
            return $result;
        }

        $coupon_res = '';
        try {
            $coupon_res = $this->couponmgmtservice->deleteByCodes($couponCodesToDelete, true);
        } catch(\Exception $e) {
            $result = $this->resultJsonFactory->create();
            $result->setData(['error' => "Failed to delete coupons for ruleid $ruleId"]);
            return $result;
        }

        $result = $this->resultJsonFactory->create();
        return $result->setData([
            'deleted_coupons' => array_diff($couponCodesToDelete, $coupon_res->getFailedItems(), $coupon_res->getMissingItems()),
            'missing_coupons' => $coupon_res->getMissingItems(),
            'failed_coupons' => $coupon_res->getFailedItems()
        ]);
    }
}
