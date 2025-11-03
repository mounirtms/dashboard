<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Ui/Component/Listing/Column/IsActive.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Ui\Component\Listing\Column;

class IsActive extends \Magento\Ui\Component\Listing\Columns\Column
{
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $class = '';
                $text = '';
                switch ($item[$this->getData('name')]) {
                    case 0:
                        $class = 'grid-severity-critical';
                        $text = __('Disabled');
                        break;
                    case 1:
                        $class = 'grid-severity-notice';
                        $text = __('Enabled');
                        break;
                }
                $item[$this->getData('name') . '_orig'] = $item[$this->getData('name')];
                $item[$this->getData('name')] = '<span class="' . $class . '"><span>' . $text . '</span></span>';
            }
        }

        return $dataSource;
    }
}