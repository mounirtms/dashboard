<?php
/**
 * MAB Delivery Options - Mageplaza Methods Source Model
 * 
 * @category    Mab
 * @package     Mab_DeliveryOptions
 * @author      Mounir Abderrahmani <mounir.webdev@gmail.com>
 * @copyright   Copyright (c) 2025 MAB Extensions
 * @license     https://opensource.org/licenses/MIT MIT License
 */

namespace Mab\DeliveryOptions\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class MageplazaMethods
 * 
 * Provides available Mageplaza Table Rate Shipping methods for configuration
 */
class MageplazaMethods implements ArrayInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        
        // Check if Mageplaza Table Rate Shipping is enabled
        $isEnabled = $this->scopeConfig->getValue(
            'carriers/mptablerate/active',
            ScopeInterface::SCOPE_STORE
        );
        
        if ($isEnabled) {
            // Common Mageplaza method codes based on typical configurations
            $methods = [
                '1' => __('Standard Delivery'),
                '2' => __('Yalidine Home Delivery'),
                '3' => __('Express Delivery'),
                '4' => __('Next Day Delivery'),
                '24' => __('Yalidine Desk Delivery'),
                '25' => __('Economy Shipping'),
                '26' => __('Premium Shipping')
            ];
            
            foreach ($methods as $code => $label) {
                $options[] = [
                    'value' => $code,
                    'label' => $label
                ];
            }
        } else {
            $options[] = [
                'value' => '',
                'label' => __('Mageplaza Table Rate Shipping is not enabled')
            ];
        }
        
        return $options;
    }
}