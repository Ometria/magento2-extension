<?php
namespace Ometria\AbandonedCarts\Controller\Cartlink;
class Index extends \Magento\Framework\App\Action\Action
{
    public function execute()
    {
        exit(__METHOD__);
        return $this->resultFactory->create(
        
            \Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT
        )->setUrl('http://google.com');
//         var_dump(__METHOD__);
//         exit;
    }
}