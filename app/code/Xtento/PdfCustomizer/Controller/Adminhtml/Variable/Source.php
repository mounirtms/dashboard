<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-08-26T14:47:42+00:00
 * File:          Controller/Adminhtml/Variable/Source.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Variable;

use Magento\Backend\App\Action\Context;
use Magento\Email\Model\BackendTemplateFactory;
use Magento\Email\Model\Template\Config;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Registry;
use Xtento\PdfCustomizer\Helper\Variable\Custom\SalesCollect as TaxCustom;
use Xtento\PdfCustomizer\Helper\Variable\DefaultVariables;
use Xtento\PdfCustomizer\Model\PdfTemplateRepository as TemplateRepository;
use Xtento\PdfCustomizer\Model\Source\TemplateType;

class Source extends Template
{
    /**
     * @var \Xtento\PdfCustomizer\Helper\Variable\Processors\Pdf
     */
    protected $pdfProcessor;

    /**
     * Source constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param Config $_emailConfig
     * @param JsonFactory $resultJsonFactory
     * @param DefaultVariables $_defaultVariablesHelper
     * @param SearchCriteriaBuilder $_criteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param BackendTemplateFactory $backendTemplateFactory
     * @param TemplateRepository $templateRepository
     * @param TaxCustom $taxCustom
     * @param \Xtento\PdfCustomizer\Helper\Variable\Processors\Pdf $pdfProcessor
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        Config $_emailConfig,
        JsonFactory $resultJsonFactory,
        DefaultVariables $_defaultVariablesHelper,
        SearchCriteriaBuilder $_criteriaBuilder,
        FilterBuilder $filterBuilder,
        BackendTemplateFactory $backendTemplateFactory,
        TemplateRepository $templateRepository,
        TaxCustom $taxCustom,
        \Xtento\PdfCustomizer\Helper\Variable\Processors\Pdf $pdfProcessor
    ) {
        parent::__construct($context, $coreRegistry, $_emailConfig, $resultJsonFactory, $_defaultVariablesHelper, $_criteriaBuilder, $filterBuilder, $backendTemplateFactory, $templateRepository, $taxCustom);
        $this->pdfProcessor = $pdfProcessor;
    }


    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|object
     * @throws \Exception
     */
    public function execute()
    {
        $this->_initTemplate();

        $id = $this->getRequest()->getParam('template_id');
        $type = $this->getRequest()->getPostValue('type_id');

        if ($type) {
            $templateTypeName = TemplateType::TYPES[$type];
        }

        if ($id) {
            $templateModel = $this->templateRepository->getById($id);
            $templateType = $templateModel->getData('template_type');
            $templateTypeName = TemplateType::TYPES[$templateType];
        }

        $collection = $this->collection($templateTypeName);

        if (empty($collection)) {
            return null;
        }

        if (is_object($collection)) {
            $source = $collection->getLastItem();
        } else {
            $source = end($collection);
        }

        $variableProcessor = $this->pdfProcessor;
        $variableProcessor->source = $source;
        $variableProcessor->template = $templateModel;
        if (!$source instanceof \Magento\Sales\Model\Order) {
            $variableProcessor->order = $source->getOrder();
        } else {
            $variableProcessor->order = $source;
        }
        $variables = $variableProcessor->buildVariablesAndProcessTemplate(true);

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();
        $result = $resultJson->setData($this->defaultVariablesHelper->allVariablesToOptionArray($variables));
        return $this->addResponse($result);
    }
}
