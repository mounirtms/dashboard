<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-19T17:03:40+00:00
 * File:          Controller/Adminhtml/Order/Invoice/Printpdf.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Order\Invoice;

use Xtento\PdfCustomizer\Controller\Adminhtml\Order\AbstractPdf;
use Magento\Sales\Api\InvoiceRepositoryInterface;

class Printpdf extends AbstractPdf
{

    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Sales::sales_invoice';

    /**
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        $pdf = $this->returnFile(InvoiceRepositoryInterface::class, 'invoice_id');
        return $pdf;
    }
}
