<?php
namespace Ometria\Core\Helper; 
use Magento\Framework\App\Helper\AbstractHelper; 
class Session extends AbstractHelper 
{

    public function getSessionID() {
        $cookie = isset($_COOKIE['ometria']) ? $_COOKIE['ometria'] : '';
        $session_id = null;

        if ($cookie){
            $data = array();
            parse_str($cookie, $data);
            if (isset($data['sid'])) $session_id = $data['sid'];
        }

        return $session_id;
    }
}