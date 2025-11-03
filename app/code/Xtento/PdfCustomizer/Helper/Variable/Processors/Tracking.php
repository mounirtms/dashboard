<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-10-22T14:33:36+00:00
 * File:          Helper/Variable/Processors/Tracking.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Helper\Variable\Processors;

use Magento\Sales\Model\Order;
use Xtento\PdfCustomizer\Helper\Variable\Formatted;
use Xtento\PdfCustomizer\Model\Template\Processor;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject\Factory as DataObject;

/**
 * Class Tracking
 * @package Xtento\PdfCustomizer\Helper\Variable\Processors
 */
class Tracking extends AbstractHelper
{
    /**
     * @var Formatted
     */
    private $formatted;

    /**
     * @var Processor
     */
    public $processor;

    /**
     * @var DataObject
     */
    private $dataObject;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory
     */
    private $shipmentCollectionFactory;

    /**
     * Tracking constructor.
     *
     * @param Context $context
     * @param Processor $processor
     * @param Formatted $formatted
     * @param DataObject $dataObject
     * @param \Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory $shipmentCollectionFactory
     */
    public function __construct(
        Context $context,
        Processor $processor,
        Formatted $formatted,
        DataObject $dataObject,
        \Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory $shipmentCollectionFactory
    ) {
        $this->formatted = $formatted;
        $this->processor = $processor;
        $this->dataObject = $dataObject;
        $this->shipmentCollectionFactory = $shipmentCollectionFactory;
        parent::__construct($context);
    }

    /**
     * @param $source
     * @param $track
     * @param $template
     *
     * @return array|string
     */
    public function variableProcessor($source, $track, $template)
    {
        $transport['track'] = !is_object($track) ? $this->dataObject->create($track) : $track;

        $processor = $this->processor;
        $processor->setVariables($transport);
        $processor->setTemplate($template);

        return $processor->processTemplate($source->getStoreId());
    }

    /**
     * @param $source
     * @param $templateModel
     * @return string
     */
    public function process($source, $templateModel)
    {
        $templateHtml = $templateModel->getTemplateHtml();
        $templateMatchedParts = $this->formatted->getTemplateAreas(
            $templateHtml,
            '##tracking_start##',
            '##tracking_end##'
        );

        if (empty($templateMatchedParts)) {
            return $templateHtml;
        }

        $tracks = [];
        if ($source instanceof Order\Shipment) {
            // Shipment
            $tracks = $source->getAllTracks();
        } else {
            // Order, invoice, ...
            if ($source instanceof Order) {
                $order = $source;
            } else {
                $order = $source->getOrder();
            }
            $shipments = $this->shipmentCollectionFactory->create()
                ->addAttributeToFilter('order_id', $order->getId())
                ->load();
            foreach ($shipments as $shipment) {
                foreach ($shipment->getAllTracks() as $track) {
                    $tracks[] = $track;
                }
            }
        }

        foreach ($templateMatchedParts as $templatePart) {
            $newHtml = '';
            foreach ($tracks as $track) {
                $templateParts = $this->dataObject->create(
                    [
                        'template_html_full' => $templateModel->getTemplateHtml(),
                        'template_html' => $templatePart
                    ]
                );
                $processedTemplate = $this->variableProcessor($source, $track, $templateParts);
                $newHtml .= $processedTemplate['body'];
            }
            $templateHtml = str_replace($templateMatchedParts, $newHtml, $templateHtml);
        }

        $templateHtml = str_replace(['##tracking_start##', '##tracking_end##'], '', $templateHtml);
        return $templateHtml;
    }
}