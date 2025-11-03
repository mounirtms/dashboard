<?php
namespace Mab\AdminLocale\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Locale\Resolver;

class CurrentLocale extends Field
{
    /**
     * @var Resolver
     */
    private $localeResolver;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param Resolver $localeResolver
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        Resolver $localeResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->localeResolver = $localeResolver;
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
        $currentLocale = $this->localeResolver->getLocale();
        $localeNames = [
            'en_US' => 'English (United States)',
            'fr_FR' => 'Français (France)',
            'de_DE' => 'Deutsch (Deutschland)',
            'es_ES' => 'Español (España)',
            'it_IT' => 'Italiano (Italia)'
        ];
        
        $localeName = $localeNames[$currentLocale] ?? $currentLocale;
        
        return '<div class="control-value">' . 
               '<strong>' . $currentLocale . '</strong> - ' . $localeName . 
               '</div>';
    }
}
