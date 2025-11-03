<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2021-04-03T04:31:49+00:00
 * File:          Ui/Plugin/Component/MassActionPlugin.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Ui\Plugin\Component;

use Xtento\PdfCustomizer\Helper\Data;
use Xtento\PdfCustomizer\Model\PdfTemplate;
use Xtento\PdfCustomizer\Model\ResourceModel\PdfTemplate\CollectionFactory;
use Xtento\PdfCustomizer\Model\ResourceModel\PdfTemplate\Collection as PdfCollection;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Xtento\PdfCustomizer\Model\Source\TemplateActive;
use Magento\Backend\Helper\Data as AdminhtmlData;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Registry;
use Magento\Ui\Component\MassAction;

/**
 * Class MassActionPlugin
 * @package Xtento\ProductExport\Ui\Plugin\Component
 */
class MassActionPlugin
{
    /**
     * @var Data
     */
    private $moduleHelper;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * Adminhtml data
     *
     * @var AdminhtmlData
     */
    private $adminhtmlData = null;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * MassActionPlugin constructor.
     *
     * @param Data $moduleHelper
     * @param RequestInterface $request
     * @param ScopeConfigInterface $config
     * @param Registry $registry
     * @param AuthorizationInterface $authorization
     * @param AdminhtmlData $adminhtmlData
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        Data $moduleHelper,
        RequestInterface $request,
        ScopeConfigInterface $config,
        Registry $registry,
        AuthorizationInterface $authorization,
        AdminhtmlData $adminhtmlData,
        CollectionFactory $collectionFactory
    ) {
        $this->moduleHelper      = $moduleHelper;
        $this->request           = $request;
        $this->scopeConfig       = $config;
        $this->registry          = $registry;
        $this->authorization     = $authorization;
        $this->adminhtmlData     = $adminhtmlData;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Add massactions to various grids in the admin panel
     * Why not via XML? Because then you cannot select the actions which should be shown from
     * the Magento admin, this is required so admins can adjust the actions via the configuration.
     *
     * @param MassAction $subject
     * @param string $interceptedOutput
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    // @codingStandardsIgnoreStart
    public function afterPrepare($subject, $interceptedOutput)
    {
        $dataProvider = $subject->getContext()->getDataProvider()->getName();
        preg_match('/(sales\_(?<salesGrid>.*)\_grid)|((?<catalogGrid>.*)\_listing_data)/', $dataProvider, $dataProviderMatches);

        $salesGridType = false;
        $catalogGridType = false;
        if (isset($dataProviderMatches['catalogGrid']) && !empty($dataProviderMatches['catalogGrid']) && $dataProviderMatches['catalogGrid'] === 'product') {
            $catalogGridType = $dataProviderMatches['catalogGrid'];
        }
        if (isset($dataProviderMatches['salesGrid']) && !empty($dataProviderMatches['salesGrid'])) {
            $salesGridType = $dataProviderMatches['salesGrid'];
        }
        if (!$salesGridType && !$catalogGridType) {
            return;
        }

        $config = $subject->getData('config');
        if (!isset($config['component']) || strstr($config['component'], 'tree') === false) {
            // Temporary until added to core to support multi-level selects
            $config['component'] = 'Magento_Ui/js/grid/tree-massactions';
        }
        if (!isset($config['actions'])) {
            $config['actions'] = [];
        }

        if ($salesGridType && !$this->authorization->isAllowed('Magento_Sales::sales_order')) {
            // Do not add bulk actions and remove added bulk actions
            $config['actions'] = $this->removeBulkActions($config['actions'], [], true);
            $subject->setData('config', $config);
            return;
        }

        $this->moduleHelper->getModuleHelper()->confirmEnabled(true);
        $isModuleEnabled = $this->moduleHelper->getModuleHelper()->isModuleEnabled();

        // Product grid
        if ($catalogGridType && $this->authorization->isAllowed('Magento_Catalog::products')) {
            if (!$isModuleEnabled || !$this->moduleHelper->isEnabled(Data::ENABLE_PRODUCT)) {
                return;
            }

            /** @var PdfCollection $templateCollection */
            $templateCollection = $this->collectionFactory
                ->create()
                ->addFieldToSelect('*')
                ->addFieldToFilter(
                    'template_type', [
                    'eq' => TemplateType::TYPE_PRODUCT
                ])
                ->addFieldToFilter(
                    'is_active', [
                    'eq' => TemplateActive::STATUS_ENABLED
                ])
                ->getItems();

            if (empty($templateCollection)) {
                return;
            }
            $config['actions'] = $this->addCatalogExportAction($config['actions'], $templateCollection);
        }

        // Sales grids, remove bulk actions if PDF type is not enabled
        if ($salesGridType) {
            $pdfTypes = ['order', 'invoice', 'shipment', 'creditmemo'];
            foreach ($pdfTypes as $pdfType) {
                if (!$isModuleEnabled || !$this->moduleHelper->isEnabled(str_replace('order', $pdfType, Data::ENABLE_ORDER))) {
                    $actionsToRemove = [
                        'pdf_customizer_' . $pdfType,
                        'pdf_customizer_order_' . $pdfType
                    ];
                    if ($pdfType === 'order') {
                        $actionsToRemove[] = 'pdf_customizer_all';
                    }
                    $config['actions'] = $this->removeBulkActions($config['actions'], $actionsToRemove);
                }
            }

            $disabledActions = [];
            if ($isModuleEnabled && $this->scopeConfig->isSetFlag('xtento_pdfcustomizer/advanced/disable_default_bulk_actions')) {
                $disabledActions = ['pdfinvoices_order', 'pdfshipments_order', 'pdfcreditmemos_order', 'pdfdocs_order'];
            }
            if ($isModuleEnabled && $this->scopeConfig->isSetFlag('xtento_pdfcustomizer/advanced/disable_print_label_action')) {
                $disabledActions[] = 'print_shipping_label';
            }
            if (!empty($disabledActions)) {
                foreach ($config['actions'] as $key => $action) {
                    if (isset($action['type']) && in_array($action['type'], $disabledActions)) {
                        unset($config['actions'][$key]);
                    }
                }
                $config['actions'] = array_values($config['actions']); // Required to restore (sorted) keys in array, somehow needed by Magento
            }
        }

        $subject->setData('config', $config);
    }

    /**
     * @param $configActions
     * @param $bulkActionsToRemove
     * @param bool $removeAll
     *
     * @return mixed
     */
    private function removeBulkActions($configActions, $bulkActionsToRemove, $removeAll = false)
    {
        foreach ($configActions as $configId => $configAction) {
            if (!isset($configAction['type'])) {
                continue;
            }
            if ($removeAll && stristr($configAction['type'], 'pdf_customizer_') !== false) {
                unset($configActions[$configId]);
            }
            if (in_array($configAction['type'], $bulkActionsToRemove)) {
                unset($configActions[$configId]);
            }
        }
        return array_values($configActions);
    }

    /**
     * @param $configActions
     * @param PdfCollection $templateCollection
     * @return array
     */
    private function addCatalogExportAction($configActions, $templateCollection)
    {
        $subActions = [];
        /** @var PdfTemplate $item */
        foreach ($templateCollection as $item) {
            $subActions[] = [
                'type' => 'pdf_' . $item->getData('template_id'),
                'label' => $item['template_name'],
                'url' => $this->adminhtmlData->getUrl(
                    'xtento_pdf/product_massaction/printpdf/',
                    [
                        'template_id' => $item->getData('template_id'),
                    ]
                )
            ];
        }

        $configActions[] = [
            'type' => 'pdfcustomizer_productpdf',
            'label' => __('PDF Catalog'),
            'actions' => $subActions
        ];

        return $configActions;
    }
}
