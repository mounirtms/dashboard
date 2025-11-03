<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2020-07-27T11:50:21+00:00
 * File:          Setup/Uninstall.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Setup;

use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Magento\Framework\Setup\ModuleContextInterface;


/**
 * Class Uninstall
 * @package Xtento\PdfCustomizer\Setup
 */
class Uninstall implements UninstallInterface
{
    public function __construct()
    {
    }

    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $connection = $setup->getConnection();
        $connection->dropTable($connection->getTableName('xtento_pdf_store'));
        $connection->dropTable($connection->getTableName('xtento_pdf_templates'));

        $setup->endSetup();
    }
}
