<?php
namespace Mab\Core\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Mab\Core\Model\License\Validator;

class LicenseStatus extends \Magento\Config\Block\System\Config\Form\Field
{
    private $licenseValidator;

    public function __construct(
        Context $context,
        Validator $licenseValidator,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->licenseValidator = $licenseValidator;
    }

    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $modules = [
            'core' => 'Core Module',
            'checkout_customization' => 'Checkout Customization',
            'delivery_options' => 'Delivery Options',
            'admin_locale' => 'Admin Locale',
            'social_login' => 'Social Login',
            'source_selector' => 'Source Selector',
            'guest_checkout' => 'Guest Checkout',
            'theme' => 'Theme Module'
        ];

        $html = '<div class="mab-license-status" style="margin-top: 10px;">';
        $html .= '<style>
            .mab-license-status .license-module {
                padding: 8px 12px;
                margin: 5px 0;
                border-radius: 4px;
                border-left: 4px solid;
            }
            .mab-license-status .license-module.success {
                background-color: #d4edda;
                border-left-color: #28a745;
                color: #155724;
            }
            .mab-license-status .license-module.error {
                background-color: #f8d7da;
                border-left-color: #dc3545;
                color: #721c24;
            }
            .mab-license-status .license-module.warning {
                background-color: #fff3cd;
                border-left-color: #ffc107;
                color: #856404;
            }
            .mab-license-status .module-name {
                font-weight: bold;
            }
            .mab-license-status .status {
                float: right;
                font-weight: bold;
            }
        </style>';

        try {
            foreach ($modules as $code => $label) {
                try {
                    $isValid = $this->licenseValidator->validateLicense($code);
                    $statusClass = $isValid ? 'success' : 'error';
                    $statusText = $isValid ? __('Valid') : __('Invalid');
                } catch (\Exception $e) {
                    $statusClass = 'warning';
                    $statusText = __('Check Failed');
                }
                
                $html .= sprintf(
                    '<div class="license-module %s"><span class="module-name">%s:</span> <span class="status">%s</span><div style="clear:both;"></div></div>',
                    $statusClass,
                    $this->escapeHtml($label),
                    $this->escapeHtml($statusText)
                );
            }
        } catch (\Exception $e) {
            $html .= '<div class="license-module error">';
            $html .= '<span class="module-name">' . __('License Check Error') . ':</span> ';
            $html .= '<span class="status">' . $this->escapeHtml($e->getMessage()) . '</span>';
            $html .= '<div style="clear:both;"></div></div>';
        }

        $html .= '</div>';
        return $html;
    }

    protected function _getTemplateFile()
    {
        return 'Mab_Core::system/config/license_status.phtml';
    }
}
