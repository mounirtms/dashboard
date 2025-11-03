<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2022-05-16T21:20:15+00:00
 * File:          Model/Email/Template/CustomFilter.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Model\Email\Template;

use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;

class CustomFilter extends \Magento\Email\Model\Template\Filter
{
    /**
     * @var RegionFactory
     */
    protected $regionFactory;

    protected $filesystem;

    /**
     * Store config directive
     *
     * @param string[] $construction
     * @return string
     */
    public function configDirective($construction)
    {
        // Object manager is used here as the constructor cannot be overwritten as it changed in 2.3.6, 2.3.7, 2.4.0 and no compatibility for all Magento versions can be established otherwise
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->regionFactory = $objectManager->get('Magento\Directory\Model\RegionFactory');

        if (isset($construction[2]) && $construction[2] == ' path="general/store_information/region_id"') {
            $regionCode = parent::configDirective($construction);
            $region = $this->regionFactory->create()->load($regionCode);
            if ($region->getId()) {
                $regionCode = $region->getCode();
            }
            return $regionCode;
        } else {
            return parent::configDirective($construction);
        }
    }

    /**
     * Retrieve media file URL directive
     *
     * @param string[] $construction
     * @return string
     */
    public function mediaDirective($construction)
    {
        // Object manager is used here as the constructor cannot be overwritten as it changed in 2.3.6, 2.3.7, 2.4.0 and no compatibility for all Magento versions can be established otherwise
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->filesystem = $objectManager->get('\Magento\Framework\Filesystem');

        $params = $this->getParameters(html_entity_decode($construction[2], ENT_QUOTES));
        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        if ($mediaDirectory->isFile($params['url'])) {
            return $mediaDirectory->getAbsolutePath($params['url']);
        } else {
            return parent::mediaDirective($construction);
        }
    }

    // PHP 8.1 fix
    public function filter($value)
    {
        return parent::filter((string)$value);
    }
}