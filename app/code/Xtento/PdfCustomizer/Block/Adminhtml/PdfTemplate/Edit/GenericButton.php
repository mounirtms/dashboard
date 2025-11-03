<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-19T17:03:40+00:00
 * File:          Block/Adminhtml/PdfTemplate/Edit/GenericButton.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Block\Adminhtml\PdfTemplate\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Registry;

/**
 * Class GenericButton
 */
abstract class GenericButton
{
    /**
     * @var \Magento\Framework\AuthorizationInterface
     */
    private $authorization;

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    private $coreRegistry = null;

    /**
     * @var Context
     */
    private $context;

    /**
     * GenericButton constructor.
     * @param Context $context
     * @param Registry $registry
     */
    public function __construct(
        Context $context,
        Registry $registry
    ) {
        $this->coreRegistry = $registry;
        $this->context = $context;
        $this->authorization = $context->getAuthorization();
    }

    /**
     * Return Template ID
     *
     * @return int|null
     */
    public function getTemplateId()
    {
        return $this->coreRegistry->registry('pdfcustomizer_template')->getData('template_id');
    }

    /**
     * Generate url by route and parameters
     *
     * @param   string $route
     * @param   array $params
     * @return  string
     */
    public function getUrl($route = '', $params = [])
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }

    /**
     * Check whether is allowed action
     *
     * @param string $resourceId
     * @return bool
     */
    //@codingStandardsIgnoreLine
    protected function _isAllowedAction($resourceId)
    {
        return $this->authorization->isAllowed($resourceId);
    }
}
