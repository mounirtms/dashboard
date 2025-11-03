<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2021-04-03T04:48:41+00:00
 * File:          Controller/Adminhtml/Order/Massaction/AbstractMassAction.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Order\Massaction;

use Xtento\PdfCustomizer\Helper\GeneratePdf;
use Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction as SalesAbstractMassAction;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Class AbstractMassAction
 * @package Xtento\PdfCustomizer\Controller\Adminhtml\Order\Massaction
 * @SuppressWarnings(CouplingBetweenObjects)
 */
abstract class AbstractMassAction extends SalesAbstractMassAction
{
    /**
     * @var FileFactory
     */
    public $fileFactory;

    /**
     * @var GeneratePdf
     */
    protected $generatePdfHelper;

    /**
     * @var AbstractCollection
     */
    protected $abstractCollection;

    /**
     * AbstractMassAction constructor.
     *
     * @param Context $context
     * @param Filter $filter
     * @param FileFactory $fileFactory
     * @param ForwardFactory $resultForwardFactory
     * @param GeneratePdf $generatePdfHelper
     */
    public function __construct(
        Context $context,
        Filter $filter,
        FileFactory $fileFactory,
        ForwardFactory $resultForwardFactory,
        GeneratePdf $generatePdfHelper
    ) {
        $this->fileFactory = $fileFactory;
        $this->generatePdfHelper = $generatePdfHelper;
        parent::__construct($context, $filter);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function generateFile()
    {
        $templateId = $this->getRequest()->getParam('template_id', null);
        if ($templateId == "null" || $templateId == 0) {
            $templateId = null;
        }
        $pdf = $this->generatePdfHelper->generatePdfForCollection($this->abstractCollection, $templateId);
        if ($pdf === false) {
            $this->messageManager->addErrorMessage(__('Did you specify a default template? No PDF Template found or there are no printable documents related to selected orders.'));
            return $this->_redirect($this->_redirect->getRefererUrl());
        }

        $file = $this->fileFactory->create(
            $pdf['filename'],
            $pdf['output'],
            DirectoryList::TMP,
            'application/pdf'
        );

        return $file;
    }

    /**
     * @return bool
     */
    //@codingStandardsIgnoreLine
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Magento_Sales::sales_order');
    }
}
