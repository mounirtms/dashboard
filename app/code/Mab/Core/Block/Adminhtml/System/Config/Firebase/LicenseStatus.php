<?php
/**
 * MAB Core - Firebase License Status Block
 * 
 * @category    Mab
 * @package     Mab_Core
 * @author      Mounir Abderrahmani <mounir.webdev@gmail.com>
 * @copyright   Copyright (c) 2025 MAB Extensions
 * @license     https://opensource.org/licenses/MIT MIT License
 */

namespace Mab\Core\Block\Adminhtml\System\Config\Firebase;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Module\Manager;

/**
 * Class LicenseStatus
 * 
 * Display Firebase license validation status
 */
class LicenseStatus extends Field
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Manager
     */
    protected $moduleManager;

    /**
     * @var array
     */
    protected $mabModules = [
        'Mab_Core' => 'Core Module',
        'Mab_DeliveryOptions' => 'Delivery Options',
        'Mab_CheckoutCustomization' => 'Checkout Customization',
        'Mab_AdminLocale' => 'Admin Locale Control',
        'Mab_SourceSelector' => 'Source Selector',
        'Mab_SocialLogin' => 'Social Login',
        'Mab_VisualEffects' => 'Visual Effects',
        'Mab_License' => 'License Management',
        'Mab_GuestCheckout' => 'Guest Checkout',
        'Mab_Theme' => 'Theme Settings'
    ];

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param Manager $moduleManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        Manager $moduleManager,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->moduleManager = $moduleManager;
        parent::__construct($context, $data);
    }

    /**
     * Remove scope label
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
     * Return element HTML
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $isBetaMode = $this->scopeConfig->isSetFlag(
            'mab_core/firebase/beta_mode',
            ScopeInterface::SCOPE_STORE
        );

        $html = '<div class="mab-license-status">';
        
        // Beta Mode Banner
        if ($isBetaMode) {
            $html .= '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 16px; border-radius: 8px; margin-bottom: 20px;">';
            $html .= '<h4 style="margin: 0 0 5px 0;">🚀 Beta Mode Active</h4>';
            $html .= '<p style="margin: 0; opacity: 0.9;">All MAB modules are automatically licensed and fully functional during beta period.</p>';
            $html .= '</div>';
        }

        // Module License Status
        $html .= '<div style="background: white; border: 1px solid #dee2e6; border-radius: 8px; padding: 16px;">';
        $html .= '<h4 style="margin: 0 0 15px 0; color: #4361ee;">Module License Status</h4>';

        foreach ($this->mabModules as $moduleName => $moduleTitle) {
            if ($this->moduleManager->isEnabled($moduleName)) {
                $html .= '<div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border: 1px solid #e9ecef; border-radius: 6px; background: #f8f9fa; margin-bottom: 8px;">';
                $html .= '<div>';
                $html .= '<span style="font-weight: 600; color: #333;">' . $moduleTitle . '</span><br>';
                $html .= '<span style="font-size: 12px; color: #666;">' . $moduleName . '</span>';
                $html .= '</div>';
                $html .= '<div>';
                $html .= '<span style="width: 8px; height: 8px; border-radius: 50%; background: #28a745; display: inline-block; margin-right: 6px;"></span>';
                $html .= '<span style="font-size: 12px; font-weight: 600; color: #28a745;">Beta Licensed</span>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}