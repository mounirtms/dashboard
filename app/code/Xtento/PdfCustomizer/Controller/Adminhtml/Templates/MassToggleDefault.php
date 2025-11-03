<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-19T17:03:40+00:00
 * File:          Controller/Adminhtml/Templates/MassToggleDefault.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Templates;

use Magento\Framework\Controller\ResultFactory;

class MassToggleDefault extends MassAction
{
    /**
     * Execute action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     * @throws \Magento\Framework\Exception\LocalizedException|\Exception
     */
    public function execute()
    {
        $collection = $this->filter->getCollection($this->templateCollectionFactory->create());

        foreach ($collection as $item) {
            if ($item->getTemplateDefault()) {
                $item->setTemplateDefault(false);
            } else {
                $item->setTemplateDefault(true);
            }
            //@codingStandardsIgnoreLine
            $item->save();
        }

        $this->messageManager->addSuccessMessage(
            __('A total of %1 record(s) "default" status have been toggled.', $collection->getSize())
        );

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath('*/*/');
    }
}
