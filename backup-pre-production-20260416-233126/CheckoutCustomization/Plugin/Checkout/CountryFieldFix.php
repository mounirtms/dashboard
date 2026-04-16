<?php
/**
 * Fix country_id field visibility in checkout for Algeria
 *
 * The country_id EAV attribute has visible=0, which causes Magento's
 * AttributeMerger::isFieldVisible() to skip it. This plugin forces
 * country_id to be visible so the region_id filterBy link can work.
 *
 * Since this store only serves Algeria, the country field is hidden
 * visually via CSS but must exist as a KO component for the region
 * dropdown to populate correctly.
 */

namespace Mab\CheckoutCustomization\Plugin\Checkout;

use Magento\Checkout\Block\Checkout\AttributeMerger;

class CountryFieldFix
{
    /**
     * Force country_id to be visible before the merge processes elements.
     *
     * Without this, the core isFieldVisible() check sees visible=0 from
     * the EAV metadata and skips country_id entirely, preventing the
     * region_id component from filtering options by country.
     *
     * @param AttributeMerger $subject
     * @param array $elements
     * @param string $providerName
     * @param string $dataScopePrefix
     * @param array $fields
     * @return array|null
     */
    public function beforeMerge(
        AttributeMerger $subject,
        $elements,
        $providerName,
        $dataScopePrefix,
        array $fields = []
    ) {
        if (isset($elements['country_id'])) {
            // Force visible so isFieldVisible() returns true
            $elements['country_id']['visible'] = true;
            // Set default value to DZ (Algeria)
            $elements['country_id']['default'] = 'DZ';
        }

        return [$elements, $providerName, $dataScopePrefix, $fields];
    }

    /**
     * After merge, ensure country_id has correct defaults for Algeria.
     *
     * @param AttributeMerger $subject
     * @param array $result
     * @return array
     */
    public function afterMerge(AttributeMerger $subject, $result)
    {
        if (isset($result['country_id'])) {
            // Ensure default value is DZ
            if (empty($result['country_id']['value'])) {
                $result['country_id']['value'] = 'DZ';
            }
            // Keep it visible as a KO component (CSS will hide it visually)
            $result['country_id']['visible'] = true;
        }

        return $result;
    }
}
