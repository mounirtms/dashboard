<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2020-09-09T13:38:47+00:00
 * File:          Controller/Adminhtml/Templates/Save.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Templates;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\HTTP\Adapter\FileTransferFactory;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\Filesystem;
use Xtento\PdfCustomizer\Controller\Adminhtml\Templates;
use Xtento\PdfCustomizer\Model\PdfTemplate;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Xtento\PdfCustomizer\Model\Source\TemplateActive;
use Xtento\PdfCustomizer\Model\PdfTemplateRepository as TemplateRepository;
use Xtento\PdfCustomizer\Model\PdfTemplateFactory;

/**
 * Class Save
 *
 * @package Xtento\PdfCustomizer\Controller\Adminhtml\Templates
 * @SuppressWarnings(CouplingBetweenObjects)
 */
class Save extends Action
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
     * @var FileTransferFactory
     */
    private $fileTransferFactory;

    /**
     * @var UploaderFactory
     */
    private $uploaderFactory;

    /**
     * @var Filesystem\Directory\WriteInterface
     */
    private $varDirectory;

    /**
     * Save constructor.
     *
     * @param Context $context
     * @param PdfDataProcessor $dataProcessor
     * @param DataPersistorInterface $dataPersistor
     * @param TemplateRepository $templateRepository
     * @param PdfTemplateFactory $pdfTemplateFactory
     * @param FileTransferFactory $fileTransferFactory
     * @param UploaderFactory $uploaderFactory
     * @param Filesystem $filesystem
     *
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function __construct(
        Context $context,
        PdfDataProcessor $dataProcessor,
        DataPersistorInterface $dataPersistor,
        TemplateRepository $templateRepository,
        PdfTemplateFactory $pdfTemplateFactory,
        FileTransferFactory $fileTransferFactory,
        UploaderFactory $uploaderFactory,
        Filesystem $filesystem
    ) {
        $this->dataProcessor = $dataProcessor;
        $this->dataPersistor = $dataPersistor;
        $this->templateRepository = $templateRepository;
        $this->pdfTemplateFactory = $pdfTemplateFactory;
        $this->fileTransferFactory = $fileTransferFactory;
        $this->uploaderFactory = $uploaderFactory;
        $this->varDirectory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
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
        $data = $this->getRequest()->getPostValue();
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($data) {
            $data = $this->dataProcessor->validateRequireEntry($data);
            if (isset($data['is_active']) && $data['is_active'] === 'true') {
                $data['is_active'] = TemplateActive::STATUS_ENABLED;
            }
            if (empty($data['template_id'])) {
                $data['template_id'] = null;
            }
            if (isset($data['template_attachment_pdf_remove'])) {
                $data['attachment_pdf_file'] = '';
            }

            /** @var PdfTemplate $model */
            $id = $this->getRequest()->getParam('template_id');
            if ($id) {
                $model = $this->templateRepository->getById($id);
            } else {
                unset($data['template_id']);
                $model = $this->pdfTemplateFactory->create();
            }

            $model->setData($data);
            $model->setData('update_time', time());

            if (!$this->dataProcessor->validate($data)) {
                return $resultRedirect
                    ->setPath('*/*/edit', ['template_id' => $model->getTemplateId(), '_current' => true]);
            }

            try {
                $this->checkAttachmentUpload($model);
                $this->templateRepository->save($model);
                $this->messageManager->addSuccessMessage(__('You saved the template.'));
                $this->dataPersistor->clear('pdfcustomizer_template');
                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect
                        ->setPath('*/*/edit', ['template_id' => $model->getTemplateId(), '_current' => true]);
                }
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __('Something went wrong while saving the template: %1', $e->getMessage())
                );
            }

            $this->dataPersistor->set('pdfcustomizer_template', $data);
            return $resultRedirect
                ->setPath('*/*/edit', ['template_id' => $this->getRequest()->getParam('template_id')]);
        }
        return $resultRedirect->setPath('*/*/');
    }

    /**
     * @param $model
     *
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    protected function checkAttachmentUpload($model)
    {
        /** @var $adapter \Zend_File_Transfer_Adapter_Http */
        $adapter = $this->fileTransferFactory->create();
        if ($adapter->isValid('template_attachment_pdf')) {
            /** @var $uploader \Magento\MediaStorage\Model\File\Uploader */
            $uploader = $this->uploaderFactory->create(['fileId' => 'template_attachment_pdf']);
            $uploader->skipDbProcessing(true);
            $result = $uploader->save($this->varDirectory->getAbsolutePath('pdfattachments/'));
            $extension = pathinfo($result['file'], PATHINFO_EXTENSION);

            $uploadedFile = $result['path'] . $result['file'];
            if ($extension !== 'pdf') {
                $this->varDirectory->delete($uploadedFile);
                throw new \Magento\Framework\Exception\LocalizedException(__('The file you uploaded has an invalid extension.'));
            }
            $newFilename = $result['path'] . $model->getId() . '_' . $result['file'];
            $this->varDirectory->renameFile($uploadedFile, $newFilename);
            $model->setAttachmentPdfFile($model->getId() . '_' . $result['file']);
        }
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
