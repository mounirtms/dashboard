<?php
/**
 * Copyright © Custom. All rights reserved.
 * Fix for missing formElement in configurableExistingAttributeSetId field
 */
namespace Custom\ConfigurableFix\Ui\DataProvider\Product\Form\Modifier;

use Magento\ConfigurableProduct\Ui\DataProvider\Product\Form\Modifier\ConfigurableAttributeSetHandler as CoreConfigurableAttributeSetHandler;
use Magento\Ui\Component\Form;

/**
 * Fixed Data provider for Attribute Set handler in the Configurable products
 */
class ConfigurableAttributeSetHandler extends CoreConfigurableAttributeSetHandler
{
    /**
     * Returns configuration for existing attribute set options - FIXED VERSION
     *
     * @param array $meta
     * @return array
     */
    protected function getExistingAttributeSet($meta)
    {
        // Default configuration with formElement to prevent errors
        $ret = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => Form\Field::NAME,
                        'formElement' => Form\Element\Select::NAME, // FIX: Always set formElement
                        'visible' => false,
                        'dataScope' => 'configurableExistingAttributeSetId',
                        'sortOrder' => 60,
                    ],
                ],
            ],
        ];

        if ($name = $this->getGeneralPanelName($meta)) {
            if (!empty($meta[$name]['children']['attribute_set_id']['arguments']['data']['config']['options'])) {
                $options = $meta[$name]['children']['attribute_set_id']['arguments']['data']['config']['options'];

                // Update the configuration with the full component when options are available
                $ret = [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'component' => 'Magento_Ui/js/form/element/ui-select',
                                'disableLabel' => true,
                                'filterOptions' => false,
                                'elementTmpl' => 'ui/grid/filters/elements/ui-select',
                                'formElement' => 'select', // FIX: Explicitly set formElement
                                'componentType' => Form\Field::NAME,
                                'options' => $options,
                                'label' => __('Choose existing Attribute Set'),
                                'dataScope' => 'configurableExistingAttributeSetId',
                                'sortOrder' => 60,
                                'multiple' => false,
                                'imports' => [
                                    'value' => 'ns = ${ $.ns }, index = attribute_set_id:value',
                                    'visible' => 'ns = ${ $.ns }, index = affectedAttributeSetExisting:checked',
                                    'disabled' =>
                                        '!ns = ${ $.ns }, index = affectedAttributeSetExisting:checked',
                                    '__disableTmpl' => ['disabled' => false, 'value' => false, 'visible' => false],
                                ],
                            ],
                        ],
                    ],
                ];
            }
        }

        return $ret;
    }
}
