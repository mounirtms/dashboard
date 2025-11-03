<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-19T17:03:40+00:00
 * File:          Api/TemplatesRepositoryInterface.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Api;

use Xtento\PdfCustomizer\Api\Data\TemplatesInterface;

interface TemplatesRepositoryInterface
{

    /**
     * @param TemplatesInterface $templates
     * @return mixed
     */
    public function save(TemplatesInterface $templates);

    /**
     * @param $value the template id
     * @return mixed
     */
    public function getById($value);

    /**
     * @param TemplatesInterface $templates
     * @return mixed
     */
    public function delete(TemplatesInterface $templates);

    /**
     * @param $value the template id
     * @return mixed
     */
    public function deleteById($value);
}
