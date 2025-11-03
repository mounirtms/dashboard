<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2020-07-27T10:57:31+00:00
 * File:          Block/Adminhtml/Sales/Order/PrintPdf.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Block\Adminhtml\Sales\Order;

use Xtento\PdfCustomizer\Helper\Data;
use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Registry;
use Xtento\PdfCustomizer\Model\ResourceModel\PdfTemplate\CollectionFactory;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Xtento\PdfCustomizer\Model\Source\TemplateActive;

class PrintPdf extends Container
{
    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * Core registry
     *
     * @var Registry
     */
    private $coreRegistry = null;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * PrintPdf constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param Data $dataHelper
     * @param CollectionFactory $collectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        Data $dataHelper,
        CollectionFactory $collectionFactory,
        array $data = []
    ) {
        $this->coreRegistry = $registry;
        $this->dataHelper = $dataHelper;
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context, $data);
    }

    public function _construct()
    {
        if (!$this->dataHelper->isEnabled(Data::ENABLE_ORDER)) {
            return $this;
        }

        $addButtonProps = [
            'id' => 'print_pdf',
            'label' => __('Print PDF'),
            'button_class' => 'print',
            'class_name' => 'Magento\Backend\Block\Widget\Button\SplitButton',
            'options' => $this->templateOptions(),
        ];
        $this->buttonList->add('print_pdf', $addButtonProps);

        parent::_construct();
    }

    /**
     * @return array
     */
    public function templateOptions()
    {
        $order = $this->getOrder();
        $splitButtonOptions = [];

        $templateCollection = $this->collectionFactory
            ->create()
            ->addFieldToFilter('template_type', ['neq' => TemplateType::TYPE_PRODUCT])
            ->addFieldToFilter('template_type', ['neq' => TemplateType::TYPE_SECONDARY_ATTACHMENT])
            ->addFieldToFilter('is_active', ['eq' => TemplateActive::STATUS_ENABLED]);
        $templateCollection->addStoreFilter($order->getStoreId());

        if (empty($templateCollection)) {
            return [];
        }

        $templateTypes = [];
        foreach ($templateCollection as $template) {
            if (!in_array($template->getTemplateType(), $templateTypes)) {
                array_push($templateTypes, $template->getTemplateType());
            }
        }

        foreach ($templateTypes as $templateType) {
            $entity = TemplateType::TYPES[$templateType];
            $templateTypeName = __(ucwords($entity));

            $hasTemplatesOfThisType = false;
            $templateCount = 0;
            foreach ($templateCollection as $template) {
                if ($template->getTemplateType() == $templateType) {
                    $templateCount++;
                    $hasTemplatesOfThisType = true;
                }
            }
            if (!$hasTemplatesOfThisType) {
                continue;
            }

            if ($templateType == TemplateType::TYPE_INVOICE && !$order->hasInvoices()) {
                continue;
            }
            if ($templateType == TemplateType::TYPE_SHIPMENT && !$order->hasShipments()) {
                continue;
            }
            if ($templateType == TemplateType::TYPE_CREDIT_MEMO && !$order->hasCreditmemos()) {
                continue;
            }

            if ($templateCount > 1) {
                $splitButtonOptions[] = [
                    'label' => __('%1: Default Template', $templateTypeName),
                    'onclick' => 'setLocation(\'' . $this->getPdfPrintUrl($templateType, null) . '\')'
                ];
            }

            foreach ($templateCollection as $template) {
                if ($templateType !== $template->getTemplateType()) {
                    continue;
                }

                $label =  $templateTypeName . ': ' . $template->getTemplateName();
                if ($templateCount === 1) {
                    $label = $templateTypeName;
                }
                $templateId = $template->getTemplateId();
                $splitButtonOptions[] = [
                    'label' => $label,
                    'onclick' => 'setLocation(\'' . $this->getPdfPrintUrl($templateType, $templateId) . '\')'
                ];
            }
        }

        return $splitButtonOptions;
    }

    /**
     * @return string
     */
    public function getPdfPrintUrl($entity, $id)
    {
        return $this->getUrl(
            'xtento_pdf/*/printpdf',
            [
                'entity' => $entity,
                'template_id' => $id,
                'order_id' => $this->getOrderId()
            ]
        );
    }

    /**
     * @return integer
     */
    public function getOrderId()
    {
        return $this->getOrder()->getId();
    }

    /**
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->coreRegistry->registry('sales_order');
    }
}
