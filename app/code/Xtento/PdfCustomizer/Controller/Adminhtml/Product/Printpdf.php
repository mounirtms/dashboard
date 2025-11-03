<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-10-15T13:06:47+00:00
 * File:          Controller/Adminhtml/Product/Printpdf.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Product;

use Magento\Catalog\Api\ProductRepositoryInterface;

class Printpdf extends AbstractPdf
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Catalog::products';

    /**
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        $pdf = $this->returnFile(ProductRepositoryInterface::class, 'product_id');
        return $pdf;
    }
}
