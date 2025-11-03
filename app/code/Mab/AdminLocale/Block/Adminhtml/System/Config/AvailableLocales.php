<?php
namespace Mab\AdminLocale\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Locale\ListsInterface;

class AvailableLocales extends Field
{
    /**
     * @var ListsInterface
     */
    private $localeLists;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param ListsInterface $localeLists
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        ListsInterface $localeLists,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->localeLists = $localeLists;
    }

    /**
     * Remove scope label
     *
     * @param  AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return element html
     *
     * @param  AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $locales = $this->localeLists->getOptionLocales();
        $html = '<div class="control-value">';
        $html .= '<ul style="list-style: none; padding: 0;">';
        
        foreach ($locales as $locale) {
            if (!empty($locale['value'])) {
                $html .= '<li style="padding: 2px 0;"><strong>' . 
                        $this->escapeHtml($locale['value']) . '</strong> - ' . 
                        $this->escapeHtml($locale['label']) . '</li>';
            }
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        return $html;
    }
}