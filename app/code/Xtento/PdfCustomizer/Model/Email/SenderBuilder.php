<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2020-08-06T13:29:58+00:00
 * File:          Model/Email/SenderBuilder.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Model\Email;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Sales\Model\Order;
use Xtento\PdfCustomizer\Helper\Data as DataHelper;
use Xtento\PdfCustomizer\Helper\GeneratePdf;
use Xtento\PdfCustomizer\Model\PdfTemplate;
use Xtento\PdfCustomizer\Model\PdfTemplateRepository;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Magento\Sales\Model\Order\Email\Container\CreditmemoIdentity;
use Magento\Sales\Model\Order\Email\Container\IdentityInterface;
use Magento\Sales\Model\Order\Email\Container\InvoiceIdentity;
use Magento\Sales\Model\Order\Email\Container\OrderIdentity;
use Magento\Sales\Model\Order\Email\Container\ShipmentIdentity;
use Magento\Sales\Model\Order\Email\Container\Template;
use Xtento\PdfCustomizer\Helper\Variable\Processors\Output;
use Xtento\PdfCustomizer\Helper\Data;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Filesystem\Driver\File;

class SenderBuilder extends \Magento\Sales\Model\Order\Email\SenderBuilder
{
    /**
     * @var PdfTemplate
     */
    private $pdfTemplate;

    /**
     * @var Output
     */
    private $helper;

    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * @var PdfTemplateRepository
     */
    private $pdfTemplateRepository;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var GeneratePdf
     */
    private $generatePdfHelper;

    /**
     * @var File
     */
    private $file;

    /**
     * @var Filesystem\Directory\WriteInterface
     */
    private $varDirectory;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $eventManager;

    /**
     * SenderBuilder constructor.
     *
     * @param Template $templateContainer
     * @param IdentityInterface $identityContainer
     * @param TransportBuilder $transportBuilder
     * @param Output $helper
     * @param Data $dataHelper
     * @param PdfTemplateRepository $pdfTemplateRepository
     * @param ObjectManagerInterface $objectManager
     * @param GeneratePdf $generatePdfHelper
     * @param File $file
     * @param Filesystem $filesystem
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     *
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function __construct(
        Template $templateContainer,
        IdentityInterface $identityContainer,
        TransportBuilder $transportBuilder,
        Output $helper,
        Data $dataHelper,
        PdfTemplateRepository $pdfTemplateRepository,
        ObjectManagerInterface $objectManager,
        GeneratePdf $generatePdfHelper,
        File $file,
        Filesystem $filesystem,
        \Magento\Framework\Event\ManagerInterface $eventManager
    ) {
        $this->helper = $helper;
        $this->dataHelper = $dataHelper;
        $this->pdfTemplateRepository = $pdfTemplateRepository;
        $this->objectManager = $objectManager;
        $this->generatePdfHelper = $generatePdfHelper;
        $this->file = $file;
        $this->varDirectory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->eventManager = $eventManager;
        parent::__construct($templateContainer, $identityContainer, $transportBuilder);
    }

    /**
     * Add attachment to the main mail
     */
    public function send()
    {
        $this->addPdfCustomizerPDFAttachment();
        parent::send();
    }

    /**
     * Add attachment to the cc/bcc mail
     */
    public function sendCopyTo()
    {
        $copyTo = $this->identityContainer->getEmailCopyTo();

        if (!empty($copyTo) && $this->identityContainer->getCopyMethod() == 'copy') { // Only required for copy to, "bcc" is handled in send()
            foreach ($copyTo as $email) {
                $this->addPdfCustomizerPDFAttachment();
                $this->configureEmailTemplate();
                $this->transportBuilder->addTo($email);
                $transport = $this->transportBuilder->getTransport();
                $transport->sendMessage();
            }
        } else {
            parent::sendCopyTo();
        }
    }

    /**
     * Add the attachment
     *
     * @return $this
     */
    private function addPdfCustomizerPDFAttachment()
    {
        $templateSpecs = $this->entityByType();
        if (!array_key_exists('email', $templateSpecs)) {
            return $this;
        }

        $templateValue = $templateSpecs['type'];
        $templateType = TemplateType::TYPES[$templateValue];
        $variables = $this->templateContainer->getTemplateVars();
        $object = $variables[$templateType];

        $storeId = null;
        if ($object instanceof Order) {
            $storeId = $object->getStoreId();
        } else if ($object->getOrder()) {
            $storeId = $object->getOrder()->getStoreId();
        }

        $templateEmailConfigPath = $templateSpecs['email'];
        if ($this->dataHelper->isAttachToEmailEnabled($templateEmailConfigPath, $storeId)) {
            try {
                $this->pdfTemplate = $this->dataHelper->getDefaultTemplate(
                    $object,
                    $templateValue
                );
                $this->attachment($templateType, $object);
            } catch (\Exception $e) {
                //file_put_contents('/tmp/test', $e->getMessage()."\n".$e->getTraceAsString());
            }
        }

        return $this;
    }

    /**
     * @param $templateType
     * @param $object
     *
     * @return $this
     */
    private function attachment($templateType, $object)
    {
        if (!$this->pdfTemplate || !$this->pdfTemplate->getId()) {
            return $this;
        }

        $pdf = $this->generatePdfHelper->generatePdfForObject($templateType, $object, $this->pdfTemplate);
        if ($pdf === false) {
            return $this;
        }

        // Ability to customize PDFs (mostly filename) or totally remove them from being attached to emails (by changing filename to DO_NOT_ATTACH) based on order status for example
        $transportObject = new \Magento\Framework\DataObject();
        $transportObject->setPdf($pdf);
        $this->eventManager->dispatch(
            'xtento_pdfcustomizer_attachment_add_before', [
            'transport' => $transportObject,
            'object' => $object,
            'template_type' => $templateType,
            'pdf_template' => $this->pdfTemplate
        ]
        );
        $pdf = $transportObject->getPdf();

        if (strstr($pdf['filename'], 'DO_NOT_ATTACH') === false) {
            $this->transportBuilder->xtAddAttachment(
                $pdf['output'],
                \Laminas\Mime\Mime::TYPE_OCTETSTREAM,
                \Laminas\Mime\Mime::DISPOSITION_ATTACHMENT,
                \Laminas\Mime\Mime::ENCODING_BASE64,
                $pdf['filename']
            );
        }

        /** @var PdfTemplate $model */
        //$model = $pdfFileData['model'];
        $model = $this->pdfTemplate;
        $secondaryAttachments = explode(',', $model->getData('attachments'));
        $this->secondaryAttachments($secondaryAttachments, $object);

        return $this;
    }

    /**
     * @return array
     */
    private function entityByType()
    {
        $identityContainer = $this->identityContainer;

        $result = [];
        if ($identityContainer instanceof OrderIdentity) {
            $result = [
                'email' => DataHelper::EMAIL_ORDER,
                'type' => TemplateType::TYPE_ORDER
            ];
        }

        if ($identityContainer instanceof InvoiceIdentity) {
            $result = [
                'email' => DataHelper::EMAIL_INVOICE,
                'type' => TemplateType::TYPE_INVOICE
            ];
        }

        if ($identityContainer instanceof ShipmentIdentity) {
            $result = [
                'email' => DataHelper::EMAIL_SHIPMENT,
                'type' => TemplateType::TYPE_SHIPMENT
            ];
        }

        if ($identityContainer instanceof CreditmemoIdentity) {
            $result = [
                'email' => DataHelper::EMAIL_CREDITMEMO,
                'type' => TemplateType::TYPE_CREDIT_MEMO
            ];
        }

        return $result;
    }

    /**
     * @param $secondaryAttachments
     * @param $object
     *
     * @return $this
     */
    private function secondaryAttachments($secondaryAttachments, $object)
    {
        if (empty($secondaryAttachments)) {
            return $this;
        }

        foreach ($secondaryAttachments as $secondaryAttachment) {
            if (!is_numeric($secondaryAttachment)) {
                continue;
            }

            $template = $this->pdfTemplateRepository->getById($secondaryAttachment);
            if (!$template->getId()) {
                continue;
            }

            $attachmentOutput = false;
            // Check if a static PDF file should be attached
            if ($template->getAttachmentPdfFile() != '') {
                $attachmentFilename = $template->getAttachmentPdfFile();
                $fullFilePath = $this->varDirectory->getAbsolutePath('pdfattachments/') . $attachmentFilename;
                if ($this->file->isExists($fullFilePath)) {
                    $attachmentOutput = $this->file->fileGetContents($fullFilePath);
                    $filename = $template->getTemplateFileName();
                }
            }

            // Fall back to traditional PDF generation
            if ($attachmentOutput === false) {
                $attachmentHelper = $this->objectManager->create(Output::class);
                $attachmentHelper->setSource($object);
                $attachmentHelper->setTemplate($template);
                $fileParts = $attachmentHelper->template2Pdf();
                $filename = $fileParts['filename'];
                $attachmentOutput = $attachmentHelper->pdfMerger();
            }

            $this->transportBuilder->xtAddAttachment(
                $attachmentOutput,
                \Laminas\Mime\Mime::TYPE_OCTETSTREAM,
                \Laminas\Mime\Mime::DISPOSITION_ATTACHMENT,
                \Laminas\Mime\Mime::ENCODING_BASE64,
                rtrim($filename, '.pdf') . '.pdf'
            );
        }

        return $this;
    }
}
