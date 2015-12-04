<?php
namespace Ometria\Core\Helper\Is;
class Frontend
{
    protected $applicationState;
    protected $request;
    
    public function __construct(
        \Magento\Framework\App\State $applicationState,
        \Magento\Framework\App\RequestInterface $request      
    )        
    {
        $this->request          = $request;
        $this->applicationState = $applicationState;
    }
    
    public function check()
    {
        if($this->applicationState->getAreaCode() !== 'frontend' && $this->request->getModuleName() !== 'ometria_api')
        {
            return false;
        }    
        return true;
    }
}