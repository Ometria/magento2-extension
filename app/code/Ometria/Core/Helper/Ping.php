<?php
namespace Ometria\Core\Helper; 
use Magento\Framework\App\Helper\AbstractHelper; 
use Magento\Framework\App\Helper\Context; 
class Ping extends AbstractHelper 
{

    const API_HOST = 'trk.ometria.com';
    const API_SOCKET_SCHEMA = 'ssl://';
    const API_PATH = '/ping.php';
    const API_SOCKET_TIMEOUT = 2;

    protected $helperConfig;
    public function __construct(
        Context $context,
        \Ometria\Core\Helper\Config $helperConfig       
    )        
    {
        $this->helperConfig = $helperConfig;    
        return parent::__construct($context);
    }
    
    public function sendPing($type, $ids, $extra=array(), $store_id=null){
        //$ometriaConfigHelper = Mage::helper('ometria/config');
        $ometriaConfigHelper = $this->helperConfig;

        if (!$ometriaConfigHelper->isConfigured()) {
            return false;
        }

        if (!$ometriaConfigHelper->isPingEnabled()) {
            return true;
        }

        if($ometriaConfigHelper->isDebugMode()) {
            if(is_array($ids)) {
                $ometriaConfigHelper->log("Sending ping. Type: ".$type." " . implode(',', $ids));
            } else {
                $ometriaConfigHelper->log("Sending ping. Type: ".$type." " . $ids);
            }
        }

        $extra['account']   =  $ometriaConfigHelper->getAPIKey($store_id);
        $extra['type']      =  $type;
        $extra['id']        =  $ids;

        return $this->_ping($extra);
    }

    /**
     * Helper function to ping ometria.  Manually doing an fsockopen
     * so that we don't have to wait for a response. Unless debugging
     * when we do wait and log the content body.
     *
     * @param array $parameters
     *
     * @return bool
     */
    protected function _ping($parameters = array()) {

        //file_put_contents('/tmp/ping', json_encode($parameters)."\n", FILE_APPEND);
        //return true;

        //$ometriaConfigHelper = Mage::helper('ometria/config');
        $ometriaConfigHelper = $this->helperConfig;

        $content = http_build_query($parameters);
        $path = self::API_PATH;


        try {

            $fp = fsockopen(self::API_SOCKET_SCHEMA . self::API_HOST, 443, $errorNum, $errorStr, self::API_SOCKET_TIMEOUT);

            if($fp !== false) {

                $out  = "POST $path HTTP/1.1\r\n";
                $out .= "Host: " . self::API_HOST. "\r\n";
                $out .= "Content-type: application/x-www-form-urlencoded\r\n";
                $out .= "Content-Length: " . strlen($content) . "\r\n";
                $out .= "Connection: Close\r\n\r\n";
                $out .= $content;

                fwrite($fp, $out);

                // If debug mode, wait for response and log
                if($ometriaConfigHelper->isDebugMode()) {

                    $responseHeader = "";
                    do {
                        $responseHeader .= fgets($fp, 1024);
                    } while(strpos($responseHeader, "\r\n\r\n") === false);

                    $response = "";
                    while (!feof($fp)) {
                        $response .= fgets($fp, 1024);
                    }

                    $ometriaConfigHelper->log($response);
                }

                fclose($fp);
            } else {
                $ometriaConfigHelper->log("Ping failed: Error $errorNum - $errorStr", Zend_Log::ERR);
                return false;
            }
        } catch (Exception $e) {
            $ometriaConfigHelper->log($e->getMessage());
            return false;
        }

        return true;
    }
}
