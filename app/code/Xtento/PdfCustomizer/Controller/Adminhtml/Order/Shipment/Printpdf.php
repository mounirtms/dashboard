<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-19T17:03:40+00:00
 * File:          Controller/Adminhtml/Order/Shipment/Printpdf.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Order\Shipment;

use Xtento\PdfCustomizer\Controller\Adminhtml\Order\AbstractPdf;
use Magento\Sales\Api\ShipmentRepositoryInterface;

class Printpdf extends AbstractPdf
{

    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Sales::shipment';

    /**
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        $pdf = $this->returnFile(ShipmentRepositoryInterface::class, 'shipment_id');
        return $pdf;
    }
}
