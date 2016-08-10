<?php
namespace Ometria\Api\Controller\V1;
abstract class Base extends \Magento\Framework\App\Action\Action
{
    public function dispatch(
        \Magento\Framework\App\RequestInterface $request)
    {
        return parent::dispatch($request);
    }
}