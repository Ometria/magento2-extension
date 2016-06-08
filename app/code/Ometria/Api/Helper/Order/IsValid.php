<?php
namespace Ometria\Api\Helper\Order;
class IsValid
{
    public function fromItem($item)
    {
        $state = array_key_exists('state', $item) ? $item['state'] : false;
        if(!$state)
        {
            return false;
        }

        if(in_array($state,['canceled', 'closed', 'complete', 'processing', 'pending_payment']))
        {
            return true;
        }        
        
        return false;
    }
}
