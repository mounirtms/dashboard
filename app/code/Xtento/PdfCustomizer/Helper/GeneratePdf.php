<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2022-01-01T21:11:46+00:00
 * File:          Helper/GeneratePdf.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Helper;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject\Factory as DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Xtento\PdfCustomizer\Model\PdfTemplate;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Xtento\PdfCustomizer\Helper\Variable\Processors\Output as OutputHelper;
use Xtento\PdfCustomizer\Helper\Variable\Processors\ProductOutput as ProductOutputHelper;
use Xtento\PdfCustomizer\Helper\Data as DataHelper;
use Xtento\PdfCustomizer\Model\PdfTemplateFactory;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Filesystem\DirectoryList;
use Xtento\XtCore\Helper\Utils;

/**
 * Helper class to generate PDFs from code calls - for YOU, the developer :) See mainly: generatePdfForCollection, generatePdfForObject
 *
 * Class GeneratePdf
 * @package Xtento\PdfCustomizer\Helper
 */
class GeneratePdf extends AbstractHelper
{
    /**
     * @var DateTime
     */
    protected $dateTime;

    /**
     * @var OutputHelper
     */
    protected $outputHelper;

    /**
     * @var ProductOutputHelper
     */
    protected $productOutputHelper;

    /**
     * @var Data
     */
    protected $dataHelper;

    /**
     * @var PdfTemplateFactory
     */
    protected $pdfTemplateFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepositoryInterface;

    /**
     * @var DataObject
     */
    protected $dataObject;

    /**
     * @var ExtensibleDataObjectConverter
     */
    protected $extensibleDataObjectConverter;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var File
     */
    protected $file;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var Utils
     */
    protected $utilsHelper;

    /**
     * GeneratePdf constructor.
     *
     * @param Context $context
     * @param DateTime $dateTime
     * @param OutputHelper $outputHelper
     * @param ProductOutputHelper $productOutputHelper
     * @param Data $dataHelper
     * @param PdfTemplateFactory $pdfTemplateFactory
     * @param ExtensibleDataObjectConverter $extensibleDataObjectConverter
     * @param DataObject $dataObject
     * @param CustomerRepositoryInterface $customerRepositoryInterface
     * @param ObjectManagerInterface $objectManager
     * @param File $file
     * @param DirectoryList $directoryList
     * @param Utils $utilsHelper
     */
    public function __construct(
        Context $context,
        DateTime $dateTime,
        OutputHelper $outputHelper,
        ProductOutputHelper $productOutputHelper,
        DataHelper $dataHelper,
        PdfTemplateFactory $pdfTemplateFactory,
        ExtensibleDataObjectConverter $extensibleDataObjectConverter,
        DataObject $dataObject,
        CustomerRepositoryInterface $customerRepositoryInterface,
        ObjectManagerInterface $objectManager,
        File $file,
        DirectoryList $directoryList,
        Utils $utilsHelper
    ) {
        $this->dateTime = $dateTime;
        $this->outputHelper = $outputHelper;
        $this->productOutputHelper = $productOutputHelper;
        $this->dataHelper = $dataHelper;
        $this->pdfTemplateFactory = $pdfTemplateFactory;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
        $this->dataObject = $dataObject;
        $this->extensibleDataObjectConverter = $extensibleDataObjectConverter;
        $this->objectManager = $objectManager;
        $this->file = $file;
        $this->directoryList = $directoryList;
        $this->utilsHelper = $utilsHelper;
        parent::__construct($context);
    }

    /**
     * Supply order/invoice/shipment/credit memo/product collection (or repository) and get a PDF file (as binary in 'pdf' in array) in return
     *
     * @param $collection - Can be a order/invoice/shipment/credit memo/product collection
     * @param null $templateId - Provide PDF Template ID to generate or provide non and have the extension pick the default template
     *
     * @return array|bool
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Zend_Pdf_Exception
     */
    public function generatePdfForCollection($collection, $templateId = null)
    {
        if (empty($collection)) {
            return false;
        }

        $isMixedCollection = false;
        $lastTemplateType = false;
        $templateModel = false;
        $helper = false;
        foreach ($collection as $object) {
            // Done in loop, created pdf files are registered in Output::pdfFiles[]
            $templateModel = $this->processObject($object, $templateId);
            if ($templateModel === false) {
                continue;
            }

            if ($lastTemplateType === false) {
                $lastTemplateType = $templateModel->getTemplateType();
            } else {
                if ($lastTemplateType !== $templateModel->getTemplateType()) {
                    $isMixedCollection = true;
                }
            }

            // Get helper
            if ($templateModel->getTemplateType() == TemplateType::TYPE_PRODUCT) {
                $helper = $this->productOutputHelper;
            } else {
                $helper = $this->outputHelper;
            }

            $backupFolder = $this->getBackupFolder();
            $backupFilenamePrefix = $this->getBackupFilenamePrefix($templateModel, $object->getId());
            // Check if PDF has already been generated, if so, return it
            if ($templateModel->getData('read_pdf_from_backup_folder')) {
                if (!$this->file->isExists($backupFolder)) {
                    $this->file->createDirectory($backupFolder);
                }
                $generatedPdfsInFolder = $this->file->search($backupFilenamePrefix . '*.pdf', $backupFolder);
                if (!empty($generatedPdfsInFolder)) {
                    $alreadyGeneratedPdf = array_shift($generatedPdfsInFolder);
                    $helper->addPdfFile($alreadyGeneratedPdf);
                    continue; // Continue with next object to be created as PDF
                }
            }

            // Create PDF if PDF doesn't exist yet
            $fileParts = $helper->template2Pdf();

            // Save backup copy in folder
            if ($templateModel->getData('save_pdf_in_backup_folder')) {
                $pdfData = false;
                try {
                    $pdfData = file_get_contents($helper->getLastPdf());
                } catch (\Exception $e) {}
                if (!empty($pdfData)) {
                    if (isset($fileParts['filename'])) {
                        $pdfFilename = $fileParts['filename'];
                    } else {
                        $dateTime = $this->dateTime->date('Y-m-d_H-i-s');
                        $pdfFilename = TemplateType::TYPES[$templateModel->getTemplateType()] . '_' . $dateTime . '.pdf';
                        $pdfFilename = rtrim($pdfFilename, '.pdf') . '.pdf';
                    }

                    $this->saveBackupCopy($backupFilenamePrefix, $pdfFilename, $pdfData);
                }
            }
        }

        if ($helper === false) {
            return false;
        }

        $pdfData = $helper->pdfMerger();

        $dateTime = $this->dateTime->date('Y-m-d_H-i-s');
        if ($collection->getSize() === 1) {
            if (isset($fileParts) && isset($fileParts['filename'])) {
                $fileName = $fileParts['filename'];
            } else {
                $fileName = TemplateType::TYPES[$templateModel->getTemplateType()] . '_' . $dateTime . '.pdf';
            }
        } else {
            if ($isMixedCollection) {
                $fileName = 'pdfs_' . $dateTime . '.pdf';
            } else {
                $fileName = TemplateType::TYPES[$templateModel->getTemplateType()] . 's_' . $dateTime . '.pdf';
            }
        }
        $fileName = rtrim($fileName, '.pdf') . '.pdf';

        return ['filename' => $fileName, 'output' => $pdfData];
    }

    /**
     * Supply order/invoice/shipment/credit memo/product *object* and get a PDF file (as binary in 'pdf' in array) in return
     *
     * @param $entity - Can be a repository or just the entity, for example "order"
     * @param $sourceId - Can be the entity_id of the object or an actual object such as an order/invoice/product
     * @param null $templateId - Provide PDF Template ID to generate or provide non and have the extension pick the default template
     *
     * @return array|bool
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Zend_Pdf_Exception
     */
    public function generatePdfForObject($entity, $sourceId, $templateId = null)
    {
        if (empty($sourceId)) {
            return false;
        }

        if (stristr($entity, 'RepositoryInterface') === false) {
            // Build repository class manually if no repository was given, for example when just providing "order" as $entity
            $entity = ucfirst($entity);
            if ($entity === 'Product') {
                $entity = 'Magento\Catalog\Api\\' . $entity . 'RepositoryInterface';
            } else {
                $entity = 'Magento\Sales\Api\\' . $entity . 'RepositoryInterface';
            }
        }

        if (is_object($sourceId)) {
            $object = $sourceId; // If you already have an order, invoice, etc. model, just pass it in $sourceId
        } else {
            $repository = $this->objectManager->create($entity);
            if ($entity === ProductRepositoryInterface::class) {
                // Product
                $object = $repository->getById($sourceId);
            } else {
                // Sales Object
                //@todo: Allow increment ID to be used as well, fallback.
                try {
                    $object = $repository->get($sourceId);
                } catch (\Exception $e) {
                    $object = false;
                }
            }
            if ($object === false) {
                return false;
            }
        }

        $templateModel = $this->processObject($object, $templateId);
        if ($templateModel === false) {
            return false;
        }

        // Check is correct template type
        if ($entity === ProductRepositoryInterface::class && $templateModel->getTemplateType() != TemplateType::TYPE_PRODUCT) {
            return false; // Product PDF, but non-product-template
        }
        if ($entity !== ProductRepositoryInterface::class && $templateModel->getTemplateType() == TemplateType::TYPE_PRODUCT) {
            return false; // Sales PDF, but non-sales-template
        }

        $backupFolder = $this->getBackupFolder();
        $backupFilenamePrefix = $this->getBackupFilenamePrefix($templateModel, $object->getId());
        $isTestGeneration = $templateModel->getIsTestGeneration() === true;

        // Check if PDF has already been generated, if so, return it
        if (!$isTestGeneration && $templateModel->getData('read_pdf_from_backup_folder')) {
            if (!$this->file->isExists($backupFolder)) {
                $this->file->createDirectory($backupFolder);
            }
            $generatedPdfsInFolder = $this->file->search($backupFilenamePrefix . '*.pdf', $backupFolder);
            if (!empty($generatedPdfsInFolder)) {
                $alreadyGeneratedPdf = array_shift($generatedPdfsInFolder);
                $originalPdfFilename = str_replace($backupFilenamePrefix, '', basename($alreadyGeneratedPdf));
                $pdfData = false;
                try {
                    $pdfData = file_get_contents($alreadyGeneratedPdf);
                } catch (\Exception $e) {}
                if (!empty($pdfData)) {
                    return ['filename' => $originalPdfFilename, 'output' => $pdfData];
                }
            }
        }

        if ($templateModel->getTemplateType() == TemplateType::TYPE_PRODUCT) {
            $helper = $this->productOutputHelper;
        } else {
            $helper = $this->outputHelper;
        }
        $fileParts = $helper->template2Pdf();
        $pdfData = $helper->pdfMerger();

        if (isset($fileParts['filename'])) {
            $pdfFilename = $fileParts['filename'];
        } else {
            $dateTime = $this->dateTime->date('Y-m-d_H-i-s');
            $pdfFilename = TemplateType::TYPES[$templateModel->getTemplateType()] . '_' . $dateTime . '.pdf';
            $pdfFilename = rtrim($pdfFilename, '.pdf') . '.pdf';
        }

        // Save backup copy in folder
        if (!$isTestGeneration && $templateModel->getData('save_pdf_in_backup_folder')) {
            $this->saveBackupCopy($backupFilenamePrefix, $pdfFilename, $pdfData);
        }

        return ['filename' => $pdfFilename, 'output' => $pdfData];
    }

    /**
     * @param $object
     * @param null $templateId
     *
     * @return bool|object
     */
    protected function processObject($object, $templateId = null)
    {
        if ($id = $object->getPdfOriginalId()) {
            $object->setId($id);
        }

        if ($templateId === null) {
            $types = array_flip(TemplateType::TYPES);
            $entityType = $object->getEntityType();
            // Get default template if no template ID has been specified
            $templateId = $this->dataHelper->getDefaultTemplate(
                $object,
                $types[$entityType]
            )->getId();
        }

        if ($templateId instanceof PdfTemplate) {
            $templateModel = $templateId; // For PDF Preview
        } else {
            $templateModel = $this->pdfTemplateFactory->create()->load($templateId);
        }

        if (!$templateModel->getId()) {
            return false;
        }

        if ($templateModel->getTemplateType() == TemplateType::TYPE_PRODUCT) {
            $helper = $this->productOutputHelper;
        } else {
            $helper = $this->outputHelper;
        }
        $helper->setSource($object);
        $helper->setTemplate($templateModel);

        // Get customer
        $customerId = false;
        if ($object->getCustomerId()) {
            $customerId = $object->getCustomerId();
        }
        if ($object->getOrder() && $object->getOrder()->getCustomerId()) {
            $customerId = $object->getOrder()->getCustomerId();
        }
        if ($templateModel->getTemplateType() != TemplateType::TYPE_PRODUCT && $customerId) {
            $pseudoCustomer = $this->getCustomer($customerId);
            $helper->setCustomer($pseudoCustomer);
        }

        return $templateModel;
    }

    /**
     * Get customer object as DataObject
     *
     * @param $customerId
     *
     * @return \Magento\Framework\DataObject
     */
    protected function getCustomer($customerId)
    {
        /** @var Customer $customer */
        try {
            $customer = $this->customerRepositoryInterface->getById($customerId);
        } catch (NoSuchEntityException $e) {
            return $this->dataObject->create([]);
        }

        $customerData = $this->extensibleDataObjectConverter->toFlatArray(
            $customer,
            [],
            CustomerInterface::class
        );

        // Support for Xtento_CustomAttributes
        if ($this->utilsHelper->isExtensionInstalled('Xtento_CustomAttributes')) {
            // Must use object manager here unfortunately
            $attributeHelper = $this->objectManager->get('\Xtento\CustomAttributes\Helper\Attribute');
            $customAttributes = $attributeHelper->getCustomAttributes('customer');
            foreach ($customAttributes as $customAttribute) {
                $attributeCode = $customAttribute->getAttributeCode();
                if (isset($customerData[$attributeCode])) {
                    $customAttribute = $this->objectManager->get('\Xtento\CustomAttributes\Helper\Data')->addAttributeData($customAttribute);
                    $attribute = $customAttribute->getData('attribute_data_values');
                    $attributeValue = $attributeHelper->getCustomerAttributeText($customer, $attribute);
                    if (!empty($attributeValue)) {
                        $customerData[$attributeCode] = $attributeValue;
                    }
                }
            }
        }

        $pseudoCustomer = $this->dataObject->create($customerData);
        return $pseudoCustomer;
    }

    /**
     * @return string
     */
    protected function getBackupFolder()
    {
        return rtrim($this->directoryList->getPath(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'xtento_pdf' . DIRECTORY_SEPARATOR;
    }

    /**
     * @param $templateModel
     * @param $objectId
     *
     * @return string
     */
    protected function getBackupFilenamePrefix($templateModel, $objectId)
    {
        return sprintf('%s-%s-%s_', $templateModel->getTemplateType(), $templateModel->getId(), $objectId);
    }

    /**
     * @param $backupFilenamePrefix
     * @param $pdfFilename
     * @param $pdfData
     */
    protected function saveBackupCopy($backupFilenamePrefix, $pdfFilename, $pdfData)
    {
        $backupFolder = $this->getBackupFolder();
        $backupFilePath = $backupFolder . $backupFilenamePrefix . $pdfFilename;
        try {
            if (!file_put_contents($backupFilePath, $pdfData)) {
                $this->_logger->warning(__('[Xtento_PdfCustomizer] Warning: Could not save backup PDF in %1. Please check folder permissions.', $backupFilePath));
            }
        } catch (\Exception $e) {}
    }
}
