<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Controller/Adminhtml/Templates/Duplicate.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Templates;

use Xtento\PdfCustomizer\Controller\Adminhtml\Templates;
use Xtento\PdfCustomizer\Model\PdfTemplate;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Xtento\PdfCustomizer\Model\PdfTemplateRepository as TemplateRepository;
use Xtento\PdfCustomizer\Model\PdfTemplateFactory;

/**
 * Class Save
 *
 * @package Xtento\PdfCustomizer\Controller\Adminhtml\Templates
 * @SuppressWarnings(CouplingBetweenObjects)
 */
class Duplicate extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Xtento_PdfCustomizer::templates';

    /**
     * @var PdfDataProcessor
     */
    private $dataProcessor;

    /**
     * @var DataPersistorInterface
     */
    private $dataPersistor;

    /**
     * @var TemplateRepository
     */
    private $templateRepository;

    /**
     * @var PdfTemplateFactory
     */
    private $pdfTemplateFactory;

    /**
     * Save constructor.
     *
     * @param Context $context
     * @param PdfDataProcessor $dataProcessor
     * @param DataPersistorInterface $dataPersistor
     * @param TemplateRepository $templateRepository
     * @param PdfTemplateFactory $pdfTemplateFactory
     */
    public function __construct(
        Context $context,
        PdfDataProcessor $dataProcessor,
        DataPersistorInterface $dataPersistor,
        TemplateRepository $templateRepository,
        PdfTemplateFactory $pdfTemplateFactory
    ) {
        $this->dataProcessor = $dataProcessor;
        $this->dataPersistor = $dataPersistor;
        $this->templateRepository = $templateRepository;
        $this->pdfTemplateFactory = $pdfTemplateFactory;
        parent::__construct($context);
    }

    /**
     * Save action
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @return ResultInterface
     */
    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        /** @var PdfTemplate $model */

        $id = $this->getRequest()->getParam('template_id');
        if ($id) {
            $model = $this->templateRepository->getById($id);
            $newModel = $this->pdfTemplateFactory->create();
        }

        $model->unsetData('template_id');
        $newModel->setData($model->getData());

        $newModel->setData('update_time', time());

        try {
            $this->templateRepository->save($model);
            $this->messageManager->addSuccessMessage(__('You saved the template.'));
            $this->dataPersistor->clear('pdfcustomizer_template');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while saving the template.')
            );
        }

        return $resultRedirect->setPath(
            '*/*/edit',
            ['template_id' => $model->getTemplateId(), '_current' => true]
        );
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
