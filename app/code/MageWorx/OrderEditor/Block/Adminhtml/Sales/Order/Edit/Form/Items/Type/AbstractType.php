<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Block\Adminhtml\Sales\Order\Edit\Form\Items\Type;

use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Item as StockItemResourceModel;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Block\Adminhtml\Items\AbstractItems;
use Magento\Sales\Helper\Admin as AdminHelper;
use Magento\Sales\Model\Order as OrderInstance;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Item as OrderItem;
use MageWorx\OrderEditor\Api\InvoiceFinderInterface;
use MageWorx\OrderEditor\Api\TaxManagerInterface;
use MageWorx\OrderEditor\Helper\Data as OrderEditorHelper;
use MageWorx\OrderEditor\Model\Edit\Thumbnail;

/**
 * Class AbstractType
 */
class AbstractType extends AbstractItems
{
    const ITEM_TYPE_ORDER = 'order';
    const ITEM_TYPE_QUOTE = 'quote';

    /**
     * @var null|OrderInstance
     */
    protected $order = null;

    /**
     * @var null|OrderItem
     */
    protected $orderItem = null;

    /**
     * @var AdminHelper
     */
    protected $adminHelper;

    /**
     * @var OrderEditorHelper
     */
    protected $helperData;

    /**
     * @var TaxManagerInterface
     */
    protected $taxManager;

    /**
     * @var CatalogHelper
     */
    protected $catalogHelper;

    /**
     * @var Thumbnail
     */
    protected $thumbnailModel;

    /**
     * @var InvoiceFinderInterface
     */
    protected $invoiceFinder;

    /**
     * @param Context $context
     * @param StockRegistryInterface $stockRegistry
     * @param StockConfigurationInterface $stockConfiguration
     * @param Registry $registry
     * @param AdminHelper $adminHelper
     * @param StockItemResourceModel $itemResource
     * @param OrderEditorHelper $helperData
     * @param TaxManagerInterface $taxManager
     * @param CatalogHelper $catalogHelper
     * @param Thumbnail $thumbnailModel
     * @param InvoiceFinderInterface $invoiceFinder
     * @param array $data
     */
    public function __construct(
        Context                     $context,
        StockRegistryInterface      $stockRegistry,
        StockConfigurationInterface $stockConfiguration,
        Registry                    $registry,
        AdminHelper                 $adminHelper,
        StockItemResourceModel      $itemResource,
        OrderEditorHelper           $helperData,
        TaxManagerInterface         $taxManager,
        CatalogHelper               $catalogHelper,
        Thumbnail                   $thumbnailModel,
        InvoiceFinderInterface      $invoiceFinder,
        array                       $data = []
    ) {
        $this->adminHelper    = $adminHelper;
        $this->helperData     = $helperData;
        $this->taxManager     = $taxManager;
        $this->catalogHelper  = $catalogHelper;
        $this->thumbnailModel = $thumbnailModel;
        $this->invoiceFinder  = $invoiceFinder;

        parent::__construct($context, $stockRegistry, $stockConfiguration, $registry, $data);
    }

    /**
     * @param \Magento\Sales\Model\Order\Item $item
     * @return \Magento\Catalog\Helper\Image|null
     */
    public function getImageHelper($item)
    {
        return $this->thumbnailModel->getImgByItem($item);
    }

    /**
     * @param OrderItem $orderItem
     * @return $this
     */
    public function setOrderItem($orderItem)
    {
        $this->orderItem = $orderItem;

        return $this;
    }

    /**
     * @return OrderItem
     */
    public function getOrderItem()
    {
        return $this->orderItem;
    }

    /**
     * @param OrderInstance $order
     * @return $this
     */
    public function setOrder(OrderInstance $order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @return OrderInstance
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @return OrderItem
     */
    public function getPriceDataObject()
    {
        return $this->getOrderItem();
    }

    /**
     * @param string $priceType
     * @return string
     */
    public function getPriceHtml(string $priceType): string
    {
        $basePrice = $this->getOrderItem()->getData('base_' . $priceType);
        $price     = $this->getOrderItem()->getData($priceType);

        return $this->adminHelper->displayPrices(
            $this->getOrder(),
            $basePrice,
            $price,
            false,
            '<br/>'
        );
    }

    /**
     * @param string $priceType
     * @return string
     */
    public function getPrice(string $priceType): string
    {
        $price = $this->getOrderItem()->getData($priceType);

        return $this->helperData->roundAndFormatPrice($price);
    }

    /**
     * Return base subtotal cancelled for selected item
     *
     * @return string
     */
    public function getAmountCancelled(): string
    {
        $qtyCancelled = $this->getOrderItem()->getQtyCanceled();
        if ($qtyCancelled <= 0) {
            return 0;
        }

        $basePrice = $this->getOrderItem()->getBasePrice();
        $amountCancelled = $basePrice * $qtyCancelled;

        return $this->helperData->roundAndFormatPrice($amountCancelled);
    }

    /**
     * Return base subtotal incl. tax cancelled for selected item
     *
     * @return string
     */
    public function getAmountCancelledInclTax(): string
    {
        $qtyCancelled = $this->getOrderItem()->getQtyCanceled();
        if ($qtyCancelled <= 0) {
            return 0;
        }

        $basePriceInclTax = $this->getOrderItem()->getBasePriceInclTax();
        $amountCancelledInclTax = $basePriceInclTax * $qtyCancelled;

        return $this->helperData->roundAndFormatPrice($amountCancelledInclTax);
    }

    /**
     * @param string $percentType
     * @return string
     */
    public function getPercent(string $percentType): string
    {
        $percent = (float)$this->getOrderItem()->getData($percentType);

        return number_format($percent, 2, '.', '');
    }

    /**
     * @param string $percentType
     * @return string
     */
    public function getPercentHtml(string $percentType): string
    {
        return $this->getPercent($percentType) . "%";
    }

    /**
     * @return string
     */
    public function getItemTotalHtml(): string
    {
        $basePrice = $this->getBaseItemTotal();
        $price     = $this->getItemTotal();

        return $this->adminHelper->displayPrices(
            $this->getOrder(),
            $basePrice,
            $price,
            false,
            '<br/>'
        );
    }

    /**
     * @return string
     */
    public function getBaseItemTotal(): string
    {
        $orderItem = $this->getOrderItem();

        $total = $orderItem->getBaseRowTotal()
            + $orderItem->getBaseTaxAmount()
            + $orderItem->getBaseWeeeTaxAppliedRowAmount()
            + $orderItem->getBaseDiscountTaxCompensationAmount()
            - $orderItem->getBaseDiscountAmount();

        return $this->helperData->roundAndFormatPrice($total);
    }

    /**
     * @return string
     */
    public function getItemTotal(): string
    {
        $orderItem = $this->getOrderItem();

        $total = $orderItem->getRowTotal()
            + $orderItem->getTaxAmount()
            + $orderItem->getWeeeTaxAppliedRowAmount()
            + $orderItem->getDiscountTaxCompensationAmount()
            - $orderItem->getDiscountAmount();

        return $this->helperData->roundAndFormatPrice($total);
    }

    /**
     * @param OrderItem|null $orderItem
     * @return float|int
     */
    public function getItemQty(OrderItem $orderItem = null): float
    {
        if ($orderItem == null) {
            $orderItem = $this->getOrderItem();
        }

        $itemQty = $orderItem->getQtyOrdered()
            - $orderItem->getQtyRefunded()
            - $orderItem->getQtyCanceled();

        return $itemQty < 0 ? 0.0 : (float)$itemQty;
    }

    /**
     * @param OrderItemInterface $item
     * @return bool
     */
    public function canShowPriceInfo(OrderItemInterface $item): bool
    {
        return true;
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getConfigureButtonHtml(): string
    {
        $product = $this->getOrderItem()->getProduct();
        if (!$product) {
            return '';
        }

        $options = ['label' => __('Configure')];
        if ($product->canConfigure()) {
            $id               = $this->getPrefixId() . $this->getOrderItem()->getId();
            $options['class'] = sprintf("configure-order-item item-id-%s", $id);

            return $this->getLayout()
                        ->createBlock(\Magento\Backend\Block\Widget\Button::class)
                        ->setData($options)
                        ->setDataAttribute(['order-item-id' => $id, 'order-id' => $this->getOrder()->getId()])
                        ->toHtml();
        }

        return '';
    }

    /**
     * @return bool
     */
    public function isCustomOptionsStillAvailable(): bool
    {
        $orderItem = $this->getOrderItem();
        $product   = $orderItem->getProduct();
        if (!$product) {
            return true;
        }

        $orderedOptions        = $orderItem->getProductOptions();
        $orderedProductOptions = $orderedOptions && isset($orderedOptions['options']) ? $orderedOptions['options'] : [];
        if (empty($orderedProductOptions)) {
            return true;
        }

        $productOptions        = $product->getOptions();
        $indexedProductOptions = [];
        foreach ($productOptions as $productOption) {
            $values                                               = $productOption->getValues();
            $indexedProductOptions[$productOption->getOptionId()] = !empty($values) ? array_keys($values) : false;
        }

        $multipleValueOptionTypes = $this->getMultipleValueCustomOptionTypes();
        foreach ($orderedProductOptions as $orderedProductOption) {
            $optionId = $orderedProductOption['option_id'] ?? false;
            if (!$optionId) {
                return false;
            }

            if (!isset($indexedProductOptions[$optionId])) {
                return false;
            }

            if ($indexedProductOptions[$optionId] !== false) {
                $orderedValue = $orderedProductOption['option_value'];
                if (in_array($orderedProductOption['option_type'], $multipleValueOptionTypes)) {
                    if (!is_array($orderedValue)) {
                        $orderedValue = explode(',', $orderedValue);
                    }
                    $diff = array_diff($orderedValue, $indexedProductOptions[$optionId]);
                    if (!empty($diff)) {
                        return false;
                    }
                } else {
                    if (!in_array($orderedValue, $indexedProductOptions[$optionId])) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    public function getMultipleValueCustomOptionTypes(): array
    {
        return ['checkbox', 'multiselect'];
    }

    /**
     * @return bool
     */
    public function getDefaultBackToStock(): bool
    {
        return $this->helperData->getReturnToStock();
    }

    /**
     * @return string
     */
    public function getOrderItemHtmlId(): string
    {
        return $this->getPrefixId() . $this->getOrderItem()->getItemId();
    }

    /**
     * @return string
     */
    public function getParentItemHtmlId(): string
    {
        $parentItem = $this->getOrderItem()->getParentItem();
        $parentId   = !empty($parentItem) ? $parentItem->getItemId() : 0;

        return $this->getPrefixId() . $parentId;
    }

    /**
     * @return bool
     */
    public function hasOrderItemParent(): bool
    {
        $parentItem = $this->getOrderItem()->getParentItem();

        return !empty($parentItem);
    }

    /**
     * @return string
     */
    public function getPrefixId(): string
    {
        return '';
    }

    /**
     * @return string
     */
    public function getEditedItemType(): string
    {
        return static::ITEM_TYPE_ORDER;
    }

    /**
     * @return bool
     */
    public function getCanDeleteItem(): bool
    {
        $item = $this->getOrderItem();
        if (($item->getQtyRefunded() + $item->getQtyCanceled()) == $item->getQtyOrdered()) {
            return false;
        }

        return true;
    }

    /**
     * Get tax rate codes (classes) for the active item
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getItemTaxRateCodes(): array
    {
        return $this->taxManager->getOrderItemTaxClasses($this->getOrderItem());
    }

    /**
     * Return all available tax rate codes (whole Magento)
     *
     * @return array
     */
    public function getTaxRatesOptions(): array
    {
        return $this->taxManager->getAllAvailableTaxRateCodes();
    }

    /**
     * Returns html of the <option> tag for the tax-rates select/multiselect tag
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function renderTaxRatesOptions(): string
    {
        $options     = $this->getTaxRatesOptions();
        $values      = $this->getItemTaxRateCodes();
        $optionsHtml = '';

        foreach ($options as $option) {
            $selected    = in_array($option['label'], $values) ? 'selected="selected"' : '';
            $optionsHtml .= '<option
                value="' . $option['label'] . '" ' .
                'data-percent="' . round($option['percent'], 4) . '"' .
                'data-rate-id="' . $option['id'] . '"' .
                ' ' . $selected . '>' . $option['label'] . '</option>';
        }

        return $optionsHtml;
    }

    /**
     * Get tax rates applied to the order item
     *
     * @return \Magento\Tax\Model\Sales\Order\Tax[]
     * @throws NoSuchEntityException
     */
    public function getItemActiveRates(): array
    {
        $orderItem    = $this->getOrderItem();
        $appliedRates = $this->taxManager->getOrderItemTaxDetails($orderItem);

        return $appliedRates;
    }

    /**
     * @return CatalogHelper
     */
    public function getCatalogHelper(): CatalogHelper
    {
        return $this->catalogHelper;
    }

    /**
     * @return bool
     */
    public function isNewItem(): bool
    {
        return false;
    }

    /**
     * @return bool
     */
    public function getCanRemove(): bool
    {
        $orderItem = $this->getOrderItem();
        if (!$orderItem || !$orderItem->getId()) {
            return true;
        }

        try {
            /** @var InvoiceInterface[]|Invoice[] $invoice */
            $invoices = $this->getInvoiceByOrderItem($orderItem);
        } catch (\Exception $e) {
            $this->_logger->notice($e->__toString());
            $invoices = [];
        }

        foreach ($invoices as $invoice) {
            if ((int)$invoice->getState() === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param OrderItemInterface $orderItem
     * @return array|InvoiceInterface[]
     */
    public function getInvoiceByOrderItem(\Magento\Sales\Api\Data\OrderItemInterface $orderItem): array
    {
        return $this->invoiceFinder->getInvoiceByOrderItemId(
            $orderItem->getId(),
            $orderItem->getOrderId()
        );
    }

    /**
     * @return Phrase
     */
    public function getUnableToRemoveErrorMessage(): Phrase
    {
        return __(
            'This item cannot be removed, because the invoice is not captured. Capture or cancel the invoice in order to remove the item.'
        );
    }
}
