<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-08-26T15:27:31+00:00
 * File:          Controller/Adminhtml/Variable/Items.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Variable;

use Magento\Framework\DataObject\Factory as DataObject;
use Xtento\PdfCustomizer\Helper\Variable\Custom\Items as VariableItems;
use Xtento\PdfCustomizer\Helper\Variable\DefaultVariables;
use Xtento\PdfCustomizer\Helper\Variable\Custom\SalesCollect as TaxCustom;
use Magento\Backend\App\Action\Context;
use Magento\Email\Model\Template\Config;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Xtento\PdfCustomizer\Model\PdfTemplateRepository as TemplateRepository;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Registry;
use Magento\Email\Model\BackendTemplateFactory;

class Items extends Template
{

    /**
     * @var VariableItems
     */
    public $customData;

    /**
     * @var DataObject
     */
    protected $dataObject;

    /**
     * @var \Xtento\PdfCustomizer\Helper\Variable\Processors\Items
     */
    protected $itemsProcessor;

    /**
     * Items constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param Config $_emailConfig
     * @param JsonFactory $resultJsonFactory
     * @param TemplateRepository $templateRepository
     * @param DefaultVariables $_defaultVariablesHelper
     * @param SearchCriteriaBuilder $_criteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param VariableItems $customData
     * @param BackendTemplateFactory $backendTemplateFactory
     * @param TaxCustom $taxCustom
     * @param DataObject $dataObject
     * @param \Xtento\PdfCustomizer\Helper\Variable\Processors\Items $itemsProcessor
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
        VariableItems $customData,
        BackendTemplateFactory $backendTemplateFactory,
        TaxCustom $taxCustom,
        DataObject $dataObject,
        \Xtento\PdfCustomizer\Helper\Variable\Processors\Items $itemsProcessor
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
        $this->dataObject = $dataObject;
        $this->itemsProcessor = $itemsProcessor;
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

        $id = $this->getRequest()->getParam('template_id');
        if ($id) {
            $templateModel = $this->templateRepository->getById($id);
        } else {
            // Stop
        }

        $dataItem = $this->dataItem($collection);
        $source = $dataItem->source;
        $lastItem = $this->customData->entity($dataItem)->processAndReadVariables();

        $variableProcessor = $this->itemsProcessor;
        $templateParts = $this->dataObject->create(
            [
                'template_model' => $templateModel,
                'template_html_full' => '',
                'template_html' => '',
                'get_all_variables' => true
            ]
        );
        $variables = $variableProcessor->variableItemProcessor($source, $lastItem, $templateParts);

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();
        $result = $resultJson->setData($this->defaultVariablesHelper->allVariablesToOptionArray($variables));
        return $this->addResponse($result);
    }
}
