<?php
namespace Ometria\Core\Model\Observer\Product;
use Ometria\Core\Model\Observer\Product as BaseObserver;
class CatalogProductDeleteAfter extends BaseObserver implements \Magento\Framework\Event\ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        return $this->catalogProductDeleteAfter($observer);
    }
}