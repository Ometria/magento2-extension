<?php
namespace Ometria\Api\Helper;
class Category
{
    protected $categoryRepository;
    public function __construct(
        \Magento\Catalog\Model\CategoryRepository $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    public function getOmetriaAttributeFromCategoryIds($ids)
    {
        $ret = [];

        foreach($ids as $id){
            try{
                $category = $this->categoryRepository->get($id);

                if ($category) $ret[] = [
                    'type'      =>'category',
                    'id'        =>$id,
                    'url_key'   =>$category->getUrlKey(),
                    'url_path'  =>$category->getUrlPath(),
                    'label'     =>$category->getName()
                ];
            } catch(\Exception $e) {
                // pass, prevent issues with missing categories
            }
        }

        return $ret;
    }
}