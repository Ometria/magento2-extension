<?php
namespace Ometria\Core\Block\Adminhtml\System\Config\Logs;

use Magento\Framework\Phrase;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class DownloadButton extends Field
{
    /**
     * @return $this|Field
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate('Ometria_Core::system/config/logs/download_button.phtml');
        }
        return $this;
    }

    /**
     * Unset some non-related element parameters
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Get the button and scripts contents
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->addData(
            [
                'button_label' => __($element->getOriginalData('button_label')),
                'html_id' => $element->getHtmlId()
            ]
        );

        return $this->_toHtml();
    }

    /**
     * @return string
     */
    public function getDownloadUrl()
    {
        return $this->_urlBuilder->getUrl(
            'ometria_core/logs/download'
        );
    }
}
