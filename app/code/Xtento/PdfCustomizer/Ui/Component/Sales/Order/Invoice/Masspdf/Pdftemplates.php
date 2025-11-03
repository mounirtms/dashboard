<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2022-04-15T19:12:16+00:00
 * File:          Ui/Component/Sales/Order/Invoice/Masspdf/Pdftemplates.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Ui\Component\Sales\Order\Invoice\Masspdf;

use Xtento\PdfCustomizer\Helper\Data;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Xtento\PdfCustomizer\Model\ResourceModel\PdfTemplate\CollectionFactory;
use Xtento\PdfCustomizer\Model\Source\TemplateActive;
use Magento\Framework\UrlInterface;
use Laminas\Stdlib\JsonSerializable;

class Pdftemplates implements JsonSerializable
{
    /**
     * @var array
     */
    private $options;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * Additional options params
     *
     * @var array
     */
    private $data;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * Base URL for subactions
     *
     * @var string
     */
    private $urlPath;

    /**
     * Param name for subactions
     *
     * @var string
     */
    private $paramName;

    /**
     * Additional params for subactions
     *
     * @var array
     */
    private $additionalData = [];

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;

    /**
     * Pdftemplates constructor.
     *
     * @param CollectionFactory $collectionFactory
     * @param UrlInterface $urlBuilder
     * @param Data $helper
     * @param \Magento\Framework\App\RequestInterface $request
     * @param array $data
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        UrlInterface $urlBuilder,
        Data $helper,
        \Magento\Framework\App\RequestInterface $request,
        array $data = []
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->helper = $helper;
        $this->data = $data;
        $this->urlBuilder = $urlBuilder;
        $this->request = $request;
    }

    /**
     * Get action options
     *
     * @return array
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $options = [];

        $message = [
            0 =>
                [
                    'type' => 'disabled',
                    'label' => __('The extension is disabled, or you disabled this PDF type, or you did not set up any PDF Templates at Stores > PDF Templates yet.')
                ]
        ];

        if (!$this->helper->isEnabled(\Xtento\PdfCustomizer\Helper\Data::ENABLE_INVOICE)) {
            return $message;
        }

        if ($this->options === null) {
            // get the massaction data from the database table
            $templateCollection = $this->collectionFactory
                ->create()
                ->addFieldToFilter('template_type', [
                    'eq' => TemplateType::TYPE_INVOICE
                ])
                ->addFieldToFilter('is_active', [
                    'eq' => TemplateActive::STATUS_ENABLED
                ]);

            if (empty($templateCollection)) {
                return $this->options;
            }

            if ($templateCollection->count() > 1) {
                $options[] = [
                    'label' => __('Default Template'),
                    'value' => 'null'
                ];
            } else if ($templateCollection->count() === 1) {
                return $this->options;
            }

            foreach ($templateCollection as $template) {
                $options[] = [
                    'label' => $template->getData('template_name'),
                    'value' => $template->getData('template_id')
                ];
            }

            $this->prepareData();

            if (empty($options)) {
                return $message;
            }

            foreach ($options as $option) {
                $this->options[$option['value']] = [
                    'type' => 'template_' . $option['value'],
                    'label' => $option['label'],
                ];

                if ($this->urlPath && $this->paramName) {
                    $params = [$this->paramName => $option['value']];
                    if ($this->request->getParam('order_id', false)) {
                        $params['order_id'] = $this->request->getParam('order_id');
                    }
                    $this->options[$option['value']]['url'] = $this->urlBuilder->getUrl(
                        $this->urlPath,
                        $params
                    );
                }

                $this->options[$option['value']] = array_merge_recursive(
                    $this->options[$option['value']],
                    $this->additionalData
                );
            }

            $this->options = array_values($this->options);
        }

        return $this->options;
    }

    /**
     * Prepare addition data for subactions
     *
     * @return void
     */
    private function prepareData()
    {

        foreach ($this->data as $key => $value) {
            switch ($key) {
                case 'urlPath':
                    $this->urlPath = $value;
                    break;
                case 'paramName':
                    $this->paramName = $value;
                    break;
                default:
                    $this->additionalData[$key] = $value;
                    break;
            }
        }
    }
}
