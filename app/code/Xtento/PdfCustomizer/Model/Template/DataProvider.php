<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-19T17:03:40+00:00
 * File:          Model/Template/DataProvider.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Model\Template;

use Xtento\PdfCustomizer\Model\ResourceModel\PdfTemplate\CollectionFactory as templateCollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Class DataProvider
 */
class DataProvider extends AbstractDataProvider
{
    /**
     * @var \Xtento\PdfCustomizer\Model\ResourceModel\PdfTemplate\Collection
     */
    public $collection;

    /**
     * @var DataPersistorInterface
     */
    private $dataPersistor;

    /**
     * @var array
     */
    private $loadedData;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param templateCollectionFactory $templateCollectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        templateCollectionFactory $templateCollectionFactory,
        DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $templateCollectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->meta = $this->prepareMeta($this->meta);
    }

    /**
     * Prepares Meta
     *
     * @param array $meta
     * @return array
     */
    public function prepareMeta(array $meta)
    {
        return $meta;
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $templates = $this->collection->getItems();
        /** @var $template \Xtento\PdfCustomizer\Model\PdfTemplate */
        foreach ($templates as $template) {
            $this->loadedData[$template->getId()] = $template->getData();
        }

        $data = $this->dataPersistor->get('pdfcustomizer_template');
        if (!empty($data)) {
            $template = $this->collection->getNewEmptyItem();
            $template->setData($data);
            $this->loadedData[$template->getId()] = $template->getData();
            $this->dataPersistor->clear('pdfcustomizer_template');
        }

        return $this->loadedData;
    }
}
