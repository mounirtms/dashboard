<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Controller/Adminhtml/Templates/PdfDataProcessor.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Templates;

use Magento\Cms\Controller\Adminhtml\Page\PostDataProcessor;

class PdfDataProcessor extends PostDataProcessor
{

    /**
     * @param array $data
     * @return array
     */
    //@codingStandardsIgnoreLine
    public function validateRequireEntry(array $data)
    {
        $requiredFields = [
            'template_name' => __('Template Name'),
            'store_id' => __('Store View'),
            'template_file_name' => __('Template File Name'),
            'template_paper_ori' => __('Template Paper Orientation'),
            'template_paper_form' => __('Template Paper Form'),
            'is_active' => __('Status')
        ];

        foreach ($data as $field => $value) {
            if (in_array($field, array_keys($requiredFields)) && $value == '') {
                $this->messageManager->addErrorMessage(
                    __('To apply changes you should fill in hidden required "%1" field', $requiredFields[$field])
                );
            }
        }

        return $data;
    }
}
