<?php
namespace Ometria\Api\Controller\V1;
use \Ometria\Api\Controller\V1\Base;
class Categories extends Base
{
    protected $resultJsonFactory;
    
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
		\Magento\Catalog\Model\CategoryFactory $categoryFactory,
		\Magento\Catalog\Model\Category\TreeFactory $treeFactory
	) {
		parent::__construct($context);
		$this->resultJsonFactory = $resultJsonFactory;
		$this->categoryFactory   = $categoryFactory;
		$this->treeFactory       = $treeFactory;
	}
	
	protected function serializeChildrenData($data)
	{
	    $serialized = $data->getData();
	    if(count($serialized['children_data']) > 0)
	    {
	        foreach($serialized['children_data'] as $key=>$child)
	        {
    	        $serialized['children_data'][$key] = $this->serializeChildrenData($child);
	        }
	    
	    }
	    return $serialized;
	}
    public function execute()
    {
        $category = $this->categoryFactory->create();
        // var_dump($category->getCollection());
        
        //treeFactory is a Magento\Catalog\Model\Category\TreeFactory
        $tree     = $this->treeFactory->create();
        
        $data = $tree->getTree($tree->getRootNode());
        $data = $this->serializeChildrenData($data);
		$result = $this->resultJsonFactory->create();
		return $result->setData($data);
    }    
}