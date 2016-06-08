<?php
namespace Ometria\Core\Helper; 
use Magento\Framework\App\Helper\AbstractHelper; 
use Magento\Framework\App\Helper\Context; 

class Product extends AbstractHelper 
{
    protected $helperMageConfig;
    protected $helperConfig;

    public function __construct(
        Context $context,
        \Ometria\Core\Helper\MageConfig $helperMageConfig,
        \Ometria\Core\Helper\Config $helperConfig        
    )
    {
        $this->helperMageConfig = $helperMageConfig; 
        $this->helperConfig = $helperConfig;            
        return parent::__construct($context);
    }

    
    public function isSkuMode(){           
        //return Mage::getStoreConfig('ometria/advanced/productmode')=='sku';
        return $this->helperMageConfig->get('ometria/advanced/productmode')=='sku';
    }

    public function getIdentifierForProduct($product) {
        if (!$product) return null;


        if ($this->isSkuMode()) {
            return $product->getSku();
        } else {
            return $product->getId();
        }
    }

    public function getIdentifiersForProducts($products) {

        $is_sku_mode = $this->isSkuMode();

        $ret = array();
        foreach($products as $product){
            if ($is_sku_mode) {
                $ret[] = $product->getSku();
            } else {
                $ret[] = $product->getId();
            }
        }

        return $ret;

    }

    public function convertProductIdsIfNeeded($ids){

        if (!$this->isSkuMode()) {
            return $ids;
        }

        if (!$ids) return $ids;

        $was_array = is_array($ids);
        if (!is_array($ids)) $ids = array($ids);

        $products_collection = Mage::getModel('catalog/product')
                ->getCollection()
                ->addAttributeToFilter('entity_id', array('in' => $ids));

        $skus = array();
        foreach($products_collection as $product) {
            $skus[] =  $product->getSku();
            $product->clearInstance();
        }

        if (!$was_array) {
            return count($skus)>0 ? $skus[0] : null;
        } else {
            return $skus;
        }
    }

    public function getProductByIdentifier($id){
        $product_model = Mage::getModel('catalog/product');

        if ($this->isSkuMode()){
            return $product_model->load($product_model->getIdBySku($id));
        } else {
            return $product_model->load($id);
        }
    }
}
