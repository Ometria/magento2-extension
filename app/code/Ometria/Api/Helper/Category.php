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
        return array_map(function($id){
            $category = $this->categoryRepository->get($id);
            
            return [
                'type'      =>'category',
                'id'        =>$id,
                'url_key'   =>$category->getUrlKey(),
                'url_path'  =>$category->getUrlPath(),
                'label'     =>$category->getName()
            ];        
        }, $ids);
        
    }
}