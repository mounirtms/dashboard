<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Model/Source/TemplatePaperOrientation.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Model\Source;

use Magento\Framework\View\Model\PageLayout\Config\BuilderInterface;

/**
 * Class PageLayout
 */
class TemplatePaperOrientation extends AbstractSource
{
    /**
     * @var \Magento\Framework\View\Model\PageLayout\Config\BuilderInterface
     */
    private $pageLayoutBuilder;

    /**
     * Constructor
     *
     * @param BuilderInterface $pageLayoutBuilder
     */
    public function __construct(BuilderInterface $pageLayoutBuilder)
    {
        $this->pageLayoutBuilder = $pageLayoutBuilder;
    }

    /**
     * Paper types
     */
    const TEMAPLATE_PAPER_PORTRAIT = 1;
    const TEMAPLATE_PAPER_LANDSCAPE = 2;

    /**
     * @return array
     */
    public function getAvailable()
    {
        return [
            self::TEMAPLATE_PAPER_PORTRAIT => 'Portrait',
            self::TEMAPLATE_PAPER_LANDSCAPE => 'Landscape',
        ];
    }
}
