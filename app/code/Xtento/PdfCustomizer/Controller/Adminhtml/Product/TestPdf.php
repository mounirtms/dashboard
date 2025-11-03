<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Controller/Adminhtml/Product/TestPdf.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Product;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Xtento\PdfCustomizer\Api\TemplatesRepositoryInterface;
use Xtento\PdfCustomizer\Controller\Adminhtml\Templates;
use Xtento\PdfCustomizer\Helper\GeneratePdf;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;

/**
 * Class TestPdf
 * @package Xtento\PdfCustomizer\Controller\Adminhtml\Order
 */
class TestPdf extends AbstractPdf
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Xtento_PdfCustomizer::templates';

    /**
     * @var SearchCriteriaBuilder
     */
    public $criteriaBuilder;

    /**
     * @var FilterBuilder
     */
    public $filterBuilder;

    /**
     * @var TemplatesRepositoryInterface
     */
    protected $templatesRepositoryInterface;

    /**
     * AbstractPdf constructor.
     *
     * @param Context $context
     * @param FileFactory $fileFactory
     * @param GeneratePdf $generatePdfHelper
     */
    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        GeneratePdf $generatePdfHelper,
        TemplatesRepositoryInterface $templatesRepositoryInterface,
        SearchCriteriaBuilder $_criteriaBuilder,
        FilterBuilder $filterBuilder,
        GeneratePdf $generatePdf
    ) {
        $this->criteriaBuilder = $_criteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->templatesRepositoryInterface = $templatesRepositoryInterface;
        parent::__construct($context, $fileFactory, $generatePdfHelper);
    }


    /**
     * @return \Magento\Framework\Controller\Result\Raw
     */
    public function execute()
    {
        $templateId = $this->getRequest()->getParam('template_id');
        $entity = $this->getRequest()->getParam('template_type');
        $templateHtml = $this->getRequest()->getParam('template_html');
        $templateCss = $this->getRequest()->getParam('template_css');

        $this->templateId = $templateId;
        $this->templateModel();
        $this->templateModel->setTemplateHtml($templateHtml);
        $this->templateModel->setTemplateCss($templateCss);
        $this->templateModel->setData(
            array_merge(
                $this->templateModel()->getData(),
                [
                    'template_paper_ori' => $this->getRequest()->getParam('template_paper_ori'),
                    'template_custom_t' => $this->getRequest()->getParam('template_custom_t'),
                    'template_custom_b' => $this->getRequest()->getParam('template_custom_b'),
                    'template_custom_l' => $this->getRequest()->getParam('template_custom_l'),
                    'template_custom_r' => $this->getRequest()->getParam('template_custom_r')
                ]
            )
        );
        $this->templateModel->setIsTestGeneration(true);

        $item = $this->productCollection();
        if ($item === false) {
            /** @var \Magento\Framework\Controller\Result\Raw $resultPage */
            $resultPage = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
            $resultPage->setContents(__("%1 ID does not exist. Please set a test ID in 'PDF Template' tab.", ucwords(TemplateType::TYPES[$entity])));
            return $resultPage;
        }

        return $this->returnFileInline(ProductRepositoryInterface::class, $item->getId(), $this->templateModel);
    }

    /**
     * @return bool|mixed
     */
    public function productCollection()
    {
        $this->criteriaBuilder->addFilters(
            [
                $this->filterBuilder
                    ->setField('entity_id')
                    ->setValue($this->getRequest()->getParam('entity_id'))
                    ->setConditionType('eq')
                    ->create()
            ]
        );
        $searchCriteria = $this->criteriaBuilder->create();

        //@codingStandardsIgnoreLine
        $collection = $this->_objectManager->create(
            ProductRepositoryInterface::class
        )->getList($searchCriteria);

        $items = $collection->getItems();
        if (count($items) < 1) {
            return false;
        }

        return array_shift($items);
    }

    /**
     * @return mixed|\Xtento\PdfCustomizer\Model\PdfTemplate
     */
    public function templateModel()
    {
        $templateId = $this->templateId;

        if (!$this->templateId) {
            //$this->noRoute();
        }

        $templateModel = $this->templatesRepositoryInterface->getById($templateId);

        $this->templateModel = $templateModel;
        return $this->templateModel;
    }

    /**
     * Check the permission to run it
     *
     * @return boolean
     */
    //@codingStandardsIgnoreLine
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(Templates::ADMIN_RESOURCE_VIEW);
    }
}
