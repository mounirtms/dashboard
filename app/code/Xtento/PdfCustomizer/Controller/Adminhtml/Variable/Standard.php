<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Controller/Adminhtml/Variable/Standard.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Variable;

use Xtento\PdfCustomizer\Helper\Variable\DefaultVariables;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Xtento\PdfCustomizer\Helper\Variable\Custom\SalesCollect as TaxCustom;
use Magento\Backend\App\Action\Context;
use Magento\Email\Model\Source\Variables;
use Magento\Email\Model\Template\Config;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Xtento\PdfCustomizer\Model\PdfTemplateRepository as TemplateRepository;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Registry;
use Magento\Framework\Json\Helper\Data as JsonData;
use Magento\Variable\Model\Variable;
use Magento\Variable\Model\VariableFactory as VariableModelFactory;
use Magento\Email\Model\BackendTemplateFactory;
use Xtento\XtCore\Helper\Utils;

/**
 * Class Standard
 *
 * @package Xtento\PdfCustomizer\Controller\Adminhtml\Variable
 * @SuppressWarnings(CouplingBetweenObjects)
 */
class Standard extends Template
{
    /**
     * @var JsonData
     */
    private $jsonData;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var Variable
     */
    private $variableModelFactory;

    /**
     * @var Utils
     */
    private $utilsHelper;

    private $variablesModelSourceFactory;

    /**
     * Standard constructor.
     *
     * @param Context $context
     * @param Registry $coreRegistry
     * @param Config $_emailConfig
     * @param JsonFactory $resultJsonFactory
     * @param TemplateRepository $templateRepository
     * @param DefaultVariables $_defaultVariablesHelper
     * @param SearchCriteriaBuilder $_criteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param JsonData $jsonData
     * @param VariableModelFactory $variableModelFactory
     * @param BackendTemplateFactory $backendTemplateFactory
     * @param TaxCustom $taxCustom
     * @param Utils $utilsHelper
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
        JsonData $jsonData,
        VariableModelFactory $variableModelFactory,
        BackendTemplateFactory $backendTemplateFactory,
        TaxCustom $taxCustom,
        Utils $utilsHelper
    ) {
        $this->context = $context;
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
        $this->jsonData = $jsonData;
        $this->variableModelFactory = $variableModelFactory;
        $this->utilsHelper = $utilsHelper;
    }

    /**
     * @return $this|null
     */
    public function execute()
    {
        $isMagento23orNewer = version_compare($this->utilsHelper->getMagentoVersion(), '2.3', '>=');
        if ($isMagento23orNewer) {
            $this->variablesModelSourceFactory = $this->_objectManager->create('\Magento\Variable\Model\Source\VariablesFactory');
        } else {
            $this->variablesModelSourceFactory = $this->_objectManager->create('\Magento\Email\Model\Source\VariablesFactory');
        }

        $template = $this->_initTemplate();

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

        /**if json error see https://github.com/magento/magento2/commit/02bc3fc42bf041919af6200f5dbba071ae3f2020 */

        try {
            $parts = $this->emailConfig->parseTemplateIdParts('sales_email_' . $templateTypeName . '_template');
            $templateId = $parts['templateId'];
            $theme = $parts['theme'];

            if ($theme) {
                $template->setForcedTheme($templateId, $theme);
            }
            $template->setForcedArea($templateId);

            $template->loadDefault($templateId);
            $template->setData('orig_template_code', $templateId);
            $template->setData('template_variables', \Zend_Json::encode($template->getVariablesOptionArray(true)));

            if ($isMagento23orNewer) {
                $templateBlock = $this->_view->getLayout()->createBlock('Magento\Email\Block\Adminhtml\Template\Edit', '', ['data' => ['email_template' => $this->_objectManager->create('\Magento\Email\Model\TemplateFactory')->create()]]);
            } else {
                $templateBlock = $this->_view->getLayout()->createBlock('Magento\Email\Block\Adminhtml\Template\Edit');
            }
            $template->setData('orig_template_currently_used_for', $templateBlock->getCurrentlyUsedForPaths(false));

            $this->getResponse()->representJson(
                $this->jsonData->jsonEncode($template->getData())
            );
        } catch (\Exception $e) {
            //$this->context->getMessageManager()->addExceptionMessage($e, $e->getMessage());
        }

        $customVariables = $this->variableModelFactory->create()
            ->getVariablesOptionArray(true);

        $resultArray = [
            $template->getVariablesOptionArray(true),
            $customVariables
        ];

        $storeContactVariables = $this->variablesModelSourceFactory->create()
            ->toOptionArray(true);
        if ($isMagento23orNewer) {
            foreach ($storeContactVariables as $storeContactVariable) {
                $resultArray[] = $storeContactVariable;
            }
        } else {
            $resultArray[] = $storeContactVariables;
        }

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();

        $result = $resultJson->setData($resultArray);

        return $this->addResponse($result);
    }
}
