<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-19T17:03:40+00:00
 * File:          Controller/Adminhtml/Templates/Delete.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */
namespace Xtento\PdfCustomizer\Controller\Adminhtml\Templates;

use Xtento\PdfCustomizer\Controller\Adminhtml\Templates;
use Magento\Backend\App\Action;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Xtento\PdfCustomizer\Model\PdfTemplateRepository as TemplateRepository;

class Delete extends Templates
{

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * @var TemplateRepository
     */
    private $templateRepository;

    /**
     * Delete constructor.
     * @param Action\Context $context
     * @param PageFactory $resultPageFactory
     * @param Registry $registry
     * @param TemplateRepository $templateRepository
     */
    public function __construct(
        Action\Context $context,
        PageFactory $resultPageFactory,
        Registry $registry,
        TemplateRepository $templateRepository
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->templateRepository = $templateRepository;
        parent::__construct($context, $registry);
    }

    /**
     * Delete action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam('template_id');
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($id) {
            try {
                $this->templateRepository->deleteById($id);
                $this->messageManager->addSuccessMessage(__('The template has been deleted.'));
                return $resultRedirect->setPath('*/*/');
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                return $resultRedirect->setPath('*/*/edit', ['template_id' => $id]);
            }
        }

        $this->messageManager->addErrorMessage(__('We can\'t find a post to delete.'));
        return $resultRedirect->setPath('*/*/');
    }

    /**
     * Check the permission to run it
     *
     * @return boolean
     */
    //@codingStandardsIgnoreLine
    public function _isAllowed()
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE_SAVE);
    }
}
