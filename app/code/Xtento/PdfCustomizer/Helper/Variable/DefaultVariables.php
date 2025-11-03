<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-08-26T15:45:49+00:00
 * File:          Helper/Variable/DefaultVariables.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Helper\Variable;

use Magento\Framework\App\Helper\AbstractHelper;

/**
 * Handles the default system data coming from the source and generates the variables
 *
 * Class Data
 * @package Xtento\PdfCustomizer\Helper
 */
class DefaultVariables extends AbstractHelper
{
    public $source;

    /**
     * @param $allVariables
     *
     * @return array
     */
    public function allVariablesToOptionArray($allVariables)
    {
        $optionArray = [];

        if ($allVariables) {
            foreach ($allVariables as $label => $value) {
                if (is_array($value) || is_object($value)) {
                    if (is_object($value)) {
                        $value = $value->getData();
                    }
                    $tempArray = [];
                    foreach ($value as $subLabel => $subValue) {
                        if (is_object($subValue) || is_array($subValue)) {
                            continue;
                        }

                        if ($subLabel === 'attributes_table_html' || $subLabel === 'item_options' || $subLabel === 'additional_data') {
                            continue;
                        }

                        $optionValue = $this->createNameFromValue($subValue, $subLabel);
                        //if (empty($optionValue) && $optionValue !== 0) {
                        //    continue;
                        //}

                        $prefix = $label . '.';
                        $tempArray[] = [
                            'value' => '{{' . 'var ' . $prefix . $subLabel . '}}',
                            'label' => '{{' . 'var ' . $prefix . $subLabel . '}} - ' . $optionValue
                        ];
                    }

                    $optionArray[] = [
                        'label' => __($label),
                        'value' => $tempArray
                    ];
                } else {
                    if ($label === 'attributes_table_html' || $label === 'item_options' || $label === 'additional_data') {
                        continue;
                    }

                    $optionValue = $this->createNameFromValue($value, $label);
                    //if (empty($optionValue)) {
                    //    continue;
                    //}

                    $prefix = '';
                    $tempArray = [];
                    $tempArray[] = [
                        'value' => '{{' . 'var ' . $prefix . $label . '}}',
                        'label' => '{{' . 'var ' . $prefix . $label . '}} - ' . $optionValue
                    ];
                    $optionArray[] = [
                        'label' => __($label),
                        'value' => $tempArray
                    ];
                }
            }
        }

        return $optionArray;
    }

    /**
     * @param $objectValue
     * @param $label
     *
     * @return string
     */
    private function createNameFromValue($objectValue, $label = null)
    {
        if (is_object($objectValue) || is_array($objectValue)) {
            return null;
        }

        if (is_object($label) || is_array($label)) {
            return null;
        }

        if (is_null($objectValue) || (empty($objectValue) && $objectValue !== 0)) {
            $objectValue = 'NULL';
        }

        $objectValue = preg_replace('/<br\/>/', ' ', $objectValue);

        $labelValue = mb_substr($objectValue, 0, 100);
        return $labelValue;
    }

    /**
     * Retrieve option array of variables
     *
     * @param boolean $withGroup if true wrap variable options in group
     * @param $variables , the passed variables for processing
     * @param $groupLabel , the label for the new variable group
     * @param $prefix , the prefix with dot to get the correct var name
     * @return array
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function getVariablesOptionArray(
        $groupLabel,
        $variables,
        $prefix,
        $withGroup = false
    ) {
        $optionArray = [];

        if ($variables) {
            foreach ($variables as $label => $value) {
                if (is_object($value) || is_array($value)) {
                    continue;
                }
                if ($label === 'attributes_table_html' || $label === 'item_options' || $label === 'additional_data') {
                    continue;
                }

                $optionLabel = $this->createNameFromValue($value, $label);
                if (empty($optionLabel)) {
                    continue;
                }

                $optionArray[] = [
                    'value' => '{{' . 'var ' . $prefix . $label . '}}',
                    'label' => __('%1', $optionLabel) . ' - ({{' . 'var ' . $prefix . $label . '}})'
                ];
                sort($optionArray);
            }
            if ($withGroup) {
                $optionArray = [
                    'label' => __($groupLabel),
                    'value' => $optionArray
                ];
            }
        }
        return $optionArray;
    }

    /**
     * @param $product
     *
     * @return array|bool
     */
    public function getCustomProductDefault($product)
    {
        if (!$product) {
            return false;
        }

        $this->source = $product;
        /** @var Product $data */
        $data = $this->source->getData();

        $groupNameVariables = __('Product Variables');
        $sourceVariables = $this->getVariablesOptionArray($groupNameVariables, $data, 'product.', true);

        $standardVariables = [$sourceVariables];
        return $standardVariables;
    }
}
