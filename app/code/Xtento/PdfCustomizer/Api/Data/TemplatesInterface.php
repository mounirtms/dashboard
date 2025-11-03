<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-19T17:03:40+00:00
 * File:          Api/Data/TemplatesInterface.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Api\Data;

interface TemplatesInterface
{
    /**
     * @return mixed
     */
    public function getId();

    /**
     * @param $value
     * @return mixed
     */
    public function setId($value);
}
