<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-19T17:03:40+00:00
 * File:          Helper/Variable/Custom/CustomInterface.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */


namespace Xtento\PdfCustomizer\Helper\Variable\Custom;

interface CustomInterface
{
    /**
     * @return object
     */
    public function processAndReadVariables();

    /**
     * @param $source
     * @return object
     */
    public function entity($source);
}
