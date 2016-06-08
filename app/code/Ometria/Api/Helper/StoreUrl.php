<?php
namespace Ometria\Api\Helper;
class StoreUrl
{
    protected $storeManager;
    protected $urlsByProductId=[];
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->storeManager = $storeManager;
        
    }
    public function saveAllStoreUrls($product)
    {
        $urls = [];
        $originalStore = $this->storeManager->getStore();
        foreach($this->storeManager->getStores() as $store)
        {
            $this->storeManager->setCurrentStore($store);
            $urls[$store->getId()] = $product->getProductUrl();
        }
        $this->storeManager->setCurrentStore($originalStore);
        $this->urlsByProductId[$product->getId()] = $urls;
    }
    
    public function getStoreUrlByProductIdAndStoreId($product_id, $store_id)
    {
        return $this->urlsByProductId[$product_id][$store_id];
    }
    
}