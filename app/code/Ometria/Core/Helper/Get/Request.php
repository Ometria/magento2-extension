<?php
namespace Ometria\Core\Helper\Get;
class Request
{
    protected $requestInterface;

    public function __construct(
        \Magento\Framework\App\RequestInterface $requestInterface
    )
    {
        $this->requestInterface = $requestInterface;    
    }   
         
    public function __call($method, $params)
    {
        return call_user_func_array([$this->requestInterface,$method], $params);
    }             
}