<?php
namespace Ometria\Api\Controller\V1;
use Ometria\Api\Helper\Format\V1\Stores as Helper;
use \Ometria\Api\Controller\V1\Base;
class Stores extends Base
{
    protected $resultJsonFactory;
    protected $storeFactory;
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Magento\Store\Model\StoreFactory $storeFactory, 
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
	) {
		parent::__construct($context);
		$this->resultJsonFactory = $resultJsonFactory;
		$this->storeFactory      = $storeFactory;
		$this->scopeConfig       = $scopeConfig;
	}
	
    public function execute()
    {
        $stores = $this->storeFactory->create()->getCollection()->getItems();
        $stores = array_map(function($item){
            return $item->getData();
        }, $stores);
        sort($stores);
        
        $formated = [];
        foreach($stores as $store)
        {
            $tmp = Helper::getBlankArray();
            $tmp['id']         = $store['store_id'];
            $tmp['title']      = $store['name'];
            $tmp['group_id']   = $store['group_id'];
            $tmp['website_id'] = $store['website_id'];
            $tmp['url']        = $this->scopeConfig->getValue(
                'web/unsecure/base_url',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $store['code']
            );
            $formated[] = $tmp;
        }
		$result = $this->resultJsonFactory->create();
		return $result->setData($formated);
    }    
}