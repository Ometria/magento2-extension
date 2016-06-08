<?php
namespace Ometria\Core\Data\Form\Element\Text;
class Disabled extends \Magento\Framework\Data\Form\Element\Text
{
    public function getHtml()
    {
        $this->setData('disabled','disabled');
        return parent::getHtml();
    }
}