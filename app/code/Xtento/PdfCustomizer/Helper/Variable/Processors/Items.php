<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2022-08-14T18:56:35+00:00
 * File:          Helper/Variable/Processors/Items.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Helper\Variable\Processors;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Xtento\PdfCustomizer\Helper\AbstractPdf;
use Xtento\PdfCustomizer\Helper\Variable\Custom\Items as CustomItems;
use Xtento\PdfCustomizer\Helper\Variable\Custom\Product as CustomProduct;
use Xtento\PdfCustomizer\Helper\Variable\Formatted;
use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Xtento\PdfCustomizer\Model\Template\ProcessorFactory;
use Magento\Catalog\Model\Product\Type;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject\Factory as DataObject;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\ItemFactory as OrderItemFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Xtento\XtCore\Helper\Utils;

/**
 * Class Items
 * @package Xtento\PdfCustomizer\Helper\Variable\Processors
 * @SuppressWarnings(CouplingBetweenObjects)
 */
class Items extends AbstractHelper
{
    /**
     * @var Formatted
     */
    private $formatted;

    /**
     * @var CustomItems
     */
    private $customData;

    /**
     * @var Processor
     */
    public $processor;

    /**
     * @var DataObject
     */
    private $dataObject;

    /**
     * @var CustomProduct
     */
    private $customProduct;

    /**
     * @var OrderItemFactory
     */
    private $orderItemFactory;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var \Magento\Directory\Model\Currency
     */
    private $currency;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var Utils
     */
    private $utilsHelper;

    /**
     * Items constructor.
     *
     * @param Context $context
     * @param ProcessorFactory $processor
     * @param Formatted $formatted
     * @param CustomItems $customData
     * @param DataObject $dataObject
     * @param CustomProduct $customProduct
     * @param OrderItemFactory $orderItemFactory
     * @param ProductRepositoryInterface $productRepository
     * @param \Magento\Directory\Model\Currency $currency
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param Utils $utilsHelper
     */
    public function __construct(
        Context $context,
        ProcessorFactory $processor,
        Formatted $formatted,
        CustomItems $customData,
        DataObject $dataObject,
        CustomProduct $customProduct,
        OrderItemFactory $orderItemFactory,
        ProductRepositoryInterface $productRepository,
        \Magento\Directory\Model\Currency $currency,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        Utils $utilsHelper
    ) {
        $this->formatted = $formatted;
        $this->customData = $customData;
        $this->processor = $processor;
        $this->dataObject = $dataObject;
        $this->customProduct = $customProduct;
        $this->orderItemFactory = $orderItemFactory;
        $this->productRepository = $productRepository;
        $this->currency = $currency;
        $this->objectManager = $objectManager;
        $this->utilsHelper = $utilsHelper;
        parent::__construct($context);
    }

    /**
     * @param $source
     * @param $itemObject
     * @param $template
     *
     * @return array|string
     */
    public function variableItemProcessor($source, $itemObject, $template)
    {
        $templateHtml = $template->getTemplateHtmlFull();

        $transport = [];

        $getAllVariables = $template->getData('get_all_variables') === true;

        // Order / invoice should always be available
        $templateType = $template->getData('template_model')->getData('template_type');
        $templateTypeName = TemplateType::TYPES[$templateType]; // order, invoice, ...
        if (strstr($templateHtml, $templateTypeName) !== false) {
            $transport[$templateTypeName] = $source;
        }

        if (strstr($templateHtml, 'order') !== false) {
            if ($source->getOrder()) {
                $transport['order'] = $source->getOrder();
            } else {
                $transport['order'] = $source;
            }
        }

        $storeId = $source->getStoreId();
        if (!$storeId && $source && $source->getOrder()) {
            $storeId = $source->getOrder()->getStoreId();
        }

        // Performance improvement: Only load variables that are actually required
        // Item
        if ($getAllVariables
            || strstr($templateHtml, ' item.') !== false
            || strstr($templateHtml, '$item') !== false
            || strstr($templateHtml, ' formatted_item.') !== false
            || strstr($templateHtml, ' item_if.') !== false
        ) {
            /** @var Item $orderItem */
            $item = $this->customData->entity($itemObject)->processAndReadVariables();
        }
        if ($getAllVariables || strstr($templateHtml, ' item.') !== false) {
            $transport['item'] = $item;
        }
        if ($getAllVariables || strstr($templateHtml, ' formatted_item.') !== false) {
            $transport['formatted_item'] = $this->formatted->getFormatted($item);
        }
        if (strstr($templateHtml, ' item_if.') !== false) {
            $transport['item_if'] = $this->formatted->getZeroFormatted($item);
        }
        // Order Item
        if ($getAllVariables
            || strstr($templateHtml, ' order_item.') !== false
            || strstr($templateHtml, ' formatted_order_item.') !== false
            || strstr($templateHtml, ' order_item_if.') !== false
            || strstr($templateHtml, ' giftmessage.') !== false
        ) {
            if (!isset($item)) {
                /** @var Item $orderItem */
                $item = $this->customData->entity($itemObject)->processAndReadVariables();
            }
            $orderItem = $this->orderItem($item);
        }
        if ($getAllVariables || strstr($templateHtml, ' order_item.') !== false) {
            $transport['order_item'] = $orderItem;
        }
        if ($getAllVariables || strstr($templateHtml, ' formatted_order_item.') !== false) {
            $transport['formatted_order_item'] = $this->formatted->getFormatted($orderItem);
        }
        if (strstr($templateHtml, ' order_item_if.') !== false) {
            $transport['order_item_if'] = $this->formatted->getZeroFormatted($orderItem);
        }
        if ($getAllVariables || strstr($templateHtml, ' giftmessage.') !== false) {
            $transport['giftmessage'] = $this->formatted->getOrderGiftMessageArray($orderItem);
        }
        // Product
        if ($getAllVariables
            || strstr($templateHtml, 'order_item_product.') !== false
            || strstr($templateHtml, ' formatted_order_item_product.') !== false
            || strstr($templateHtml, ' order_item_product_if.') !== false
        ) {
            if (!isset($orderItem)) {
                if (!isset($item)) {
                    /** @var Item $orderItem */
                    $item = $this->customData->entity($itemObject)->processAndReadVariables();
                }
                $orderItem = $this->orderItem($item);
            }
            // Required to get product attributes for correct store
            $product = $orderItem->getProduct();
            if ($product && $product->getId()) {
                $productCopy = clone $product;
                $productCopy->clearInstance()->setStoreId($orderItem->getStoreId())->load($product->getId());
                $orderItemProduct = $this->customProduct->entity($productCopy)->processAndReadVariables();
            } else {
                $orderItemProduct = false;
            }
        }
        if ($getAllVariables || strstr($templateHtml, ' order_item_product.') !== false) {
            $transport['order_item_product'] = $orderItemProduct;
        }
        if ($getAllVariables || strstr($templateHtml, ' formatted_order_item_product.') !== false) {
            $transport['formatted_order_item_product'] = $this->formatted->getFormatted($orderItemProduct);
        }
        if (strstr($templateHtml, ' order_item_product_if.') !== false) {
            $transport['order_item_product_if'] = $this->formatted->getZeroFormatted($orderItemProduct);
        }

        if (strstr($templateHtml, 'barcode_') !== false) {
            // Barcode variables
            if (!isset($orderItem)) {
                if (!isset($item)) {
                    /** @var Item $orderItem */
                    $item = $this->customData->entity($itemObject)->processAndReadVariables();
                }
                $orderItem = $this->orderItem($item);
            }
            foreach (AbstractPdf::CODE_BAR as $code) {
                if (strstr($templateHtml, 'barcode_' . $code . '_item') !== false) {
                    $transport['barcode_' . $code . '_item'] = $this->formatted->getBarcodeFormatted($item, $code);
                }
                if (strstr($templateHtml, 'barcode_' . $code . '_order_item') !== false) {
                    $transport['barcode_' . $code . '_order_item'] = $this->formatted->getBarcodeFormatted($orderItem, $code);
                }
                if (strstr($templateHtml, 'barcode_' . $code . '_order_item_product') !== false) {
                    $transport['barcode_' . $code . '_order_item_product'] = $this->formatted->getBarcodeFormatted($orderItemProduct, $code);
                }
            }
        }

        // Load child data of configurable
        if ($getAllVariables
            || strstr($templateHtml, 'child_item.') !== false
            || strstr($templateHtml, 'child_order_item.') !== false
            || strstr($templateHtml, 'child_order_item_product.') !== false) {
            if (false === ($source instanceof Order)) {
                // Invoice, ... - load order items
                $allItems = $source->getOrder()->getItems();
                $itemId = $itemObject->getOrderItemId();
                $parentOrderItem = $this->orderItemFactory->create()->load($itemId);
            } else {
                $allItems = $source->getItems();
                $itemId = $itemObject->getId();
                $parentOrderItem = $itemObject;
            }
            if ($parentOrderItem->getProductType() == 'configurable' || $parentOrderItem->getProductType() == 'bundle') {
                foreach ($allItems as $tempItem) {
                    if ($tempItem->getParentItemId() === $itemId) {
                        // Is child of parent item
                        break 1;
                    }
                }
            }
            if (isset($tempItem)) {
                if ($getAllVariables || strstr($templateHtml, 'child_item.') !== false) {
                    $data = $this->customData->entity($tempItem)->processAndReadVariables();
                    $transport['child_item'] = $data;
                    if (strstr($templateHtml, 'barcode_') !== false) {
                        foreach (AbstractPdf::CODE_BAR as $code) {
                            if (strstr($templateHtml, 'barcode_' . $code . '_child_item') !== false) {
                                $transport['barcode_' . $code . '_child_item'] = $this->formatted->getBarcodeFormatted($data, $code);
                            }
                        }
                    }
                    $transport['formatted_child_item'] = $this->formatted->getFormatted($data);
                }
                $tempOrderItem = $this->orderItem($tempItem);
                if ($getAllVariables || strstr($templateHtml, 'child_order_item.') !== false) {
                    $transport['child_order_item'] = $tempOrderItem;
                    if (strstr($templateHtml, 'barcode_') !== false) {
                        foreach (AbstractPdf::CODE_BAR as $code) {
                            if (strstr($templateHtml, 'barcode_' . $code . '_child_order_item') !== false) {
                                $transport['barcode_' . $code . '_child_order_item'] = $this->formatted->getBarcodeFormatted($tempOrderItem, $code);
                            }
                        }
                    }
                    $transport['formatted_child_order_item'] = $this->formatted->getFormatted($tempOrderItem);
                }
                if ($getAllVariables || strstr($templateHtml, 'child_order_item_product.') !== false) {
                    $orderItemProduct = $tempOrderItem->getProduct();
                    if ($orderItemProduct && $orderItemProduct->getId()) {
                        $orderItemProductCopy = clone $orderItemProduct;
                        $orderItemProductCopy->clearInstance()->setStoreId($storeId)->load($orderItemProduct->getId());
                        $data = $this->customProduct->entity($orderItemProductCopy)->processAndReadVariables();
                    } else {
                        $data = false;
                    }
                    $transport['child_order_item_product'] = $data;
                    if (strstr($templateHtml, 'barcode_') !== false) {
                        foreach (AbstractPdf::CODE_BAR as $code) {
                            if (strstr($templateHtml, 'barcode_' . $code . '_child_order_item_product') !== false) {
                                $transport['barcode_' . $code . '_child_order_item_product'] = $this->formatted->getBarcodeFormatted($data, $code);
                            }
                        }
                    }
                    $transport['formatted_child_order_item_product'] = $this->formatted->getFormatted($data);
                }
            }
        }

        // Parent item
        if ($getAllVariables
            || strstr($templateHtml, 'parent_item.') !== false
            || strstr($templateHtml, 'parent_order_item.') !== false
            || strstr($templateHtml, 'parent_order_item_product.') !== false) {
            $parentItem = $itemObject->getParentItem();
            if (!$parentItem && $itemObject->getOrderItemId()) {
                $orderItem = $this->orderItemFactory->create()->load($itemObject->getOrderItemId());
                $parentItemId = $orderItem->getParentItemId();
                if ($parentItemId) {
                    $parentItem = $this->orderItemFactory->create()->load($parentItemId);
                }
            }
            if ($parentItem) {
                if ($getAllVariables || strstr($templateHtml, 'parent_item.') !== false) {
                    $data = $this->customData->entity($parentItem)->processAndReadVariables();
                    $transport['parent_item'] = $data;
                    if (strstr($templateHtml, 'barcode_') !== false) {
                        foreach (AbstractPdf::CODE_BAR as $code) {
                            if (strstr($templateHtml, 'barcode_' . $code . '_parent_item') !== false) {
                                $transport['barcode_' . $code . '_parent_item'] = $this->formatted->getBarcodeFormatted($data, $code);
                            }
                        }
                    }
                    $transport['formatted_parent_item'] = $this->formatted->getFormatted($data);
                }
                $tempOrderItem = $this->orderItem($parentItem);
                if ($getAllVariables || strstr($templateHtml, 'parent_order_item.') !== false) {
                    $transport['parent_order_item'] = $tempOrderItem;
                    if (strstr($templateHtml, 'barcode_') !== false) {
                        foreach (AbstractPdf::CODE_BAR as $code) {
                            if (strstr($templateHtml, 'barcode_' . $code . '_parent_order_item') !== false) {
                                $transport['barcode_' . $code . '_parent_order_item'] = $this->formatted->getBarcodeFormatted($tempOrderItem, $code);
                            }
                        }
                    }
                    $transport['formatted_parent_order_item'] = $this->formatted->getFormatted($tempOrderItem);
                }
                if ($getAllVariables || strstr($templateHtml, 'parent_order_item_product.') !== false) {
                    $data = $this->customProduct->entity($tempOrderItem->getProduct())->processAndReadVariables();
                    $transport['parent_order_item_product'] = $data;
                    if (strstr($templateHtml, 'barcode_') !== false) {
                        foreach (AbstractPdf::CODE_BAR as $code) {
                            if (strstr($templateHtml, 'barcode_' . $code . '_parent_order_item_product') !== false) {
                                $transport['barcode_' . $code . '_parent_order_item_product'] = $this->formatted->getBarcodeFormatted($data, $code);
                            }
                        }
                    }
                    $transport['formatted_parent_order_item_product'] = $this->formatted->getFormatted($data);
                }
            }
        }

        // Ability to customize variables using an event. Store them using $transportObject->setCustomVariables();
        $transportObject = new \Magento\Framework\DataObject();
        $transportObject->setCustomVariables([]);
        $this->_eventManager->dispatch(
            'xtento_pdfcustomizer_build_item_transport_after',
            [
                'type' => 'sales',
                'object' => $source,
                'item' => $itemObject,
                'variables' => $transport,
                'transport' => $transportObject
            ]
        );
        $transport = array_merge($transport, $transportObject->getCustomVariables());

        if ($getAllVariables) {
            return $transport;
        }

        $processor = $this->processor->create();
        $processor->setVariables($transport);
        $processor->setTemplate($template);

        return $processor->processTemplate($source->getStoreId());
    }

    /**
        $items[] = $this->getShippingLine(clone end($items), $source);
        public function getShippingLine($shippingItem, $source)
             {
                 $shippingItem->setData('name', 'Shipping');
                 $shippingItem->setData('sku', 'shipping');
                 $shippingItem->setData('price_incl_tax', $source->getShippingAmount() + $source->getShippingTaxAmount());
                 $shippingItem->setData('price', $source->getShippingAmount() / $vat);
                 $shippingItem->setData('tax_amount', $source->getShippingTaxAmount());
                 $shippingItem->setData('tax_percent', $shippingItem->getData('tax_percent'));
                 $shippingItem->setData('row_total', $shippingItem->getData('price'));
                 $shippingItem->setData('row_total_incl_tax', $shippingItem->getData('price_incl_tax'));
                 return $shippingItem;
             }
     */

    /**
     * @param $source
     * @param $templateModel
     * @return string
     */
    public function processItems($source, $templateModel)
    {
        $templateHtml = $templateModel->getTemplateHtml();
        $templateItemParts = $this->formatted->getTemplateAreas(
            $templateHtml,
            '##items_start##',
            '##items_end##'
        );

        if (empty($templateItemParts)) {
            return $templateHtml;
        }

        $itemConfiguration = [];
        preg_match('/<!--item_configuration:(.*?)-->/', $templateHtml, $itemConfigurationMatches);
        if (isset($itemConfigurationMatches[1])) {
            $itemConfigurationMatches = explode(";", $itemConfigurationMatches[1]);
            array_walk($itemConfigurationMatches, function(&$value, $key) use (&$itemConfiguration) {
                $explodedSetting = explode('=', $value);
                $itemConfiguration[$explodedSetting[0]] = $explodedSetting[1];
            });
        }

        $onlyVisible = false;
        $onlyVisibleFailed = false;
        if (isset($itemConfiguration['mode']) && $itemConfiguration['mode'] == 'visible') {
            $onlyVisible = true;
            $items = $source->getAllVisibleItems();
            if (empty($items)) {
                $onlyVisibleFailed = true;
                $items = $source->getItems();
            }
        } else {
            $items = $source->getItems();
        }

        $this->formatted->applySourceOrder($source);
        $this->formatted->applyTemplate($templateModel);

        if (stristr($templateModel->getTemplateHtml(), 'hideCurrencySymbol=true') === false) {
            $hideCurrencySymbol = false;
        } else {
            $hideCurrencySymbol = true;
        }
        $this->formatted->setConfiguration(['hide_currency_symbol' => $hideCurrencySymbol]);

        if (isset($itemConfiguration['sort_by']) && isset($itemConfiguration['sort_order'])) {
            // Sorting logic
            $sortByAttribute = $itemConfiguration['sort_by'];
            $sortOrder = strtoupper($itemConfiguration['sort_order']);

            // Load product & attribute value for item
            foreach ($items as $item) {
                try {
                    $product = $this->productRepository->getById($item->getProductId(), false, $source->getStoreId());
                } catch (NoSuchEntityException $e) {
                    continue;
                }
                try {
                    $attrText = $product->getAttributeText($sortByAttribute);
                    if (empty($attrText)) {
                        $attrText = $product->getData($sortByAttribute);
                    }
                } catch (\Exception $e) {
                    $attrText = $product->getData($sortByAttribute);
                }
                $item->setXtentoSortField($attrText);
            }

            // Sort items by attribute
            uasort($items, function($a, $b) use ($sortOrder) {
                if ($sortOrder == 'DESC') {
                    return strnatcmp((string)$b->getXtentoSortField(), (string)$a->getXtentoSortField());
                } else {
                    return strnatcmp((string)$a->getXtentoSortField(), (string)$b->getXtentoSortField());
                }
            });
        }

        foreach ($templateItemParts as $templateItemPart) {
            $i = 0;
            $lineCounter = 0;
            $itemHtml = '';
            foreach ($items as $item) {
                if (isset($itemConfiguration['hide_qty_zero_items']) && $itemConfiguration['hide_qty_zero_items'] == 'true') {
                    if ($item->getOrderItem() && $item->getQty() <= 0) { // Shipment, invoice, credit memo
                        continue;
                    }
                }
                $i++;

                // Visibility checks
                if ($onlyVisible && $onlyVisibleFailed) {
                    if ($item->getOrderItem() && $item->getOrderItem()->getParentItemId()) {
                        continue;
                    }
                }
                if (isset($itemConfiguration['hidden_types'])) {
                    $hiddenProductTypes = explode(',', $itemConfiguration['hidden_types']);
                    // Check if this product type should be exported
                    if ($item->getProductType() && in_array($item->getProductType(), $hiddenProductTypes)) {
                        continue; // Product type should be not exported
                    }
                    if (!$item->getProductType() && $item->getOrderItemId()) {
                        // We are not exporting orders, but need to check the product type - thus, need to load the order item.
                        $orderItem = $this->orderItemFactory->create()->load($item->getOrderItemId());
                        if ($orderItem->getProductType() && in_array($orderItem->getProductType(), $hiddenProductTypes)) {
                            continue; // Product type should be not exported
                        }
                    }
                }

                if (!$item->getProductType() && $item->getOrderItemId()) {
                    $orderItem = $this->orderItemFactory->create()->load($item->getOrderItemId());
                    $productType = $orderItem->getProductType();
                    $hasParent = $orderItem->getParentItemId();
                    $hasChildren = $orderItem->getHasChildren();
                } else {
                    $productType = $item->getProductType();
                    $hasChildren = $item->getHasChildren();
                    $hasParent = $item->getParentItemId();
                }
                if (isset($itemConfiguration['hide_parent_items']) && $itemConfiguration['hide_parent_items'] == 'true') {
                    if ($hasChildren) {
                        continue;
                    }
                }
                if (isset($itemConfiguration['hide_child_items']) && $itemConfiguration['hide_child_items'] == 'true') {
                    if ($hasParent) {
                        continue;
                    }
                }
                if ($item instanceof Item) {
                    if ($parentItem = $item->getParentItem()) {
                        if ($parentItem->getData('product_type') != Type::TYPE_BUNDLE) {
                            continue;
                        }
                    }
                } else {
                    if ($item->getOrderItem() && $parentItem = $item->getOrderItem()->getParentItem()) {
                        if ($parentItem->getData('product_type')  != Type::TYPE_BUNDLE) {
                            continue;
                        }
                    }
                }

                $lineCounter++;

                // Build bundle product name, M2.2+
                if ($productType == 'bundle' && version_compare($this->utilsHelper->getMagentoVersion(), '2.2', '>=')) {
                    $serializer = $this->objectManager->create('\Magento\Framework\Serialize\SerializerInterface'); // Does not exist in M2.1 or older, so must be done using object manager
                    if (false === ($source instanceof Order)) {
                        // Invoice, ... - load order items
                        $allItems = $source->getOrder()->getItems();
                        //$allItems = $source->getItems();
                        $itemId = $item->getOrderItemId();
                    } else {
                        $allItems = $source->getItems();
                        $itemId = $item->getId();
                    }

                    $this->currency->load($source->getData('order_currency_code'));

                    foreach ($allItems as $tempItem) {
                        if ($tempItem->getOrderItem()) {
                            $tempItem = $tempItem->getOrderItem();
                        }

                        if ($tempItem->getParentItemId() === $itemId) {
                            $orderItemOptions = $tempItem->getProductOptions();

                            if (!isset($orderItemOptions['bundle_selection_attributes'])) {
                                continue;
                            }

                            $bundleSelectionAttributes = $orderItemOptions['bundle_selection_attributes'];

                            if (is_string($bundleSelectionAttributes)) {
                                $bundleSelectionAttributes = $serializer->unserialize($bundleSelectionAttributes);
                            }

                            if (is_array($bundleSelectionAttributes)) {
                                $item->setData(
                                    'bundle_name',
                                    $item->getData('bundle_name') . '<br><span style="font-weight:bold">' .
                                    $bundleSelectionAttributes['option_label'] .
                                    ': </span><i style="font-weight:normal">' .
                                    $bundleSelectionAttributes['qty'] . ' X ' .
                                    $tempItem->getName() .
                                    (($bundleSelectionAttributes['price'] > 0) ? ' @ ' .
                                        $this->currency->format($bundleSelectionAttributes['price'], [], false, false) : '') .
                                    '</i>'
                                );
                            }
                        }
                    }
                }

                $productOptionsFormatted = '';
                $productOptions = $this->getItemOptions($item);
                if (!empty($productOptions)) {
                    foreach ($productOptions as $productOption) {
                        $productOptionsFormatted .= '<strong>' . $productOption['label'] . '</strong>';
                        if ($productOption['value'] !== null) {
                            $printValue = isset($productOption['print_value']) ? $productOption['print_value'] : $productOption['value'];
                            $productOptionsFormatted .= ': ' . $printValue;
                        }
                        $productOptionsFormatted .= "<br/>";
                    }
                }
                $item->setData('product_options_formatted', $productOptionsFormatted);

                $item->setData('position', $lineCounter);
                $item->setData('is_last_item', ($i-1 == count($items)) ? 1 : 0);

                $templateParts = $this->dataObject->create(
                    [
                        'template_model' => $templateModel,
                        'template_html_full' => $templateModel->getTemplateHtml(),
                        'template_html' => $templateItemPart
                    ]
                );
                $processedTemplate = $this->variableItemProcessor($source, $item, $templateParts);
                $itemHtml .= $processedTemplate['body'];
            }
            $templateHtml = str_replace($templateItemPart, $itemHtml, $templateHtml);
            $templateHtml = $this->replaceFirst('##items_start##', '', $templateHtml);
            $templateHtml = $this->replaceFirst('##items_end##', '', $templateHtml);
        }

        return $templateHtml;
    }

    private function replaceFirst($search, $replace, $string)
    {
        $pos = strpos($string, $search);
        if ($pos !== false) {
            $string = substr_replace($string, $replace, $pos, strlen($search));
        }
        return $string;
    }

    /**
     * @param $item
     * @return mixed
     */
    private function orderItem($item)
    {
        if (!$item instanceof Item && $item->getOrderItem()) {
            $orderItem = $item->getOrderItem();
            $item = $this->customData->entity($orderItem)->processAndReadVariables();
            return $item;
        }

        return $item;
    }

    /**
     * @param $item
     *
     * @return array
     */
    public function getItemOptions($item)
    {
        if ($item->getOrderItem()) {
            $item = $item->getOrderItem();
        }
        $result = [];
        if ($options = $item->getProductOptions()) {
            if (isset($options['options'])) {
                $result = array_merge($result, $options['options']);
            }
            if (isset($options['additional_options'])) {
                $result = array_merge($result, $options['additional_options']);
            }
            if (isset($options['attributes_info'])) {
                $result = array_merge($result, $options['attributes_info']);
            }
        }

        return $result;
    }
}