<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Helper/Tools.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Helper;

use Magento\Framework\ObjectManagerInterface;
use Xtento\XtCore\Helper\Utils;

class Tools extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Xtento\PdfCustomizer\Model\PdfTemplateFactory
     */
    protected $templateFactory;

    /**
     * @var Utils
     */
    protected $utilsHelper;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Tools constructor.
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Xtento\PdfCustomizer\Model\PdfTemplateFactory $templateFactory
     * @param Utils $utilsHelper
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Xtento\PdfCustomizer\Model\PdfTemplateFactory $templateFactory,
        Utils $utilsHelper,
        ObjectManagerInterface $objectManager
    ) {
        parent::__construct($context);
        $this->templateFactory = $templateFactory;
        $this->utilsHelper = $utilsHelper;
        $this->objectManager = $objectManager;
    }

    /**
     * @param $templateIds
     *
     * @return string
     */
    public function exportSettingsAsJson($templateIds)
    {
        $exportData = [];
        $exportData['templates'] = [];
        foreach ($templateIds as $templateId) {
            $template = $this->templateFactory->create()->load($templateId);
            if ($template->getId()) {
                $template->unsetData('template_id');
                $exportData['templates'][] = $template->toArray();
            }
        }
        return \Zend_Json::encode($exportData);
    }

    /**
     * @param $jsonData
     * @param array $addedCounter
     * @param array $updatedCounter
     * @param bool $updateByName
     * @param string $errorMessage
     *
     * @return bool
     */
    public function importSettingsFromJson($jsonData, &$addedCounter = [], &$updatedCounter = [], $updateByName = true, &$errorMessage = "")
    {
        try {
            $settingsArray = \Zend_Json::decode($jsonData);
        } catch (\Exception $e) {
            $errorMessage = __('Import failed. Decoding of JSON import format failed.');
            return false;
        }
        // Process templates
        if (isset($settingsArray['templates'])) {
            foreach ($settingsArray['templates'] as $templateData) {
                if ($updateByName) {
                    $templateCollection = $this->templateFactory->create()->getCollection()
                        ->addFieldToFilter('template_type', $templateData['template_type'])
                        ->addFieldToFilter('template_name', $templateData['template_name']);
                    if ($templateCollection->getSize() === 1) {
                        $this->templateFactory->create()->setData($templateData)->setId($templateCollection->getFirstItem()->getId())->save();
                        $updatedCounter['templates']++;
                    } else {
                        $this->templateFactory->create()->setData($templateData)->save();
                        $addedCounter['templates']++;
                    }
                } else {
                    $this->templateFactory->create()->setData($templateData)->save();
                    $addedCounter['templates']++;
                }
            }
        }
        return true;
    }
}
