<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-08-26T14:59:49+00:00
 * File:          Controller/Adminhtml/Variable/ProductCustom.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Variable;

use Xtento\PdfCustomizer\Helper\Variable\Custom\Items as VariableItems;
use Xtento\PdfCustomizer\Helper\Variable\Custom\Product as ProductHelperData;
use Xtento\PdfCustomizer\Helper\Variable\DefaultVariables;
use Xtento\PdfCustomizer\Model\PdfTemplateRepository as TemplateRepository;
use Xtento\PdfCustomizer\Helper\Variable\Custom\SalesCollect as TaxCustom;
use Magento\Backend\App\Action\Context;
use Magento\Email\Model\Template\Config;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Registry;
use Magento\Email\Model\BackendTemplateFactory;

class ProductCustom extends Template
{

    /**
     * @var VariableItems
     */
    public $customData;

    /**
     * Items constructor.
     * @param Context $context
     * @param Registry $coreRegistry
     * @param Config $_emailConfig
     * @param JsonFactory $resultJsonFactory
     * @param TemplateRepository $templateRepository
     * @param DefaultVariables $_defaultVariablesHelper
     * @param SearchCriteriaBuilder $_criteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param ProductHelperData $customData
     * @param $backendTemplateFactory BackendTemplateFactory
     * @param TaxCustom $taxCustom
     * @SuppressWarnings(ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        Config $_emailConfig,
        JsonFactory $resultJsonFactory,
        TemplateRepository $templateRepository,
        DefaultVariables $_defaultVariablesHelper,
        SearchCriteriaBuilder $_criteriaBuilder,
        FilterBuilder $filterBuilder,
        ProductHelperData $customData,
        BackendTemplateFactory $backendTemplateFactory,
        TaxCustom $taxCustom
    ) {
        $this->templateRepository = $templateRepository;
        parent::__construct(
            $context,
            $coreRegistry,
            $_emailConfig,
            $resultJsonFactory,
            $_defaultVariablesHelper,
            $_criteriaBuilder,
            $filterBuilder,
            $backendTemplateFactory,
            $templateRepository,
            $taxCustom
        );
        $this->coreRegistry = $coreRegistry;
        $this->customData = $customData;
    }

    /**
     * @return $this|null
     */
    public function execute()
    {
        $collection = $this->addCollection();
        if (empty($collection)) {
            return null;
        }

        if (is_object($collection)) {
            $source = $collection->getLastItem();
        } else {
            $source = end($collection);
        }

        $lastItem = $this->customData->entity($source)->processAndReadVariables();

        $this->defaultVariablesHelper->source = $source;
        $variables = $this->defaultVariablesHelper->getCustomProductDefault($lastItem);

        /** @var Json $resultJson */
        return $this->response($variables);
    }
}
