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
        /**
        * Orders are placed via the restful API in Magento's stock themes
        */
        if( $this->request->getModuleName() !== 'ometria_api' &&
            !in_array($this->applicationState->getAreaCode(), ['frontend', 'webapi_rest']))
        {
            return false;
        }    
        return true;
    }
}