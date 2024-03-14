<?php
namespace Ometria\Api\Controller\V1\Attribute;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection as AttributeCollection;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Ometria\Api\Helper\Format\V1\Attribute\Types as Helper;
use Ometria\Api\Controller\V1\Base;

class Types extends Base
{
    protected $resultJsonFactory;
    protected $attributeCollectionFactory;
    protected $context;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        AttributeCollectionFactory $attributeCollectionFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
    }

    public function execute()
    {
        $collection = $this->attributeCollectionFactory->create();

        if ($this->_request->getParam('count') != null) {
            $data = $this->getCountData($collection);
        } else {
            $data = $this->getItemsData($collection);
        }

        return $this->resultJsonFactory->create()->setData($data);
    }

    /**
     * @param $collection
     * @return array
     */
    private function getCountData($collection)
    {
        return [
            'count' => $collection->count()
        ];
    }

    /**
     * @param $collection
     * @return array
     */
    public function getItemsData($collection)
    {
        $data = [];

        /** @var ProductAttributeInterface $attribute */
        foreach ($collection as $attribute) {
            $data[] = $this->serializeAttribute($attribute);
        }

        return $data;
    }

    /**
     * @param $attribute
     * @return null[]
     */
    private function serializeAttribute($attribute)
    {
        $item = Helper::getBlankArray();
        $item['id'] = $attribute->getId();
        $item['title'] = $attribute->getFrontendLabel();
        $item['attribute_code'] = $attribute->getAttributeCode();

        switch ($attribute->getData('frontend_input')) {
            case 'multiselect':
                $item['attribute_type'] = 'OPTION_LIST';
                break;
            case 'select':
                $item['attribute_type'] = 'OPTION';
                break;
            case 'boolean':
                $item['attribute_type'] = 'OPTION';
                break;
            default:
                $item['attribute_type'] = 'FREETEXT';
        }

        return $item;
    }
}
