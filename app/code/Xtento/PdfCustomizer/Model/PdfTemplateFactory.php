<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-19T17:03:40+00:00
 * File:          Model/PdfTemplateFactory.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Model;

use Magento\Framework\ObjectManagerInterface;

class PdfTemplateFactory implements FactoryInterface
{

    /**
     * Object Manager instance
     *
     * @var ObjectManagerInterface
     */
    private $objectManager = null;

    /**
     * Instance name to create
     *
     * @var string
     */
    private $instanceName = null;

    /**
     * PdfTemplateFactory constructor.
     * @param ObjectManagerInterface $objectManager
     * @param $instanceName
     */
    public function __construct(ObjectManagerInterface $objectManager, $instanceName = PdfTemplate::class)
    {
        $this->objectManager = $objectManager;
        $this->instanceName = $instanceName;
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function create(array $data = [])
    {
        //@codingStandardsIgnoreLine
        return $this->objectManager->create($this->instanceName, $data);
    }
}
