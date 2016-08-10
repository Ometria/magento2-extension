<?php
namespace Ometria\Api\Controller\V1\Get;
use Ometria\Api\Helper\Config;
use \Ometria\Api\Controller\V1\Base;
class Settings extends Base
{
    protected $resultJsonFactory;    
    protected $helperConfig;
    protected $resourceConfig;
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		Config $helperConfig,
        \Magento\Config\Model\ResourceModel\Config $resourceConfig,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
	) {
		parent::__construct($context);
		$this->resultJsonFactory = $resultJsonFactory;
		$this->helperConfig = $helperConfig;
		$this->resourceConfig = $resourceConfig;		
		$this->_cacheTypeList = $cacheTypeList;
	}
	
    public function execute()
    {
        $values = $this->helperConfig->get();    
        $data   = [Config::CONFIG_TOP=>$values];

        $values = $this->helperConfig->get(null, Config::CONFIG_TOP_ABANDONEDCARTS);    
        $data[Config::CONFIG_TOP_ABANDONEDCARTS] = $values;        
        
        $path = 'advanced/unique_id';
        if(!$this->helperConfig->get($path))
        {
            $unique_id = md5(uniqid().time());
            $this->resourceConfig->saveConfig(Config::CONFIG_TOP . '/' . $path, $unique_id, 'default', 0);
            $data[Config::CONFIG_TOP]['advanced']['unique_id'] = $unique_id;
            $this->_cacheTypeList->cleanType('config');
        }
        
		$result = $this->resultJsonFactory->create();		
		return $result->setData($data);
    }    
}