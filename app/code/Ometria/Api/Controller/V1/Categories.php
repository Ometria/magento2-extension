<?php
namespace Ometria\Api\Controller\V1;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Model\Category\TreeFactory;
use Magento\Catalog\Model\Category\Tree;
use Magento\Catalog\Api\Data\CategoryTreeInterface;
use Ometria\Api\Controller\V1\Base;

class Categories extends Base
{
    protected $resultJsonFactory;
    protected $treeFactory;


    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        TreeFactory $treeFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->treeFactory       = $treeFactory;
    }

    public function execute()
    {
        $categoryTree = $this->getCategoryTree();

        if ($this->_request->getParam('count') != null) {
            $data = $this->getCountData($categoryTree);
        } else {
            $data = $this->getItemsData($categoryTree);
        }

        return $this->resultJsonFactory->create()->setData($data);
    }

    /**
     * @return CategoryTreeInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getCategoryTree()
    {
        /** @var Tree $tree */
        $tree = $this->treeFactory->create();

        return $tree->getTree($tree->getRootNode());
    }

    /**
     * @param CategoryTreeInterface $categoryTree
     * @return array
     */
    private function getCountData(CategoryTreeInterface $categoryTree)
    {
        return [
            'count' => $this->countChildrenData($categoryTree)
        ];
    }

    /**
     * @param CategoryTreeInterface $categoryTree
     * @return array
     */
    private function getItemsData(CategoryTreeInterface $categoryTree)
    {
        return $this->serializeChildrenData($categoryTree);
    }

    /**
     * @param CategoryTreeInterface $categoryTree
     * @return array
     */
    private function serializeChildrenData(CategoryTreeInterface $categoryTree)
    {
        $serialized = $categoryTree->getData();

        if (count($serialized['children_data']) > 0) {
            foreach ($serialized['children_data'] as $key => $child) {
                $serialized['children_data'][$key] = $this->serializeChildrenData($child);
            }
        }

        return $serialized;
    }

    /**
     * @param CategoryTreeInterface $categoryTree
     * @param int $count
     * @return int
     */
    private function countChildrenData(CategoryTreeInterface $categoryTree, $count = 1)
    {
        $serialized = $categoryTree->getData();

        if (count($serialized['children_data']) > 0) {
            foreach ($serialized['children_data'] as $key => $child) {
                $count += $this->countChildrenData($child);
            }
        }

        return $count;
    }
}
