<?php
namespace Ometria\Api\Controller\V2;
class Products extends \Ometria\Api\Controller\V1\Products
{
    public function execute()
    {
        $items       = $this->getItemsForJson();  
        $items       = $this->addStoreListingToItems($items,        
            $this->resourceConnection);     
        $result = $this->resultJsonFactory->create();
        return $result->setData($items);                  
    }
}