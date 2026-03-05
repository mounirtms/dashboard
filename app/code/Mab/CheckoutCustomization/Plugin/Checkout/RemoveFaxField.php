<?php
/**
 * Remove Fax Field from Checkout
 * Hides fax field from shipping and billing address forms
 */

namespace Mab\CheckoutCustomization\Plugin\Checkout;

use Magento\Checkout\Block\Checkout\LayoutMerger;

class RemoveFaxField
{
    /**
     * Remove fax field from checkout layout configuration
     *
     * @param LayoutMerger $subject
     * @param array $result
     * @return array
     */
    public function afterMerge(LayoutMerger $subject, $result)
    {
        if (!isset($result['components']['checkout']['children']['steps']['children'])) {
            return $result;
        }

        // Remove fax from shipping address form
        if (isset($result['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['children']['shipping-address-fieldset']['children']['fax'])) {
            unset($result['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['children']['shipping-address-fieldset']['children']['fax']);
        }

        // Remove fax from billing address form
        if (isset($result['components']['checkout']['children']['steps']['children']['billing-step']['children']['payment']['children']['payments-list']['children']['billing-address-form']['children']['billing-address-fieldset']['children']['fax'])) {
            unset($result['components']['checkout']['children']['steps']['children']['billing-step']['children']['payment']['children']['payments-list']['children']['billing-address-form']['children']['billing-address-fieldset']['children']['fax']);
        }

        // Also remove from new address form in payment step
        if (isset($result['components']['checkout']['children']['steps']['children']['billing-step']['children']['payment']['children']['additional-payment-validators']['children']['fax'])) {
            unset($result['components']['checkout']['children']['steps']['children']['billing-step']['children']['payment']['children']['additional-payment-validators']['children']['fax']);
        }

        return $result;
    }
}
