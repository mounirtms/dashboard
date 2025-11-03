<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order as OriginalOrder;
use Magento\Sales\Model\Order\Item as OriginalOrderItem;
use Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory as TaxCollectionFactory;
use MageWorx\OrderEditor\Api\ChangeLoggerInterface;
use MageWorx\OrderEditor\Api\OrderItemRepositoryInterface as OrderEditorOrderItemRepository;
use MageWorx\OrderEditor\Api\OrderRepositoryInterface;
use MageWorx\OrderEditor\Api\QuoteItemRepositoryInterface as OrderEditorQuoteItemRepository;
use MageWorx\OrderEditor\Api\QuoteRepositoryInterface as QuoteRepositoryInterface;
use MageWorx\OrderEditor\Model\Order\Item as OrderEditorOrderItem;
use MageWorx\OrderEditor\Model\Order\SalesProcessorFactory;

/**
 * Class Order
 */
class Order extends OriginalOrder
{
    /**
     * @var []
     */
    protected $newParams = [];

    /**
     * @var \Magento\Tax\Model\Config $taxConfig
     */
    protected $taxConfig = null;

    /**
     * @var \MageWorx\OrderEditor\Api\SalesProcessorInterface
     */
    protected $sales;

    /**
     * @var float
     */
    protected $oldTotal;

    /**
     * @var float
     */
    protected $oldQtyOrdered;

    /**
     * @var []
     */
    protected $addedItems = [];

    /**
     * @var []
     */
    protected $removedItems = [];

    /**
     * @var []
     */
    protected $increasedItems = [];

    /**
     * @var []
     */
    protected $decreasedItems = [];

    /**
     * @var []
     */
    protected $changesInAmounts = [];

    /**
     * @var array
     */
    protected $changesInShipping = [];

    /**
     * @var \MageWorx\OrderEditor\Model\Quote
     */
    protected $quote;

    /**
     * @var \MageWorx\OrderEditor\Model\Invoice
     */
    protected $invoice;

    /**
     * @var \MageWorx\OrderEditor\Model\Creditmemo
     */
    protected $creditmemo;

    /**
     * @var \MageWorx\OrderEditor\Model\Shipment
     */
    protected $shipment;

    /**
     * @var TaxCollectionFactory
     */
    protected $taxCollectionFactory;

    /**
     * @var QuoteRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var DataObjectFactory
     */
    protected $dataObjectFactory;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var OrderEditorQuoteItemRepository
     */
    protected $oeQuoteItemRepository;

    /**
     * @var OrderEditorOrderItemRepository
     */
    protected $oeOrderItemRepository;

    /**
     * @var SearchCriteriaBuilder|mixed
     */
    protected $_searchCriteriaBuilder;
    /**
     * @var SerializerJson
     */
    protected $serializerJson;

    /**
     * Order constructor.
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param OriginalOrder\Config $orderConfig
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Catalog\Model\Product\Visibility $productVisibility
     * @param \Magento\Sales\Api\InvoiceManagementInterface $invoiceManagement
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     * @param \Magento\Eav\Model\Config $eavConfig
     * @param OriginalOrder\Status\HistoryFactory $orderHistoryFactory
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
     * @param \Magento\Tax\Model\Config $taxConfig
     * @param Quote $quote
     * @param Order\Sales $sales
     * @param QuoteRepositoryInterface $quoteRepository
     * @param Invoice $invoice
     * @param Shipment $shipment
     * @param Creditmemo $creditmemo
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderEditorQuoteItemRepository $oeQuoteItemRepository
     * @param OrderEditorOrderItemRepository $oeOrderItemRepository
     * @param DataObjectFactory $dataObjectFactory
     * @param ManagerInterface $messageManager
     * @param OrderCollectionFactoryBox $collectionFactoryBox
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Catalog\Model\Product\Visibility $productVisibility,
        \Magento\Sales\Api\InvoiceManagementInterface $invoiceManagement,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Tax\Model\Config $taxConfig,
        SalesProcessorFactory $salesProcessorFactory,
        QuoteRepositoryInterface $quoteRepository,
        \MageWorx\OrderEditor\Model\Invoice $invoice,
        \MageWorx\OrderEditor\Model\Shipment $shipment,
        \MageWorx\OrderEditor\Model\Creditmemo $creditmemo,
        SerializerJson $serializerJson,
        OrderRepositoryInterface $orderRepository,
        OrderEditorQuoteItemRepository $oeQuoteItemRepository,
        OrderEditorOrderItemRepository $oeOrderItemRepository,
        DataObjectFactory $dataObjectFactory,
        ManagerInterface $messageManager,
        OrderCollectionFactoryBox $collectionFactoryBox,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        // Models
        $this->taxConfig      = $taxConfig;
        $this->sales          = $salesProcessorFactory->create();
        $this->invoice        = $invoice;
        $this->creditmemo     = $creditmemo;
        $this->shipment       = $shipment;
        $this->serializerJson = $serializerJson;

        // Repositories
        $this->quoteRepository       = $quoteRepository;
        $this->orderRepository       = $orderRepository;
        $this->oeQuoteItemRepository = $oeQuoteItemRepository;
        $this->oeOrderItemRepository = $oeOrderItemRepository;

        // Collections & Collection Factories
        $this->taxCollectionFactory = $collectionFactoryBox->getTaxCollectionFactory();

        // Utility
        $this->dataObjectFactory = $dataObjectFactory;
        $this->messageManager    = $messageManager;

        // Rewrite private properties
        $this->_searchCriteriaBuilder = ObjectManager::getInstance()
                                                     ->get(SearchCriteriaBuilder::class);

        // Unpack Collection Factories from the Box
        $orderItemCollectionFactory  = $collectionFactoryBox->getOrderItemCollectionFactory();
        $addressCollectionFactory    = $collectionFactoryBox->getAddressCollectionFactory();
        $paymentCollectionFactory    = $collectionFactoryBox->getPaymentCollectionFactory();
        $historyCollectionFactory    = $collectionFactoryBox->getHistoryCollectionFactory();
        $invoiceCollectionFactory    = $collectionFactoryBox->getInvoiceCollectionFactory();
        $shipmentCollectionFactory   = $collectionFactoryBox->getShipmentCollectionFactory();
        $memoCollectionFactory       = $collectionFactoryBox->getMemoCollectionFactory();
        $trackCollectionFactory      = $collectionFactoryBox->getTrackCollectionFactory();
        $salesOrderCollectionFactory = $collectionFactoryBox->getSalesOrderCollectionFactory();
        $productListFactory          = $collectionFactoryBox->getProductListFactory();

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $timezone,
            $storeManager,
            $orderConfig,
            $productRepository,
            $orderItemCollectionFactory,
            $productVisibility,
            $invoiceManagement,
            $currencyFactory,
            $eavConfig,
            $orderHistoryFactory,
            $addressCollectionFactory,
            $paymentCollectionFactory,
            $historyCollectionFactory,
            $invoiceCollectionFactory,
            $shipmentCollectionFactory,
            $memoCollectionFactory,
            $trackCollectionFactory,
            $salesOrderCollectionFactory,
            $priceCurrency,
            $productListFactory,
            $resource,
            $resourceCollection,
            $data
        );

        // Overwrite collection factories: objects returned must be instance of the OrderEditor classes
        $this->_invoiceCollectionFactory  = $collectionFactoryBox->getOeInvoiceCollectionFactory();
        $this->_memoCollectionFactory     = $collectionFactoryBox->getOeCreditmemoCollectionFactory();
        $this->_shipmentCollectionFactory = $collectionFactoryBox->getOeShipmentCollectionFactory();

        // @TODO Remove this later, when we totally remove registry from module
        if (!$this->_registry->registry('ordereditor_order')) {
            $this->_registry->register('ordereditor_order', $this);
        }
    }

    /**
     * Init resource model
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init(\MageWorx\OrderEditor\Model\ResourceModel\Order::class);
    }

    /**
     * @return bool
     */
    public function hasItemsWithIncreasedQty(): bool
    {
        return array_sum($this->increasedItems) > 0;
    }

    /**
     * @return bool
     */
    public function hasItemsWithDecreasedQty(): bool
    {
        return array_sum($this->decreasedItems) > 0;
    }

    /**
     * @return bool
     */
    public function hasAddedItems(): bool
    {
        return count($this->addedItems) > 0;
    }

    /**
     * @return bool
     */
    public function hasRemovedItems(): bool
    {
        return count($this->removedItems) > 0;
    }

    /**
     * @return array
     */
    public function getChangesInAmounts(int $itemId = null): array
    {
        $changes = $this->changesInAmounts;
        if ($itemId) {
            $changes = $changes[$itemId] ?? $changes;
        }

        return $changes;
    }

    /**
     * @return bool
     */
    public function hasChangesInAmounts(): bool
    {
        return count($this->changesInAmounts) > 0;
    }

    /**
     * @param array $changes
     * @return void
     */
    public function addShippingChanges(array $changes): void
    {
        $this->changesInShipping = $changes;
    }

    /**
     * @return array
     */
    public function getChangesInShipping(): array
    {
        return $this->changesInShipping;
    }

    /**
     * @return bool
     */
    public function isTotalWasChanged(): bool
    {
        return $this->getChangesInTotal() != 0;
    }

    /**
     * @return float
     */
    public function getChangesInTotal(): float
    {
        return (float)$this->oldTotal - (float)$this->getCurrentOrderTotal();
    }

    /**
     * @return float
     */
    protected function getCurrentOrderTotal(): float
    {
        return (float)$this->getGrandTotal() - (float)$this->getTotalRefunded();
    }

    /**
     * @return void
     */
    protected function resetChanges()
    {
        $this->oldTotal         = $this->getCurrentOrderTotal();
        $this->oldQtyOrdered    = $this->getTotalQtyOrdered();
        $this->addedItems       = [];
        $this->removedItems     = [];
        $this->increasedItems   = [];
        $this->decreasedItems   = [];
        $this->changesInAmounts = [];
    }

    /**
     * @param array $params
     * @return void
     * @throws Exception
     */
    public function editItems(array $params)
    {
        // Here we go
        $this->resetChanges(); // Reset changes in order - OK
        $this->prepareParamsForEditItems($params); // set initial params and basic validation - Ω
        $this->updateOrderItems(); //^ Update order items using params from request: changes were added inside
        $this->resetItems(); // Reset items in the order - OK

        $this->collectOrderTotals(); // Manually calculate order totals - MUST BE CHANGED according new logic (cm&invoice)
        $this->updatePayment(); //^ Create invoices and creditmemos if needed: changes inside, see new Sales Model

        $this->resetItems(); // Reset items in the order - OK
        $this->orderRepository->save($this); // Finally, save the order with new items and totals

        $this->_eventManager->dispatch(
            'mageworx_order_updated',
            [
                'action'         => \MageWorx\OrderEditor\Api\WebhookProcessorInterface::ACTION_UPDATE_ORDER_ITEMS,
                'object'         => $this,
                'initial_params' => $params
            ]
        );

        $this->_eventManager->dispatch(
            'mageworx_save_logged_changes_for_order',
            [
                'order_id'        => $this->getId(),
                'notify_customer' => false
            ]
        );
    }

    /**
     * Reset order items after manipulations.
     * Important for order saving: original items has incorrect values.
     */
    protected function resetItems(): void
    {
        $newItems = $this->getItemsCollection()->getItems();

        $this->changeTheFileTypeOptionValue($newItems);
        $this->setData(OrderInterface::ITEMS, $newItems);
    }

    /**
     * Changing the file type option value data to the correct format
     *
     */
    public function changeTheFileTypeOptionValue(array $newItems): void
    {
        foreach ($newItems as &$item) {
            $productOption = $item->getProductOption();

            if ($productOption === null) {
                continue;
            }

            $extAttr = $productOption->getExtensionAttributes()->getCustomOptions();

            if (empty($extAttr)) {
                continue;
            }

            $productOptionsData = $item->getProductOptions();
            $productOptions     = empty($productOptionsData['options']) ? [] : $productOptionsData['options'];
            $optFiles           = [];

            foreach ($productOptions as $option) {
                if ($option['option_type'] == 'file') {
                    $value                          = $this->serializerJson->unserialize($option['option_value']);
                    $optFiles[$option['option_id']] = $value;
                }
            }

            foreach ($extAttr as &$extAttrItem) {
                if (array_key_exists($extAttrItem->getOptionId(), $optFiles)) {
                    $extAttrItem->setOptionValue($optFiles[$extAttrItem->getOptionId()]);
                }
            }
        }
    }

    /**
     * @return void
     */
    public function updatePayment()
    {
        $this->sales->setOrder($this)->updateSalesObjects();
    }

    /**
     * @param string[] $params
     * @return void
     * @throws LocalizedException
     */
    protected function prepareParamsForEditItems(array $params)
    {
        if (!isset($params['order_id']) || !isset($params['item'])) {
            throw new LocalizedException(__('Incorrect params for edit order items'));
        }

        $this->newParams = $params['item'];
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function updateOrderItems()
    {
        foreach ($this->newParams as $id => $params) {
            if (!empty($params['item_type']) && $params['item_type'] === 'quote') {
                $id = null;
            }

            if (!$id && !empty($params['parent'])) {
                continue;
            }

            try {
                $item = $this->loadOrderItem($id, $params);
            } catch (NoSuchEntityException $noSuchEntityException) {
                continue;
            }

            // Here we're changing item qty, removing or adding items
            /* var $item \MageWorx\OrderEditor\Model\Order\Item */
            $orderItem = $item->editItem($params, $this);

            $this->collectItemsChanges($orderItem);

            $this->oeOrderItemRepository->save($orderItem);
        }
    }

    /**
     * @return array
     */
    public function getCurrentEditParams(): array
    {
        return $this->newParams;
    }

    /**
     * @param int|null $id
     * @param string[] $params
     * @return OrderEditorOrderItem
     * @throws NoSuchEntityException
     */
    protected function loadOrderItem(int $id = null, array $params = []): OrderEditorOrderItem
    {
        $item = $this->oeOrderItemRepository->getEmptyEntity();
        if (!isset($params['item_type']) || $params['item_type'] !== 'quote') {
            if ($id) {
                $item = $this->oeOrderItemRepository->getById($id);
            } elseif (!empty($params['item_id']) && !empty($params['parent'])) {
                $quoteItemId = (int)(str_ireplace('q', '', $params['parent']));
                $item        = $this->oeOrderItemRepository->getByQuoteItemId($quoteItemId);
            }

            if (isset($params['action']) && $params['action'] == 'remove') {
                $qtyToRemove             = $item->getQtyOrdered() - $item->getQtyCanceled() - $item->getQtyRefunded();
                $this->removedItems[$id] = $params['fact_qty'] ?? $qtyToRemove;
            }
        }

        return $item;
    }

    /**
     * @param OriginalOrderItem|OrderEditorOrderItem $orderItem
     * @return void
     */
    public function collectItemsChanges(OriginalOrderItem $orderItem)
    {
        $itemId = $orderItem->getItemId();
        if ($orderItem->getIncreasedQty()) {
            $this->increasedItems[$itemId] = $orderItem->getIncreasedQty();
        }
        if ($orderItem->getDecreasedQty()) {
            $this->decreasedItems[$itemId] = $orderItem->getDecreasedQty();
        }

        $changes = $orderItem->getChangesInAmounts();
        if (!empty($changes)) {
            $this->changesInAmounts[$itemId] = $changes;
        }
    }

    /**
     * @param int $id
     * @param string[] $params
     * @return void
     * @throws Exception
     */
    protected function editNewItem(int $id, array $params)
    {
        if (isset($params['item_type']) && $params['item_type'] == 'quote') {
            $this->addedItems[] = $id;

            unset($params['action'], $params['item_type']);

            $item = $this->oeOrderItemRepository->getById($id);
            $item->editItem($params, $this);
        }
    }

    /**
     * Get actual changes in amounts for specified item from order
     *
     * @param int $itemId
     * @return array
     */
    public function getItemChanges(int $itemId): array
    {
        $changesInAmount = $this->getChangesInAmounts();
        $changesForItem  = $changesInAmount[$itemId] ?? [];

        return $changesForItem;
    }

    /**
     * Calculate how much should be refunded for that item (in order currency).
     *
     * @param OrderEditorOrderItem $orderItem
     * @return float
     */
    public function getOrderItemAmountToRefund(\Magento\Sales\Api\Data\OrderItemInterface $orderItem): float
    {
        $amount      = 0;
        $itemId      = $orderItem->getId();
        $itemChanges = $this->getItemChanges($itemId);

        if (!empty($itemChanges)) {
            // Get total invoiced from the beginning of the order
            $invoicedAmount = (float)$orderItem->getRowInvoiced();

            // Get actual row total for now
            $newAmount = (float)$orderItem->getRowTotalInclTax();

            // Get actual row total refunded for now
            $amountRefunded = (float)$orderItem->getAmountRefunded();

            // Calculate real invoiced total (invoiced minus refunded for now) for now
            $realTotalInvoiced = $invoicedAmount - $amountRefunded;

            // We must create new creditmemo only when total real invoiced amount is smaller than new base amount
            if ($realTotalInvoiced > $newAmount) {
                // Return for that item
                $amount = $invoicedAmount - $newAmount;
            }
        }

        return $amount;
    }

    /**
     * Calculate how much should be refunded for that item (in base currency).
     *
     * @param OrderEditorOrderItem $orderItem
     * @return float
     */
    public function getOrderItemBaseAmountToRefund(\Magento\Sales\Api\Data\OrderItemInterface $orderItem): float
    {
        $amount      = 0;
        $itemId      = $orderItem->getId();
        $itemChanges = $this->getItemChanges($itemId);

        if (!empty($itemChanges)) {
            $invoicedAmount    = (float)$orderItem->getBaseRowInvoiced(
            ); // Get total invoiced from the beginning of the order
            $newAmount         = (float)$orderItem->getBaseRowTotalInclTax(); // Get actual row total for now
            $amountRefunded    = (float)$orderItem->getBaseAmountRefunded(); // Get actual row total refunded for now
            $realTotalInvoiced = $invoicedAmount - $amountRefunded; // Calculate real invoiced total (invoiced minus refunded for now) for now
            if ($realTotalInvoiced > $newAmount) { // We must create new creditmemo only when total real invoiced amount is smaller than new base amount
                // Return for that item
                $amount = $invoicedAmount - $newAmount;
            }
        }

        return $amount;
    }

    /**
     * @return void
     * @throws Exception
     */
    public function collectOrderTotals()
    {
        $totalQtyOrdered                   = 0;
        $weight                            = 0;
        $totalItemCount                    = 0;
        $baseDiscountTaxCompensationAmount = 0;
        $baseDiscountAmount                = 0;
        $baseSubtotal                      = 0;
        $baseSubtotalInclTax               = 0;
        $baseSubtotalCanceled              = 0;
        $baseTaxCanceled                   = 0;

        /** @var OrderEditorOrderItem $orderItem */
        foreach ($this->getItems() as $orderItem) { //@TODO Check is item refunded or canceled, process right qty
            $baseDiscountAmount += $orderItem->getBaseDiscountAmount();

            //bundle part
            if ($orderItem->getParentItem()) {
                continue;
            }

            $baseDiscountTaxCompensationAmount += $orderItem->getBaseDiscountTaxCompensationAmount() -
                $orderItem->getDiscountTaxCompensationCanceled();

            $totalQtyOrdered += $orderItem->getQtyOrdered();
            $totalItemCount++;
            $weight += $orderItem->getRowWeight();
            /**
             * RowTotal for item is a subtotal.
             * We must include refunded amount to prevent errors during invoice (see invoice collect totals method).
             */
            $baseSubtotal        += $orderItem->getBaseRowTotal();
            $baseSubtotalInclTax += $orderItem->getBaseRowTotalInclTax();

            if ((float)$orderItem->getQtyCanceled() > 0) {
                $baseSubtotalCanceled += $orderItem->getBasePriceInclTax() * $orderItem->getQtyCanceled();
                $baseTaxCanceled      += $orderItem->getTaxCanceled();
            }
        }

        /* convert currency */
        $baseCurrencyCode  = $this->getBaseCurrencyCode();
        $orderCurrencyCode = $this->getOrderCurrencyCode();

        if ($baseCurrencyCode === $orderCurrencyCode) {
            $discountAmount                = $baseDiscountAmount + $this->getBaseShippingDiscountAmount();
            $discountTaxCompensationAmount = $baseDiscountTaxCompensationAmount;
            $subtotal                      = $baseSubtotal;
            $subtotalInclTax               = $baseSubtotalInclTax;
            $subtotalCanceled              = $baseSubtotalCanceled;
            $taxCanceled                   = $baseTaxCanceled;
        } else {
            $discountAmount                = $this->getBaseCurrency()
                                                  ->convert(
                                                      $baseDiscountAmount + $this->getBaseShippingDiscountAmount(),
                                                      $orderCurrencyCode
                                                  );
            $discountTaxCompensationAmount = $this->getBaseCurrency()
                                                  ->convert(
                                                      $baseDiscountTaxCompensationAmount,
                                                      $orderCurrencyCode
                                                  );
            $subtotal                      = $this->getBaseCurrency()
                                                  ->convert(
                                                      $baseSubtotal,
                                                      $orderCurrencyCode
                                                  );
            $subtotalInclTax               = $this->getBaseCurrency()
                                                  ->convert(
                                                      $baseSubtotalInclTax,
                                                      $orderCurrencyCode
                                                  );
            $subtotalCanceled              = $this->getBaseCurrency()
                                                  ->convert(
                                                      $baseSubtotalCanceled,
                                                      $orderCurrencyCode
                                                  );
            $taxCanceled                   = $this->getBaseCurrency()
                                                  ->convert(
                                                      $baseTaxCanceled,
                                                      $orderCurrencyCode
                                                  );
        }

        if ($this->getWeight() != $weight) {
            $this->_eventManager->dispatch(
                'mageworx_log_changes_on_order_edit',
                [
                    ChangeLoggerInterface::SIMPLE_MESSAGE_KEY => __(
                        'Total Weight has been changed from <b>%1</b> to <b>%2</b>',
                        round($this->getWeight(), 4),
                        round($weight, 4)
                    )
                ]
            );
        }

        $this->setTotalQtyOrdered($totalQtyOrdered)
             ->setWeight($weight)
             ->setSubtotal($subtotal)
             ->setBaseSubtotal($baseSubtotal)
             ->setSubtotalInclTax($subtotalInclTax)
             ->setBaseSubtotalInclTax($baseSubtotalInclTax)
             ->setDiscountTaxCompensationAmount($discountTaxCompensationAmount)
             ->setBaseDiscountTaxCompensationAmount($baseDiscountTaxCompensationAmount)
             ->setDiscountAmount('-' . $discountAmount)
             ->setBaseDiscountAmount('-' . $baseDiscountAmount)
             ->setTotalItemCount($totalItemCount)
             ->setSubtotalCanceled($subtotalCanceled)
             ->setBaseSubtotalCanceled($baseSubtotalCanceled)
             ->setTaxCanceled($taxCanceled)
             ->setBaseTaxCanceled($baseTaxCanceled)
             ->setTotalCanceled($subtotalCanceled)
             ->setBaseTotalCanceled($baseSubtotalCanceled);

        $this->calculateGrandTotal();

        $this->orderRepository->save($this);
    }

    /**
     * @return void
     * @throws Exception
     */
    public function calculateGrandTotal()
    {
        $this->reCalculateTaxAmount();

        // shipping tax
        $tax     = $this->getTaxAmount() + $this->getShippingTaxAmount();
        $baseTax = $this->getBaseTaxAmount() + $this->getBaseShippingTaxAmount();

        $this->setTaxAmount($tax)->setBaseTaxAmount($baseTax);
        $this->orderRepository->save($this);

        // Order GrandTotal include tax
        if ($this->checkTaxConfiguration()) {
            $grandTotal     = $this->getSubtotal()
                + $this->getTaxAmount()
                + $this->getShippingAmount()
                + $this->calculateMageWorxFeeAmount()
                - abs((float)$this->getDiscountAmount())
                - abs((float)$this->getGiftCardsAmount())
                - abs((float)$this->getCustomerBalanceAmount());
            $baseGrandTotal = $this->getBaseSubtotal()
                + $this->getBaseTaxAmount()
                + $this->getBaseShippingAmount()
                + $this->calculateMageWorxBaseFeeAmount()
                - abs((float)$this->getBaseDiscountAmount())
                - abs((float)$this->getBaseGiftCardsAmount())
                - abs((float)$this->getBaseCustomerBalanceAmount());
        } else {
            $grandTotal     = $this->getSubtotalInclTax()
                + $this->getShippingInclTax()
                + $this->calculateMageWorxFeeAmount()
                - abs((float)$this->getDiscountAmount())
                - abs((float)$this->getGiftCardsAmount())
                - abs((float)$this->getCustomerBalanceAmount());
            $baseGrandTotal = $this->getBaseSubtotalInclTax()
                + $this->getBaseShippingInclTax()
                + $this->calculateMageWorxBaseFeeAmount()
                - abs((float)$this->getBaseDiscountAmount())
                - abs((float)$this->getBaseGiftCardsAmount())
                - abs((float)$this->getBaseCustomerBalanceAmount());
        }

        if ((float)$this->getGrandTotal() != (float)$grandTotal) {
            $this->_eventManager->dispatch(
                'mageworx_log_changes_on_order_edit',
                [
                    ChangeLoggerInterface::SIMPLE_MESSAGE_KEY => __(
                        '<b>Grand Total</b> has been changed from <b>%1</b> to <b>%2</b>',
                        $this->formatPriceTxt($this->getGrandTotal()),
                        $this->formatPriceTxt($grandTotal)
                    )
                ]
            );
        }

        $this->setGrandTotal($grandTotal)
             ->setBaseGrandTotal($baseGrandTotal);
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function reCalculateTaxAmount()
    {
        $baseTaxAmount = 0;

        /**
         * @var OrderEditorOrderItem $orderItem
         */
        foreach ($this->getItems() as $orderItem) {
            if ($orderItem->getParentItem()) {
                continue;
            }
            $baseTaxAmount += $orderItem->getBaseTaxAmount();
        }

        $baseCurrencyCode  = $this->getBaseCurrencyCode();
        $orderCurrencyCode = $this->getOrderCurrencyCode();
        if ($baseCurrencyCode === $orderCurrencyCode) {
            $taxAmount = $baseTaxAmount;
        } else {
            $taxAmount = $this->getBaseCurrency()->convert(
                $baseTaxAmount,
                $orderCurrencyCode
            );
        }

        $this->setTaxAmount($taxAmount)->setBaseTaxAmount($baseTaxAmount);
    }

    /**
     * @return bool
     */
    public function checkTaxConfiguration(): bool
    {
        $catalogPrices         = $this->taxConfig->priceIncludesTax() ? 1 : 0;
        $shippingPrices        = $this->taxConfig->shippingPriceIncludesTax() ? 1 : 0;
        $applyTaxAfterDiscount = $this->taxConfig->applyTaxAfterDiscount() ? 1 : 0;

        return !$catalogPrices && !$shippingPrices && $applyTaxAfterDiscount;
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function syncQuote()
    {
        if ($this->hasItemsWithIncreasedQty()
            || $this->hasAddedItems()
            || $this->hasItemsWithDecreasedQty()
            || $this->hasRemovedItems()
            || $this->isTotalWasChanged()
        ) {
            $this->syncQuoteItems();
        }

        $this->syncAddressesData();
        $this->syncQuoteData();

        return $this;
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    protected function syncQuoteData()
    {
        $additionalData = [
            'store_to_base_rate'          => $this->getStoreToBaseRate(),
            'store_to_quote_rate'         => $this->getStoreToOrderRate(),
            'base_currency_code'          => $this->getBaseCurrencyCode(),
            'store_currency_code'         => $this->getStoreCurrencyCode(),
            'quote_currency_code'         => $this->getOrderCurrencyCode(),
            'grand_total'                 => $this->getGrandTotal(),
            'base_grand_total'            => $this->getBaseGrandTotal(),
            'subtotal'                    => $this->getSubtotal(),
            'base_subtotal'               => $this->getBaseSubtotal(),
            'subtotal_with_discount'      => $this->getSubtotal()
                - abs((float)$this->getDiscountAmount()),
            'base_subtotal_with_discount' => $this->getBaseSubtotal()
                - abs((float)$this->getBaseDiscountAmount()),
            'items_qty'                   => $this->getTotalQtyOrdered(),
            'items_count'                 => $this->getTotalItemCount()
        ];

        $this->getQuote()->addData($additionalData);
        $this->quoteRepository->save($this->getQuote());
    }

    /**
     * @return void
     */
    protected function syncQuoteItems()
    {
        $orderItems = $this->getItems(); // OLD items here
        foreach ($orderItems as $orderItem) {
            $quoteItemId = $orderItem->getQuoteItemId();
            if (!$quoteItemId) {
                continue;
            }

            try {
                $quoteItem = $this->oeQuoteItemRepository->getById($quoteItemId);
                $quoteItem->setQuote($this->getQuote());
            } catch (NoSuchEntityException $noSuchEntityException) {
                $this->_logger->warning($noSuchEntityException);
                continue;
            }

            $qty = $orderItem->getQtyOrdered()
                - $orderItem->getQtyRefunded()
                - $orderItem->getQtyCanceled();

            $data = [
                'product_id'                            => $orderItem->getProductId(),
                'store_id'                              => $orderItem->getStoreId(),
                'is_virtual'                            => $orderItem->getIsVirtual(),
                'sku'                                   => $orderItem->getSku(),
                'name'                                  => $orderItem->getName(),
                'description'                           => $orderItem->getDescription(),
                'additional_data'                       => $orderItem->getAdditionalData(),
                'applied_rule_ids'                      => $orderItem->getAppliedRuleIds(),
                'is_qty_decimal'                        => $orderItem->getIsQtyDecimal(),
                'no_discount'                           => $orderItem->getNoDiscount(),
                'weight'                                => $orderItem->getWeight(),
                'qty'                                   => $qty,
                'price'                                 => $orderItem->getPrice(),
                'base_price'                            => $orderItem->getBasePrice(),
                'custom_price'                          => $orderItem->getPrice(),
                'discount_percent'                      => $orderItem->getDiscountPercent(),
                'discount_amount'                       => $orderItem->getDiscountAmount(),
                'base_discount_amount'                  => $orderItem->getBaseDiscountAmount(),
                'tax_percent'                           => $orderItem->getTaxPercent(),
                'base_tax_amount'                       => $orderItem->getBaseTaxAmount(),
                'row_total'                             => $orderItem->getRowTotal(),
                'base_row_total'                        => $orderItem->getBaseRowTotal(),
                'row_total_with_discount'               => $orderItem->getRowTotal() - $orderItem->getDiscountAmount(),
                'row_weight'                            => $orderItem->getRowWeight(),
                'product_type'                          => $orderItem->getProductType(),
                'base_tax_before_discount'              => $orderItem->getBaseTaxBeforeDiscount(),
                'tax_before_discount'                   => $orderItem->getTaxBeforeDiscount(),
                'original_custom_price'                 => $orderItem->getOriginalPrice(),
                'base_cost'                             => $orderItem->getBaseCost(),
                'price_incl_tax'                        => $orderItem->getPriceInclTax(),
                'base_price_incl_tax'                   => $orderItem->getBasePriceInclTax(),
                'row_total_incl_tax'                    => $orderItem->getRowTotalInclTax(),
                'base_row_total_incl_tax'               => $orderItem->getBaseRowTotalInclTax(),
                'discount_tax_compensation_amount'      => $orderItem->getDiscountTaxCompensationAmount(),
                'base_discount_tax_compensation_amount' => $orderItem->getBaseDiscountTaxCompensationAmount(),
                'free_shipping'                         => $orderItem->getFreeShipping(),
                'weee_tax_applied'                      => $orderItem->getWeeeTaxApplied(),
                'weee_tax_applied_amount'               => $orderItem->getWeeeTaxAppliedAmount(),
                'weee_tax_applied_row_amount'           => $orderItem->getWeeeTaxAppliedRowAmount(),
                'weee_tax_disposition'                  => $orderItem->getWeeeTaxDisposition(),
                'weee_tax_row_disposition'              => $orderItem->getWeeeTaxRowDisposition(),
                'base_weee_tax_applied_amount'          => $orderItem->getBaseWeeeTaxAppliedAmount(),
                'base_weee_tax_applied_row_amnt'        => $orderItem->getBaseWeeeTaxAppliedRowAmnt(),
                'base_weee_tax_disposition'             => $orderItem->getBaseWeeeTaxDisposition(),
                'base_weee_tax_row_disposition'         => $orderItem->getBaseWeeeTaxRowDisposition(),
            ];

            $quoteItem->addData($data);

            try {
                $this->oeQuoteItemRepository->save($quoteItem);
            } catch (LocalizedException $e) {
                $this->messageManager
                    ->addErrorMessage(
                        __('Something goes wrong while sync quote items. Original error message: %1', $e->getMessage())
                    );
            }
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function syncAddressesData()
    {
        $additionalData = [
            'quote_id'                                     => $this->getQuoteId(),
            'weight'                                       => $this->getWeight(),
            'subtotal'                                     => $this->getSubtotal(),
            'base_subtotal'                                => $this->getBaseSubtotal(),
            'subtotal_with_discount'                       => $this->getSubtotal()
                - abs((float)$this->getDiscountAmount()),
            'base_subtotal_with_discount'                  => $this->getBaseSubtotal()
                - abs((float)$this->getBaseDiscountAmount()),
            'tax_amount'                                   => $this->getTaxAmount(),
            'base_tax_amount'                              => $this->getBaseTaxAmount(),
            'shipping_amount'                              => $this->getShippingAmount(),
            'base_shipping_amount'                         => $this->getBaseShippingAmount(),
            'shipping_tax_amount'                          => $this->getShippingTaxAmount(),
            'base_shipping_tax_amount'                     => $this->getBaseShippingTaxAmount(),
            'discount_amount'                              => $this->getDiscountAmount(),
            'base_discount_amount'                         => $this->getBaseDiscountAmount(),
            'grand_total'                                  => $this->getGrandTotal(),
            'base_grand_total'                             => $this->getBaseGrandTotal(),
            'shipping_discount_amount'                     => $this->getShippingDiscountAmount(),
            'base_shipping_discount_amount'                => $this->getBaseShippingDiscountAmount(),
            'subtotal_incl_tax'                            => $this->getSubtotalInclTax(),
            'base_subtotal_total_incl_tax'                 => $this->getBaseSubtotalInclTax(),
            'discount_tax_compensation_amount'             => $this->getDiscountTaxCompensationAmount(),
            'base_discount_tax_compensation_amount'        => $this->getBaseDiscountTaxCompensationAmount(),
            'shipping_discount_tax_compensation_amount'    => $this->getShippingDiscountTaxCompensationAmount(),
            'base_shipping_discount_tax_compensation_amnt' => $this->getBaseShippingDiscountTaxCompensationAmnt(),
            'shipping_incl_tax'                            => $this->getShippingAmount()
                + $this->getShippingTaxAmount(),
            'base_shipping_incl_tax'                       => $this->getBaseShippingAmount()
                + $this->getBaseShippingTaxAmount()
        ];

        if (!$this->getIsVirtual()) {
            $shippingAddressAdditionalData = [
                'shipping_method'      => $this->getShippingMethod(false),
                'shipping_description' => $this->getShippingDescription()
            ];
            $shippingAddressData           = $this->getShippingAddress()->getData();
            // If we edit quote shipping address extension attributes all changes will be lost!
            unset($shippingAddressData['extension_attributes']);
            $quoteAddress                      = $this->getQuote()->getShippingAddress();
            $shippingAddressData['address_id'] = $quoteAddress->getAddressId();
            $finalShippingData                 = array_merge(
                $shippingAddressData,
                $additionalData,
                $shippingAddressAdditionalData
            );

            $quoteAddress->addData($finalShippingData);
            $quoteAddress->save();
        }

        $billingAddressData               = $this->getBillingAddress()->getData();
        $quoteAddress                     = $this->getQuote()->getBillingAddress();
        $billingAddressData['address_id'] = $quoteAddress->getAddressId();
        $finalBillingData                 = array_merge($billingAddressData, $additionalData);

        $quoteAddress->addData($finalBillingData);
        $quoteAddress->save();
    }

    /**
     * @return $this
     * @throws LocalizedException
     */
    public function beforeDelete()
    {
        $this->deleteRelatedShipments();
        $this->deleteRelatedInvoices();
        $this->deleteRelatedCreditMemos();
        $this->deleteRelatedOrderInfo();

        parent::beforeDelete();

        return $this;
    }

    /**
     * @return void
     */
    protected function deleteRelatedOrderInfo()
    {
        try {
            $collection = $this->_addressCollectionFactory->create()->setOrderFilter($this);
            $collection->walk('delete');

            $collection = $this->_orderItemCollectionFactory->create()->setOrderFilter($this);
            $collection->walk('delete');

            $collection = $this->_paymentCollectionFactory->create()->setOrderFilter($this);
            $collection->walk('delete');

            $collection = $this->_historyCollectionFactory->create()->setOrderFilter($this);
            $collection->walk('delete');

            $collection = $this->taxCollectionFactory->create()->loadByOrder($this);
            $collection->walk('delete');
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('Delete related order info error: %1', $e->getMessage()));
        }
    }

    /**
     * @return void
     */
    protected function deleteRelatedInvoices()
    {
        try {
            $collection = $this->getInvoiceCollection();
            $collection->walk('delete');
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('Delete related invoices error: %1', $e->getMessage()));
        }
    }

    /**
     * @return void
     */
    protected function deleteRelatedShipments()
    {
        try {
            $collection = $this->getShipmentsCollection();
            $collection->walk('delete');
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('Delete related shipments error: %1', $e->getMessage()));
        }
    }

    /**
     * @return void
     */
    protected function deleteRelatedCreditMemos()
    {
        try {
            $collection = $this->getCreditmemosCollection();
            $collection->walk('delete');
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('Delete related credit memos error: %1', $e->getMessage()));
        }
    }

    /**
     * @return Quote
     * @throws NoSuchEntityException
     */
    public function getQuote()
    {
        if ($this->quote) {
            return $this->quote;
        }

        $this->quote = $this->quoteRepository->getById($this->getQuoteId());

        return $this->quote;
    }

    /**
     * Get order fee amount
     *
     * @return float
     */
    public function calculateMageWorxFeeAmount(): float
    {
        $regularFee = (float)$this->getData('mageworx_fee_amount') - (float)$this->getData('mageworx_fee_cancelled');
        $productFee = (float)$this->getData('mageworx_product_fee_amount') - (float)$this->getData(
                'mageworx_product_fee_cancelled'
            );
        $overallFee = $regularFee + $productFee;

        return $overallFee;
    }

    /**
     * Get order base fee amount
     *
     * @return float
     */
    public function calculateMageWorxBaseFeeAmount(): float
    {
        $regularBaseFee = (float)$this->getData('base_mageworx_fee_amount') - (float)$this->getData(
                'base_mageworx_fee_cancelled'
            );
        $productBaseFee = (float)$this->getData('base_mageworx_product_fee_amount') - (float)$this->getData(
                'base_mageworx_product_fee_cancelled'
            );
        $overallBaseFee = $regularBaseFee + $productBaseFee;

        return $overallBaseFee;
    }

    /**
     * Retrieve order invoice availability
     *
     * @return bool
     */
    public function canInvoice()
    {
        if ($this->canUnhold() || $this->isPaymentReview()) {
            return false;
        }
        $state = $this->getState(); // @TODO Maybe we should remove check by state in our custom order model
        if ($this->isCanceled() || $state === self::STATE_COMPLETE || $state === self::STATE_CLOSED) {
            return false;
        }

        if ($this->getActionFlag(self::ACTION_FLAG_INVOICE) === false) {
            return false;
        }

        foreach ($this->getAllItems() as $item) {
            if ($item->getQtyCanceled() === $item->getQtyOrdered()) {
                continue;
            }

            /**
             * In case the item is not in the list of totally removed items
             * and row total is greater than total paid
             * we should create a new invoice (not for the item but for amount)
             */
            $removedItemsArray = $this->getRemovedItems() ?? [];
            $isRemovedItem     = !empty($removedItemsArray)
                && isset($removedItemsArray[$item->getId()]);
            if ($isRemovedItem) {
                continue;
            }

            if ($item->getQtyToInvoice() > 0 && !$item->getLockedDoInvoice()) {
                return true;
            }

            $totalPaidByItem = $item->getBaseRowInvoiced() - $item->getBaseAmountRefunded();
            $totalItemPrice  = $item->getBaseRowTotalInclTax() - $item->getQtyCanceled() * $item->getBasePriceInclTax();

            $totalDueByItemExist = $totalItemPrice > $totalPaidByItem;
            $isRefundedItem      = $item->getQtyOrdered() === $item->getQtyRefunded();

            if ($totalDueByItemExist && !$isRefundedItem) {
                return true;
            }
        }

        if ($this->getBaseShippingInclTax() > $this->getBaseShippingInvoiced()) {
            return true;
        }

        return false;
    }

    /**
     * Get Items
     *
     * @return \Magento\Sales\Api\Data\OrderItemInterface[]
     */
    public function getItems(): ?array
    {
        if ($this->getData(OrderInterface::ITEMS) == null) {
            $this->_searchCriteriaBuilder->addFilter(OrderItemInterface::ORDER_ID, $this->getId());

            $searchCriteria = $this->_searchCriteriaBuilder->create();
            $this->setData(
                OrderInterface::ITEMS,
                $this->oeOrderItemRepository->getList($searchCriteria)->getItems()
            );
        }

        return $this->getData(OrderInterface::ITEMS);
    }

    /**
     * @return array
     */
    public function getRemovedItems(): array
    {
        return $this->removedItems;
    }

    /**
     * @return array
     */
    public function getAddedItems(): array
    {
        return $this->addedItems;
    }

    /**
     * @return array
     */
    public function getIncreasedItems(): array
    {
        return $this->increasedItems;
    }

    /**
     * @return array
     */
    public function getDecreasedItems(): array
    {
        return $this->decreasedItems;
    }
}
