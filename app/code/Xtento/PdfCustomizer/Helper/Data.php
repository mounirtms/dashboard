<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2020-07-08T20:47:02+00:00
 * File:          Helper/Data.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Helper;

use Magento\Store\Model\StoreManagerInterface;
use Xtento\PdfCustomizer\Model\Files\Synchronization;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Xtento\PdfCustomizer\Model\ResourceModel\PdfTemplate\Collection;
use Xtento\PdfCustomizer\Model\ResourceModel\PdfTemplate\CollectionFactory as TemplateCollectionFactory;
use Xtento\PdfCustomizer\Model\Source\AbstractSource;
use Xtento\PdfCustomizer\Model\Source\TemplateActive;
use Mpdf\Mpdf;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Magento\Catalog\Model\Product;

/**
 * Handles the config and other settings
 *
 * Class Data
 * @package Xtento\PdfCustomizer\Helper
 */
class Data extends AbstractHelper
{
    const ENABLE_ORDER = 'xtento_pdfcustomizer/order/enabled';
    const EMAIL_ORDER = 'xtento_pdfcustomizer/order/email';

    const ENABLE_INVOICE = 'xtento_pdfcustomizer/invoice/enabled';
    const EMAIL_INVOICE = 'xtento_pdfcustomizer/invoice/email';

    const ENABLE_SHIPMENT = 'xtento_pdfcustomizer/shipment/enabled';
    const EMAIL_SHIPMENT = 'xtento_pdfcustomizer/shipment/email';

    const ENABLE_CREDITMEMO = 'xtento_pdfcustomizer/creditmemo/enabled';
    const EMAIL_CREDITMEMO = 'xtento_pdfcustomizer/creditmemo/email';

    const ENABLE_PRODUCT = 'xtento_pdfcustomizer/product/enabled';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $config;

    /**
     * @var Collection
     */
    private $templateCollection;

    /**
     * @var Module
     */
    private $moduleHelper;

    /**
     * @var Synchronization
     */
    private $synchronization;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param TemplateCollectionFactory $_templateCollection
     * @param Module $moduleHelper
     * @param StoreManagerInterface $storeManager
     * @param Synchronization $synchronization
     */
    public function __construct(
        Context $context,
        TemplateCollectionFactory $_templateCollection,
        Module $moduleHelper,
        StoreManagerInterface $storeManager,
        Synchronization $synchronization
    ) {
        $this->templateCollection = $_templateCollection;
        $this->config             = $context->getScopeConfig();
        $this->moduleHelper       = $moduleHelper;
        $this->storeManager = $storeManager;
        $this->synchronization    = $synchronization;
        parent::__construct($context);
    }

    /**
     * @param string $node
     * @param null $storeId
     *
     * @return bool|string
     */
    public function isAttachToEmailEnabled($node = self::EMAIL_INVOICE, $storeId = null)
    {
        $enableNode = str_replace('email', 'enabled', $node);

        if ($this->isEnabled($enableNode, $storeId)) {
            return $this->getConfig($node, $storeId);
        }

        return false;
    }

    /**
     * @param bool $node
     * @param null $storeId
     *
     * @return bool|string
     */
    public function isEnabled($node = false, $storeId = null)
    {
        if (!$this->moduleHelper->isModuleEnabled()) {
            return false;
        }

        if (!class_exists(Mpdf::class)) {
            return false;
        }

        if (!extension_loaded('mbstring')) {
            return false;
        }

        if (empty($this->collection())) {
            return false;
        }

        if ($node !== false) {
            // Only if a specific entity is supposed to be checked
            return $this->getConfig($node, $storeId);
        }

        return true;
    }

    /**
     * @param $configPath
     * @param null $storeId
     *
     * @return mixed
     */
    public function getConfig($configPath, $storeId = null)
    {
        return $this->config->getValue(
            $configPath,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @param $source
     * @param int $type
     * @return \Magento\Framework\DataObject
     */
    public function getDefaultTemplate($source, $type = TemplateType::TYPE_ORDER)
    {
        if ($source instanceof Order || $source instanceof Product) {
            $store = $source->getStoreId();
        } else {
            $store = $source->getOrder()->getStoreId();
        }

        $collection = $this->collection();
        if (!$this->storeManager->isSingleStoreMode()) {
            $collection->addStoreFilter($store);
        }
        $collection->addFieldToFilter(
            'is_active',
            TemplateActive::STATUS_ENABLED
        );
        $collection->addFieldToFilter(
            'template_default',
            AbstractSource::IS_DEFAULT
        );
        $collection->addFieldToFilter(
            'template_type',
            $type
        );
        $lastItem = $collection->getLastItem();

        if (!$lastItem || !$lastItem->getId()) {
            // Try and see if there is one template that isn't default but matches store filter.
            $collection = $this->collection();
            if (!$this->storeManager->isSingleStoreMode()) {
                $collection->addStoreFilter($store);
            }
            $collection->addFieldToFilter(
                'is_active',
                TemplateActive::STATUS_ENABLED
            );
            $collection->addFieldToFilter(
                'template_type',
                $type
            );
            $lastItem = $collection->getLastItem();
        }
        return $lastItem;
    }

    /**
     * @return Collection
     */
    public function collection()
    {
        $collection = $this->templateCollection->create();
        return $collection;
    }

    /**
     * @return Module
     */
    public function getModuleHelper()
    {
        return $this->moduleHelper;
    }
}
