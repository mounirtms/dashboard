<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model\Order;

use Magento\Bundle\Model\Product\Type as BundleProductType;
use Magento\Catalog\Api\Data\ProductCustomOptionInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableProductType;
use Magento\Downloadable\Api\Data\LinkInterface as DownloadableLinkInterface;
use Magento\Downloadable\Model\Link as DownloadableLinkModel;
use Magento\Downloadable\Model\Link\Purchased\ItemFactory as PurchasedItemFactory;
use Magento\Downloadable\Model\Link\PurchasedFactory;
use Magento\Downloadable\Model\Product\Type as DownloadableProductType;
use Magento\Downloadable\Model\ResourceModel\Link\Purchased\CollectionFactory as LinkPurchasedCollectionFactory;
use Magento\Downloadable\Model\ResourceModel\Link\Purchased\Item\CollectionFactory as LinkPurchasedItemCollectionFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObject;
use Magento\Framework\DataObject\Copy as DataObjectCopy;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;
use Magento\Quote\Model\Quote\Item\Option;
use Magento\Quote\Model\Quote\Item\ToOrderItem as QuoteItemToOrderItemConverter;
use Magento\Quote\Model\Quote\Item\Updater;
use Magento\Quote\Model\Quote\Item\Updater as QuoteItemUpdater;
use Magento\Sales\Model\Order as OriginalOrder;
use Magento\Sales\Model\Order\Item as OriginalOrderItem;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;
use MageWorx\OrderEditor\Api\ChangeLoggerInterface;
use MageWorx\OrderEditor\Api\Data\LogMessageInterface;
use MageWorx\OrderEditor\Api\Data\LogMessageInterfaceFactory;
use MageWorx\OrderEditor\Api\OrderItemRepositoryInterface;
use MageWorx\OrderEditor\Api\QuoteItemOptionsInterface;
use MageWorx\OrderEditor\Api\QuoteItemRepositoryInterface;
use MageWorx\OrderEditor\Api\StockManagerInterface;
use MageWorx\OrderEditor\Api\TaxManagerInterface;
use MageWorx\OrderEditor\Helper\Data as Helper;
use MageWorx\OrderEditor\Model\Edit\QuoteFactory as OrderEditorQuoteFactory;
use MageWorx\OrderEditor\Model\Invoice as OrderEditorInvoice;

/**
 * Class Item
 */
class Item extends OriginalOrderItem
{
    const SERIALIZED_OPTION_CODES = [
        'bundle_option_ids',
        'bundle_selection_ids'
    ];

    /**
     * @var array
     */
    protected $newParams = [];

    /**
     * @var array
     */
    protected $changes = [];

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var float
     */
    protected $deltaForComparesOrders = 0.02;

    /**
     * @var mixed
     */
    protected $oldData;

    /**
     * @var PurchasedFactory
     */
    protected $purchasedFactory;

    /**
     * @var LinkPurchasedItemCollectionFactory
     */
    protected $linkPurchasedItemsCollectionFactory;

    /**
     * @var LinkPurchasedCollectionFactory
     */
    protected $linkPurchasedCollectionFactory;

    /**
     * @var DataObjectCopy
     */
    protected $objectCopyService;

    /**
     * @var DownloadableLinkModel
     */
    protected $downloadableLink;

    /**
     * @var float
     */
    protected $increasedQty;

    /**
     * @var float
     */
    protected $decreasedQty;

    /**
     * @var OrderEditorInvoice
     */
    protected $invoice;

    /**
     * @var TaxManagerInterface
     */
    protected $taxManager;

    /**
     * @var TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var QuoteItemOptionsInterface
     */
    protected $quoteItemOptionsManager;

    /**
     * @var MessageManagerInterface
     */
    private $messageManager;

    /**
     * @var OrderEditorQuoteFactory
     */
    private $orderEditorQuoteFactory;

    /**
     * @var OrderItemRepositoryInterface
     */
    private $oeOrderItemRepository;

    /**
     * @var PurchasedItemFactory
     */
    private $purchasedItemFactory;

    /**
     * @var SearchCriteriaBuilderFactory
     */
    protected $searchCriteriaBuilderFactory;

    /**
     * @var QuoteItemRepositoryInterface
     */
    private $quoteItemRepository;

    /**
     * @var QuoteItemToOrderItemConverter
     */
    private $quoteItemToOrderItemConverter;

    /**
     * @var SerializerJson
     */
    protected $serializerJson;

    /**
     * @var Updater
     */
    protected $quoteItemUpdater;

    /**
     * @var StockManagerInterface
     */
    protected $stockManager;

    /**
     * @var LogMessageInterfaceFactory
     */
    protected $logMessageFactory;

    /**
     * @var ChangeLoggerInterface
     */
    protected $changeLogger;

    protected array $optionDateTypes = [
        ProductCustomOptionInterface::OPTION_TYPE_DATE,
        ProductCustomOptionInterface::OPTION_TYPE_DATE_TIME,
        ProductCustomOptionInterface::OPTION_TYPE_TIME
    ];

    protected array $optionMultipleTypes = [
        ProductCustomOptionInterface::OPTION_TYPE_CHECKBOX,
        ProductCustomOptionInterface::OPTION_TYPE_MULTIPLE
    ];

    protected array $preparedOptionData = [];

    /**
     * Item constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param OrderFactory $orderFactory
     * @param StoreManagerInterface $storeManager
     * @param ProductRepositoryInterface $productRepository
     * @param Helper $helper
     * @param PurchasedFactory $purchasedFactory
     * @param PurchasedItemFactory $purchasedItemFactory
     * @param LinkPurchasedCollectionFactory $linkPurchasedCollectionFactory
     * @param LinkPurchasedItemCollectionFactory $linkPurchasedItemsCollectionFactory
     * @param DataObjectCopy $objectCopyService
     * @param DownloadableLinkModel $downloadableLink
     * @param OrderEditorInvoice $invoice
     * @param TaxManagerInterface $taxManager
     * @param TransactionFactory $transactionFactory
     * @param QuoteItemRepositoryInterface $quoteItemRepository
     * @param MessageManagerInterface $messageManager
     * @param OrderEditorQuoteFactory $orderEditorQuoteFactory
     * @param OrderItemRepositoryInterface $oeOrderItemRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param QuoteItemToOrderItemConverter $quoteItemToOrderItemConverter
     * @param SerializerJson $serializerJson
     * @param QuoteItemUpdater $quoteItemUpdater
     * @param StockManagerInterface $stockManager
     * @param LogMessageInterfaceFactory $logMessageFactory
     * @param ChangeLoggerInterface $changeLogger
     * @param QuoteItemOptionsInterface $quoteItemOptionsManager
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        OrderFactory $orderFactory,
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository,
        Helper $helper,
        PurchasedFactory $purchasedFactory,
        PurchasedItemFactory $purchasedItemFactory,
        LinkPurchasedCollectionFactory $linkPurchasedCollectionFactory,
        LinkPurchasedItemCollectionFactory $linkPurchasedItemsCollectionFactory,
        DataObjectCopy $objectCopyService,
        DownloadableLinkModel $downloadableLink,
        OrderEditorInvoice $invoice,
        TaxManagerInterface $taxManager,
        TransactionFactory $transactionFactory,
        QuoteItemRepositoryInterface $quoteItemRepository,
        MessageManagerInterface $messageManager,
        OrderEditorQuoteFactory $orderEditorQuoteFactory,
        OrderItemRepositoryInterface $oeOrderItemRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        QuoteItemToOrderItemConverter $quoteItemToOrderItemConverter,
        SerializerJson $serializerJson,
        Updater $quoteItemUpdater,
        StockManagerInterface $stockManager,
        LogMessageInterfaceFactory $logMessageFactory,
        ChangeLoggerInterface $changeLogger,
        QuoteItemOptionsInterface $quoteItemOptionsManager,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $orderFactory,
            $storeManager,
            $productRepository,
            $resource,
            $resourceCollection,
            $data
        );

        $this->purchasedFactory                    = $purchasedFactory;
        $this->purchasedItemFactory                = $purchasedItemFactory;
        $this->helper                              = $helper;
        $this->linkPurchasedCollectionFactory      = $linkPurchasedCollectionFactory;
        $this->linkPurchasedItemsCollectionFactory = $linkPurchasedItemsCollectionFactory;
        $this->objectCopyService                   = $objectCopyService;
        $this->downloadableLink                    = $downloadableLink;
        $this->invoice                             = $invoice;
        $this->taxManager                          = $taxManager;
        $this->transactionFactory                  = $transactionFactory;
        $this->quoteItemRepository                 = $quoteItemRepository;
        $this->messageManager                      = $messageManager;
        $this->orderEditorQuoteFactory             = $orderEditorQuoteFactory;
        $this->oeOrderItemRepository               = $oeOrderItemRepository;
        $this->searchCriteriaBuilderFactory        = $searchCriteriaBuilderFactory;
        $this->quoteItemToOrderItemConverter       = $quoteItemToOrderItemConverter;
        $this->serializerJson                      = $serializerJson;
        $this->quoteItemUpdater                    = $quoteItemUpdater;
        $this->stockManager                        = $stockManager;
        $this->logMessageFactory                   = $logMessageFactory;
        $this->changeLogger                        = $changeLogger;
        $this->quoteItemOptionsManager             = $quoteItemOptionsManager;
    }

    /**
     * @param string[] $params
     * @param OriginalOrder $order
     * @return $this|OriginalOrderItem
     * @throws \Exception
     */
    public function editItem(array $params, OriginalOrder $order): OriginalOrderItem
    {
        $this->initLogger();

        $this->initParams($params, $order);

        // Do not edit item with removed status (when qty of the item is 0 or less)
        if (isset($params['fact_qty']) && $params['fact_qty'] <= 0) {
            return $this;
        }

        // remove item
        if ($this->isRemovedItem()) {
            $this->removeOrderItem();

            return $this;
        }

        if ($this->getName()) {
            $this->logChanges(
                $this->logMessageFactory->create(
                    ['message' => __('Changes for <b>%1</b>:', $this->getName()), 'level' => 0, 'couldBeEmpty' => false]
                )
            );
        }

        // add new item
        if ($this->isNewItem()) {
            return $this->addNewOrderItem();
        }

        // edit item
        $this->editOrderItem();

        return $this;
    }

    /**
     * @param string[] $params
     * @param OriginalOrder $order
     * @return void
     */
    protected function initParams(array $params, OriginalOrder $order)
    {
        $this->newParams = $params;
        $this->setOrder($order);
    }

    /**
     * @return bool
     */
    protected function isRemovedItem(): bool
    {
        return isset($this->newParams['action'])
            && $this->newParams['action'] == 'remove';
    }

    /**
     * @return bool
     */
    public function isNewItem(): bool
    {
        return isset($this->newParams['item_type'])
            && $this->newParams['item_type'] == 'quote'
            && !$this->getId();
    }

    /**
     * @return void
     */
    public function removeOrderItem()
    {
        try {
            $this->removeDownloadablePurchasedLinks();
            $this->removeRelatedOrderItems();

            if ((float)$this->getQtyInvoiced() < 0.00001) { // Item is not invoiced yet
                /**
                 * We must set qty shipped to 0 before the cancel method call,
                 * because it used in magento to calculate how much qty to be cancelled.
                 * (simplified: [ordered_qty - shipped_qty])
                 */
                $this->setQtyShipped(0);
                $this->cancel();
            } else {
                $this->returnProductInStock($this->getQtyCanceled() + $this->getQtyRefunded());
            }

            $this->decreasedQty = $this->getQtyOrdered() - $this->getQtyCanceled();

            $this->logChanges(
                $this->logMessageFactory->create(
                    [
                        'message' => __(
                            'Product <b>%1</b> has been removed',
                            $this->getProduct() ? $this->getProduct()->getName() : $this->getSku()
                        ),
                        'level'   => 0
                    ]
                )
            );

            /*
             * Do not remove item physically: refund it
             * (but not in case when delete and create sales processor in action)
             */
            if ($this->helper->getSalesProcessorCode() === 'delete_and_create') {
                $this->oeOrderItemRepository->delete($this);
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error while removing order items: %1', $e->getMessage()));

            return;
        }
    }

    /**
     * @return void
     */
    protected function removeRelatedOrderItems(): void
    {
        if ($this->getProductType() === ConfigurableProductType::TYPE_CODE
            || $this->getProductType() === BundleProductType::TYPE_CODE
        ) {
            $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
            $searchCriteriaBuilder->addFilter('parent_item_id', $this->getItemId());
            $searchCriteria = $searchCriteriaBuilder->create();
            $searchResult   = $this->oeOrderItemRepository->getList($searchCriteria);

            $simpleOrderItems = $searchResult->getItems();

            /**
             * @var Item $simpleOrderItem
             */
            foreach ($simpleOrderItems as $simpleOrderItem) {
                $this->returnProductInStock(0, $simpleOrderItem);

                /*
                 * Remove order item physically in case when delete and create sales processor in action
                 */
                if ($this->helper->getSalesProcessorCode() === 'delete_and_create') {
                    $this->oeOrderItemRepository->delete($simpleOrderItem);

                    continue;
                }

                try {
                    if ($simpleOrderItem->canRefund()) {
                        $simpleOrderItem->setQtyRefunded(
                            $simpleOrderItem->getQtyOrdered() - $simpleOrderItem->getQtyCanceled()
                        );
                    } else {
                        $simpleOrderItem->setQtyCanceled(
                            $simpleOrderItem->getQtyOrdered() - $simpleOrderItem->getQtyCanceled()
                        );
                    }
                } catch (\Exception $e) {
                    $this->messageManager->addErrorMessage(
                        __('Error while removing related order items: %1', $e->getMessage())
                    );

                    continue;
                }
            }
        }
    }

    /**
     * @return void
     * @var Item $orderItem
     */
    protected function removeQuoteItem($orderItem)
    {
        if (!$orderItem->getQuoteItemId()) {
            return;
        }

        try {
            $this->quoteItemRepository->deleteById($orderItem->getQuoteItemId());
        } catch (NoSuchEntityException $noSuchEntityException) {
            // Quote item not found, processing without quote item
            return;
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error while removing quote items: %1', $e->getMessage()));

            return;
        }
    }

    /**
     * @return void
     */
    protected function removeDownloadablePurchasedLinks()
    {
        if ($this->getProductType() === DownloadableProductType::TYPE_DOWNLOADABLE) {
            $purchased = $this->purchasedFactory->create()->load(
                $this->getId(),
                'order_item_id'
            );

            try {
                $purchased->delete();
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(
                    __('Error while removing downloadable product links: %1', $e->getMessage())
                );

                return;
            }
        }
    }

    /**
     * @param \MageWorx\OrderEditor\Model\Quote\Item $quoteItem
     */
    protected function changeQuoteItemParams(\MageWorx\OrderEditor\Model\Quote\Item $quoteItem)
    {
        $quote = $this->helper->getQuote();
        $quoteItem->setQuote($quote);
        $quoteItem->loadOptions();

        $qty = $this->newParams['fact_qty'] ?? 0;
        if ($qty) {
            $this->quoteItemUpdater->update($quoteItem, ['qty' => $qty]);
        }
    }

    /**
     * @return OriginalOrderItem
     * @throws LocalizedException
     */
    protected function addNewOrderItem(): OriginalOrderItem
    {
        if (!isset($this->newParams['item_id'])) {
            throw new LocalizedException(__('Item id is not defined'));
        }

        $quoteItemId = $this->newParams['item_id'];

        /** @var \MageWorx\OrderEditor\Model\Edit\Quote $converter */
        $converter = $this->orderEditorQuoteFactory->create();

        $quoteItem = $this->quoteItemRepository->getById($quoteItemId);
        $this->changeQuoteItemParams($quoteItem);
        $attributes = $quoteItem->getOptionByCode('attributes');
        $params     = [];
        if ($attributes) {
            $decodedOptionValue = $this->helper->unserialize($attributes->getValue());
            if ($decodedOptionValue) {
                foreach ($decodedOptionValue as $optionId => $valueId) {
                    $params['super_attribute'][$optionId] = $valueId;
                }
            }
        }

        $options = $quoteItem->getOptions() ?? [];
        foreach ($options as $option) {
            if ($option->getCode() === 'info_buyRequest') {
                $value  = $this->helper->unserialize($option->getValue());
                $params += $value;
            } else {
                if (in_array($option->getCode(), static::SERIALIZED_OPTION_CODES)) {
                    $value = $this->helper->unserialize($option->getValue());
                } else {
                    $value = $option->getValue();
                }
                $params[$option->getCode()] = $value;
            }
        }

        if (!empty($this->newParams['fact_qty'])) {
            $params['qty'] = $this->newParams['fact_qty'];
        }

        $params['is_new_item'] = true;

        /** @var Item $orderItem */
        $orderItem = $converter->getUpdatedOrderItem($quoteItemId, $params);
        $qty       = array_key_exists('fact_qty', $this->newParams) ? $this->newParams['fact_qty'] : 0;
        $orderItem->setData('qty_ordered', $qty);

        $this->saveWithChildItems($orderItem);

        try {
            $this->stockManager->registerSale($orderItem, $qty);
        } catch (\Exception $exception) {
            $this->_logger->error(__('Register sale error:'));
            $this->_logger->error($exception->getMessage());
            $this->_logger->error($exception->getTraceAsString());
        }

        $this->_eventManager->dispatch(
            'mw_ordereditor_new_item_added',
            [
                'order_item' => $orderItem,
            ]
        );

        $this->logChanges(
            $this->logMessageFactory->create(
                [
                    'message' => __(
                        'Product <b>%1</b> has been added',
                        $orderItem->getProduct() ? $orderItem->getProduct()->getName() : $orderItem->getSku()
                    ),
                    'level'   => 0
                ]
            ),
            'item_' . $orderItem->getId()
        );

        try {
            $recentlyAddedOrderItem = $this->oeOrderItemRepository->getById($orderItem->getId());
            $recentlyAddedOrderItem->initParams($this->newParams, $this->getOrder());
            $recentlyAddedOrderItem->editOrderItem();
        } catch (\Exception $e) {
            $this->_logger->critical($e);
        }

        return !empty($recentlyAddedOrderItem) ? $recentlyAddedOrderItem : $orderItem;
    }

    /**
     * @param OriginalOrderItem $orderItem
     */
    private function saveWithChildItems(OriginalOrderItem $orderItem)
    {
        $orderItems = [$orderItem];
        if ($orderItem->getChildrenItems()) {
            $orderItems += $orderItem->getChildrenItems();
        }

        foreach ($orderItems as $orderItemToSave) {
            $orderItemToSave->setData('order_id', $this->getOrder()->getId());
            $orderItemToSave->setData('store_id', $this->getOrder()->getStoreId());

            try {
                $this->oeOrderItemRepository->save($orderItemToSave);
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(
                    __('Error while adding (saving) new order item: %1', $e->getMessage())
                );
            }
        }
    }

    /**
     * @return void
     */
    protected function cancelInvoices()
    {
        $invoices = $this->invoice
            ->getCollection()
            ->addFieldToFilter('order_id', $this->getOrderId());

        /** @var $invoice OrderEditorInvoice */
        foreach ($invoices as $invoice) {
            try {
                $invoice->cancel();
                $this->transactionFactory->create()->addObject($invoice->getOrder())->save();
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('Error while canceling invoices: %1', $e->getMessage()));

                continue;
            }
        }
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function editOrderItem()
    {
        $this->saveOldData();

        $options = empty($this->newParams['product_options']) ? [] : $this->newParams['product_options'];
        if ($options) {
            $options = is_string($options)
                ? $this->helper->decodeBuyRequestValue($options)
                : $options;
            $this->updateProductOptions($options);
        }

        $this->updateQty(); // Just update qty, @TODO but must be reviewed for qty refunded\cancelled errors
        $this->updateItemData();
        $this->updateOrderTaxItemTable();

        $this->detectChangesInAmounts();
    }

    /**
     * @return void
     */
    protected function saveOldData()
    {
        $this->oldData = $this->getData();
    }

    /**
     * Compare old and new data in amounts.
     * Save the changes in the $this->changes property.
     *
     * @return void
     */
    protected function detectChangesInAmounts()
    {
        $map = [
            'row_total',
            'base_row_total',
            'tax_refunded',
            'base_tax_amount',
            'discount_amount',
            'base_discount_amount'
        ];

        $oldItem     = (clone $this)->setData($this->oldData);
        $oldRowTotal = $this->getOrderItemRowTotal($oldItem);
        $newRowTotal = $this->getOrderItemRowTotal($this);

        if (abs($oldRowTotal - $newRowTotal) >= $this->deltaForComparesOrders) {
            foreach ($map as $i) {
                if (isset($this->oldData[$i])) {
                    $oldValue          = $this->oldData[$i];
                    $newValue          = $this->getData($i);
                    $this->changes[$i] = $newValue - $oldValue;
                }
            }
        }
    }

    /**
     * @return string[]
     */
    public function getChangesInAmounts()
    {
        return $this->changes;
    }

    /**
     * @param array|null $productOptions
     * @return void
     */
    protected function updateProductOptions(?array $productOptions = []): void
    {
        if (empty($productOptions)) {
            return;
        }

        if ($this->getProductType() === DownloadableProductType::TYPE_DOWNLOADABLE) {
            $this->editDownloadItem();
        } elseif ($this->getProductType() === BundleProductType::TYPE_CODE) {
            $this->editBundleItem();
        } elseif ($this->getProduct()->getOptions()) {
            $this->editCustomOptions($productOptions);
        } else {
            $this->editConfigurableItem($productOptions);
        }

        try {
            $this->oeOrderItemRepository->save($this);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Error while saving order item: %1', $e->getMessage())
            );

            return;
        }
    }

    /**
     * @param array|null $productOptions
     * @return void
     */
    protected function editCustomOptions(?array $productOptions = []): void
    {
        $oldSku = $this->getSku();
        if (!empty($this->newParams['options'])) {
            $rawOptions = $this->newParams['options'];
        } else {
            $rawOptions = $productOptions['info_buyRequest']['options'] ?? [];
        }

        $buyRequest = $this->getBuyRequest();
        $buyRequest->setOptions($rawOptions);

        $product              = $this->getProduct();
        $productCustomOptions = $product->getOptions();

        $this->preparedOptionData = [];

        $optionsFromRequest = $buyRequest->getOptions();

        $quoteItem                = $this->quoteItemRepository->getById($this->getQuoteItemId());
        $quoteOptions             = $this->quoteItemOptionsManager->getOptionsByItem($quoteItem);
        $results = $this->prepareOptionData(
            $productCustomOptions,
            $product,
            $buyRequest,
            $quoteOptions,
            $optionsFromRequest
        );

        $this->setProductOptions(
            [
                'info_buyRequest' => $buyRequest->getData(),
                'options'         => $results
            ]
        );
        $newSku = $this->updateSkuAfterUpdateOptions();
        $this->updateInventoryAfterUpdateOptions($oldSku, $newSku);
    }

    /**
     * Prepare correct option data to save
     *
     * @param Product\Option $productCustomOptions
     * @param Product $product
     * @param DataObject $buyRequest
     * @param Option $quoteOptions
     * @param array $optionsFromRequest
     * @return array
     * @throws LocalizedException
     */
    protected function prepareOptionData(
        array $productCustomOptions,
        Product $product,
        DataObject $buyRequest,
        array $quoteOptions,
        array $optionsFromRequest
    ): array {
        foreach ($productCustomOptions as $option) {
            /** @var \Magento\Catalog\Model\Product\Option $option */
            /** @var \Magento\Catalog\Model\Product\Option\Type\DefaultType $group */
            $group = $option->groupFactory($option->getType())
                            ->setOption($option)
                            ->setProduct($product)
                            ->setRequest($buyRequest)
                            ->setProcessMode(AbstractType::PROCESS_MODE_FULL);

            // In cases like APO skuPolicy independent, we might not have option in quote item
            if (empty($quoteOptions['option_' . $option->getId()])) {
                continue;
            }

            $quoteItemOptionObject = $quoteOptions['option_' . $option->getId()];
            $group->setData('quote_item_option', $quoteItemOptionObject);

            if ($product->getSkipCheckRequiredOption() !== true) {
                $group->validateUserValue($optionsFromRequest);
            }

            if ($optionsFromRequest !== null && isset($optionsFromRequest[$option->getId()])) {
                $optionType        = $option->getType();
                $optionFromRequest = $optionsFromRequest[$option->getId()];

                if (in_array($optionType, $this->optionDateTypes) && isset($optionFromRequest['date_internal'])) {
                    $optionFromRequest = $optionFromRequest['date_internal'];
                }

                if (in_array($optionType, $this->optionMultipleTypes)) {
                    $optionValueForFormatter = implode(',', $optionFromRequest);
                } else {
                    $optionValueForFormatter = is_array($optionFromRequest) ?
                    $this->serializerJson->serialize($optionFromRequest) :
                    $optionFromRequest;
                }
                $formattedValue          = $group->getFormattedOptionValue($optionValueForFormatter);
                $valueDirect             = $quoteItemOptionObject->getValue();

                if (!empty($valueDirect)) { // Do not display empty options in product info block
                    $this->preparedOptionData[] = [
                        'label'        => $option->getTitle(),
                        'value'        => $formattedValue,
                        'print_value'  => $group->getPrintableOptionValue($valueDirect),
                        'option_id'    => $option->getId(),
                        'option_type'  => $option->getType(),
                        'option_value' => $valueDirect,
                        'custom_view'  => $group->isCustomizedView()
                    ];
                }
            }
        }

        return $this->preparedOptionData;
    }

    /**
     * @return string
     */
    protected function updateSkuAfterUpdateOptions(): string
    {
        $productOptions = $this->getData('product_options');
        $options        = is_string($productOptions)
            ? $this->helper->decodeBuyRequestValue($productOptions)
            : $productOptions;

        if (!empty($options['simple_sku'])) {
            $this->setSku($options['simple_sku']);
        } else {
            if (!empty($this->newParams['sku'])) {
                $this->setSku($this->newParams['sku']);
            }
        }

        if (!empty($options['simple_name'])) {
            $this->setName($options['simple_name']);
        }

        return $this->getSku();
    }

    /**
     * @param string $oldSimpleSku
     * @param string $newSimpleSku
     * @return void
     */
    protected function updateInventoryAfterUpdateOptions(
        string $oldSimpleSku,
        string $newSimpleSku
    ) {
        if ($oldSimpleSku === $newSimpleSku) {
            return;
        }

        // update product id for simple product
        try {
            $oldProductId = $this->oldData['product_id'];

            // prepare qty
            $qty = $this->getQtyOrdered() - $this->getQtyRefunded() - $this->getQtyCanceled();
            $qty = $qty < 0 ? 0 : $qty;

            /**
             * @TODO make compatible (replace) with \MageWorx\OrderEditor\Api\StockManagerInterface::registerReturn()
             * to avoid method multiplication
             */
            // back to inventory OLD item
            $websiteId = $this->getStore()->getWebsiteId();
            try {
                $this->stockManager->registerReturnByProductId($oldProductId, $qty, $websiteId);
            } catch (\Exception $exception) {
                $this->_logger->error(__('Register sale error:'));
                $this->_logger->error($exception->getMessage());
                $this->_logger->error($exception->getTraceAsString());
            }

            // get from inventory NEW item // @TODO: remove this?
            $this->setQtyOrdered(0);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Error while updating inventory after updating product options: %1', $e->getMessage())
            );

            return;
        }
    }

    /**
     * @return void
     */
    protected function editDownloadItem()
    {
        if ($this->getProductType() !== DownloadableProductType::TYPE_DOWNLOADABLE) {
            return;
        }

        $newLinks = $this->getOptionLinks($this->newParams['product_options']);
        $oldLinks = $this->getOptionLinks($this->getData('product_options'));

        $added = array_diff($newLinks, $oldLinks);
        foreach ($added as $linkId) {
            $this->addDownloadableLink($linkId);
        }

        $removed = array_diff($oldLinks, $newLinks);
        foreach ($removed as $linkId) {
            $this->removeDownloadableLink($linkId);
        }
    }

    /**
     * @return void
     */
    protected function editConfigurableItem($newProductOptions = [])
    {
        // options
        $this->setData('product_options', $newProductOptions);

        $oldSimpleSku = $this->getSku();
        $newSimpleSku = $this->updateSkuAfterUpdateOptions();
        if ($oldSimpleSku != $newSimpleSku) {
            $children = $this->getChildrenItems();
            /** @var OriginalOrderItem $child */
            foreach ($children as $child) {
                if ($child->getSku() == $oldSimpleSku) {
                    $child->setSku($newSimpleSku);
                    try {
                        $newChildProduct = $this->productRepository->get($newSimpleSku);
                        $child->setProductId($newChildProduct->getId());
                        $child->setName($newChildProduct->getName());
                        $child->setWeight($newChildProduct->getWeight());

                        if (!$child->getQuoteItemId()) {
                            throw new LocalizedException(
                                __(
                                    'Child quote item have no ID. Parent ID: %1 , child SKU: %2',
                                    $this->getId(),
                                    $oldSimpleSku
                                )
                            );
                        }

                        /** @var \MageWorx\OrderEditor\Model\Quote\Item $childQuoteItem */
                        $childQuoteItem = $this->quoteItemRepository->getById($child->getQuoteItemId());
                        $childQuoteItem->setProductId($newChildProduct->getId());
                        $childQuoteItem->setName($newChildProduct->getName());
                        $childQuoteItem->setWeight($newChildProduct->getWeight());
                        $childQuoteItem->setProduct($newChildProduct);
                        try {
                            $this->quoteItemRepository->save($childQuoteItem);
                        } catch (LocalizedException $e) {
                            $this->messageManager->addErrorMessage(
                                __(
                                    'Unable to save child quote item with new SKU %1 and Product ID %2',
                                    $newSimpleSku,
                                    $newChildProduct->getId()
                                )
                            );
                            $this->messageManager->addExceptionMessage($e);
                        }

                        try {
                            $this->oeOrderItemRepository->save($child);
                        } catch (LocalizedException $e) {
                            $this->messageManager->addErrorMessage(
                                __(
                                    'Unable to save child order item with new SKU %1 and Product ID %2',
                                    $newSimpleSku,
                                    $newChildProduct->getId()
                                )
                            );
                            $this->messageManager->addExceptionMessage($e);
                        }
                    } catch (NoSuchEntityException $e) {
                        $this->messageManager->addErrorMessage(
                            __('Unable to load child product for new child order item with SKU %1', $newSimpleSku)
                        );
                        $this->messageManager->addExceptionMessage($e);
                    }
                }
            }
        }

        $this->updateInventoryAfterUpdateOptions($oldSimpleSku, $newSimpleSku);
    }

    /**
     * @return void
     */
    protected function editBundleItem()
    {
        if ($this->getProductType() !== BundleProductType::TYPE_CODE) {
            return;
        }

        /** @var \MageWorx\OrderEditor\Model\Order\Item[] $childItems */
        $childItems       = $this->getChildrenItems();
        $bundleOptions    = $this->newParams['bundle_option'] ?? [];
        $bundleOptionsQty = $this->newParams['bundle_option_qty'] ?? [];
        if (empty($bundleOptions) || empty($bundleOptionsQty)) {
            return;
        }

        $oldBuyRequest       = $this->getBuyRequest();
        $oldBundleOptions    = $oldBuyRequest->getData('bundle_option');
        $oldBundleOptionsQty = $oldBuyRequest->getData('bundle_option_qty');

        $removedOptions = array_diff(array_keys($bundleOptions), array_keys($oldBundleOptions));
        $addedOptions   = array_diff(array_keys($oldBundleOptions), array_keys($bundleOptions));

        $qtyChanges = [];
        foreach ($oldBundleOptionsQty as $optionId => $oldBundleOptionQty) {
            $newQty = $bundleOptionsQty[$optionId];
            if ($oldBundleOptionQty !== $newQty) {
                $qtyChanges[$optionId] = $newQty;
            }
        }

        $product = $this->getProduct();
        /** @var \Magento\Bundle\Model\Product\Type $productTypeInstance */
        $productTypeInstance = $product->getTypeInstance();
        $buyRequest          = new \Magento\Framework\DataObject($this->newParams);
        $candidates          = $productTypeInstance->prepareForCart($buyRequest, $product);

        $newProducts = [];
        foreach ($candidates as $candidate) {
            if (!$candidate->getParentProductId()) {
                continue;
            }
            $optionId                                     = $candidate->getOptionId();
            $newProducts[$optionId][$candidate->getSku()] = $candidate;
        }

        $oldItems = [];
        foreach ($childItems as $childItem) {
            $productOptions                                          = $childItem->getProductOptions();
            $bundleSelectionAttributes                               = $this->serializerJson->unserialize(
                $productOptions['bundle_selection_attributes']
            );
            $optionId                                                = $bundleSelectionAttributes['option_id'];
            $oldItems[$optionId][$childItem->getProduct()->getSku()] = $childItem;
            if (!empty($qtyChanges[$optionId])) {
                $qty = $qtyChanges[$optionId];
                $this->updateQtyProduct($qty, $childItem); // Update qty and SAVE updated item
            }
        }

        // Remove items
        foreach ($removedOptions as $optionId) {
            if (!empty($oldItems[$optionId])) {
                /** @var \MageWorx\OrderEditor\Model\Order\Item $oldItem */
                foreach ($oldItems[$optionId] as $oldItem) {
                    $oldItem->removeOrderItem();
                }
            }
        }

        // Update items
        $quote = $this->helper->getQuoteByOrder($this->getOrder());
        foreach ($newProducts as $optionId => $newProductList) {
            $oldItemsList = $oldItems[$optionId] ?? [];
            $diff         = array_diff(array_keys($newProductList), array_keys($oldItemsList));
            if (empty($diff)) {
                continue; // options are same
            }

            /** @var \Magento\Catalog\Model\Product $newProduct */
            foreach ($newProductList as $newProduct) {
                $newQuoteItem = $this->quoteItemRepository->getEmptyEntity();
                $options      = $newProduct->getCustomOptions();
                $newQuoteItem->setQuoteId($this->getOrder()->getQuoteId())
                             ->setQuote($quote)
                             ->setProduct($newProduct)
                             ->setParentItemId($this->getQuoteItemId())
                             ->setParentItem($quote->getItemById($this->getQuoteItemId()))
                             ->setQty($bundleOptionsQty[$optionId])
                             ->setOptions($options)
                             ->setPrice($newProduct->getFinalPrice());

                $quote->addItem($newQuoteItem);

                $quoteItemsToSave[] = $this->quoteItemRepository->save($newQuoteItem);
            }

            /**
             * @var string $oldItemSku
             * @var \MageWorx\OrderEditor\Model\Order\Item $oldItem
             */
            foreach ($oldItemsList as $oldItemSku => $oldItem) {
                if (empty($newProductList[$oldItemSku])) {
                    $oldItem->removeOrderItem();
                }
            }
        }

        // Calculate totals
        if (!empty($quoteItemsToSave)) {
            $quote->collectTotals();
            // Converting quote items to order items
            foreach ($quoteItemsToSave as $quoteItem) {
                try {
                    /** @var \MageWorx\OrderEditor\Model\Order\Item $orderItem */
                    $orderItem = $this->quoteItemToOrderItemConverter->convert($quoteItem);
                    $orderItem->setOrder($this->getOrder());
                    $orderItem->setOrderId($this->getOrderId());
                    $orderItem->setParentItemId($this->getId());
                    $orderItem->setParentItem($this);
                    $orderItem->setAppliedTaxes($quoteItem->getAppliedTaxes());

                    $newOrderItem = $this->oeOrderItemRepository->save($orderItem);
                } catch (\Exception $e) {
                    $this->messageManager->addErrorMessage($e->getMessage());

                    throw $e;
                }
            }
        }
    }

    /**
     * @TODO Check for errors with encoded value in the $productOptions , should be array-type
     *
     * @param string[] $productOptions
     * @return string[]
     */
    protected function getOptionLinks(array $productOptions): array
    {
        return isset($productOptions['links'])
            ? $productOptions['links']
            : [];
    }

    /**
     * @param int $linkId
     * @return void
     */
    protected function addDownloadableLink(int $linkId)
    {
        $linkPurchasedItem = $this->purchasedItemFactory->create();

        $linkPurchasedId = $this->getLinkPurchasedIdForOrderItem();

        $this->objectCopyService->copyFieldsetToTarget(
            'downloadable_sales_copy_link',
            'to_purchased',
            $linkId,
            $linkPurchasedItem
        );

        $hash     = microtime() . $linkPurchasedId . $this->getId() . $this->getProductId();
        $linkHash = strtr(base64_encode($hash), '+/=', '-_,');

        /**
         * @var $link DownloadableLinkModel
         */
        $link = $this->downloadableLink->getCollection()
                                       ->addTitleToResult()
                                       ->addFieldToFilter('main_table.link_id', $linkId)
                                       ->getFirstItem();

        $numberOfDownloads = $link->getNumberOfDownloads() * $this->getQtyOrdered();

        try {
            $linkPurchasedItem
                ->setPurchasedId($linkPurchasedId)
                ->setOrderItemId($this->getId())
                ->setLinkHash($linkHash)
                ->setNumberOfDownloadsBought($numberOfDownloads)
                ->setStatus(\Magento\Downloadable\Model\Link\Purchased\Item::LINK_STATUS_PENDING)
                ->setCreatedAt($this->getCreatedAt())
                ->setUpdatedAt($this->getUpdatedAt())
                ->setProductId($this->getProductId())
                ->setLinkId($link->getId())
                ->setIsShareable($link->getIsShareable())
                ->setLinkUrl($link->getLinkUrl())
                ->setLinkFile($link->getLinkFile())
                ->setLinkType($link->getLinkType())
                ->setLinkTitle($link->getDefaultTitle());
            $linkPurchasedItem->save();
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Error while adding downloadable product link: %1', $e->getMessage())
            );

            return;
        }
    }

    /**
     * @return int
     */
    protected function getLinkPurchasedIdForOrderItem(): int
    {
        $collection = $this->linkPurchasedCollectionFactory
            ->create()
            ->addFieldToFilter('order_item_id', $this->getId());

        if ($collection->getSize() > 0) {
            return (int)$collection->getFirstItem()->getId();
        }

        return 0;
    }

    /**
     * @param int $linkId
     * @return void
     */
    protected function removeDownloadableLink(int $linkId)
    {
        $purchasedItems = $this->linkPurchasedItemsCollectionFactory
            ->create()
            ->addFieldToFilter('order_item_id', $this->getId())
            ->addFieldToFilter('link_id', $linkId);

        /** @var DownloadableLinkInterface|DownloadableLinkModel $link */
        foreach ($purchasedItems as $link) {
            try {
                $link->delete();
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(
                    __('Error while removing downloadable product link: %1', $e->getMessage())
                );

                continue;
            }
        }
    }

    /**
     * Remove taxes which not exist in $taxRates
     *
     * @param \MageWorx\OrderEditor\Model\Order\Tax\Item[] $taxItems
     * @param string[] $taxRates
     */
    protected function removeNonExistingTaxes(array $taxItems, array $taxRates = [])
    {
        /** @var \MageWorx\OrderEditor\Model\Order\Tax\Item $taxItem */
        foreach ($taxItems as $taxItem) {
            $rateCode = $taxItem->getData('code');
            if (!in_array($rateCode, $taxRates)) {
                try {
                    // Delete the tax item first, because it has reference for the tax table
                    $taxId = $taxItem->getTaxId();
                    if ($taxId) {
                        $tax = $this->taxManager->getTaxById($taxId);
                        $tax->addData(
                            [
                                "amount"           => $tax->getAmount() - $taxItem->getAmount(),
                                "base_amount"      => $tax->getBaseAmount() - $taxItem->getBaseAmount(),
                                "base_real_amount" => $tax->getBaseRealAmount() - $taxItem->getBaseRealAmount()
                            ]
                        );
                    }
                    $taxItem->delete();
                } catch (\Exception $e) {
                    $this->messageManager->addErrorMessage(
                        __('Error while deleting tax item: %1', $e->getMessage())
                    );

                    continue;
                }

                // Then Delete records from tax table, if exist
                if ($taxId) {
                    if ($tax->getBaseAmount() <= 0.0001) {
                        $this->taxManager->deleteOrderTaxRecordByTaxId($taxId);
                    } else {
                        try {
                            $this->taxManager->saveOrderTax($tax);
                        } catch (LocalizedException $exception) {
                            $this->messageManager->addErrorMessage(
                                __('Error while updating tax entity: %1', $exception->getMessage())
                            );

                            continue;
                        }
                    }
                }
            }
        }
    }

    /**
     * @param string $rateCode
     * @param \MageWorx\OrderEditor\Model\Order\Tax\Item[] $taxItemsByCode
     * @return \MageWorx\OrderEditor\Model\Order\Tax\Item
     */
    protected function getOrderItemTaxItemByCode(
        string $rateCode,
        array $taxItemsByCode = []
    ): \MageWorx\OrderEditor\Model\Order\Tax\Item {
        if (isset($taxItemsByCode[$rateCode])) {
            $taxItem = $taxItemsByCode[$rateCode];
        } else {
            $taxItemsCollection = $this->taxManager->getOrderTaxItemsCollection();
            $taxItem            = $taxItemsCollection->getNewEmptyItem();
        }

        return $taxItem;
    }

    /**
     * Get all tax items (tax code is key)
     *
     * @return \MageWorx\OrderEditor\Model\Order\Tax\Item[]
     */
    public function getTaxItems(): array
    {
        $orderItemTaxItems       = $this->taxManager->getOrderItemTaxItems($this->getId());
        $orderItemTaxItemsByCode = [];

        foreach ($orderItemTaxItems as $item) {
            $orderItemTaxItemsByCode[$item->getData('code')] = $item;
        }

        return $orderItemTaxItemsByCode;
    }

    /**
     * Create/Delete/Update Tax Rates for each of Items
     *
     * @return void
     */
    protected function updateOrderTaxItemTable()
    {
        $taxRates          = $this->newParams['tax_rates'] ?? [];
        $overallTaxPercent = $this->newParams['tax_percent'] ?? 0;
        $overallTaxAmount  = $this->newParams['tax_amount'] ?? 0;

        /** @var \MageWorx\OrderEditor\Model\Order\Tax\Item[] $taxItemsByCode */
        $taxItemsByCode = $this->getTaxItems();

        $this->removeNonExistingTaxes($taxItemsByCode, $taxRates);

        foreach ($taxRates as $rateCode) {
            if ($rateCode instanceof \MageWorx\OrderEditor\Api\Data\OrderManager\TaxRateDataInterface) {
                $this->newParams['tax_applied_rates'][$rateCode->getCode()] = $rateCode->getPercent();
                $rateCode                                                   = $rateCode->getCode();
            }
            $taxItem = $this->getOrderItemTaxItemByCode($rateCode, $taxItemsByCode);

            $percent        = (float)$this->newParams['tax_applied_rates'][$rateCode];
            $amount         = $overallTaxPercent != 0 ? $percent / $overallTaxPercent * $overallTaxAmount : 0;
            $baseAmount     = $this->currencyConvertToBaseCurrency($amount);
            $baseRealAmount = $baseAmount;

            // Order tax entity (not item) which contains sum off all tax items with that code
            $tax      = $this->taxManager->getOrderTaxItemByCodeAndOrderId($rateCode, $this->getOrderId());
            $isTaxNew = false;
            if ($tax->isObjectNew()) {
                /**
                 * We should create new record in the `sales_order_tax` table before saving tax_item
                 * in case it does not exist.
                 */
                $isTaxNew = true;
                $tax->addData(
                    [
                        'order_id'         => $this->getOrderId(),
                        'code'             => $rateCode,
                        'title'            => $rateCode,
                        'percent'          => $percent,
                        'amount'           => $amount,
                        'priority'         => 0,
                        'position'         => 0,
                        'base_amount'      => $baseAmount,
                        'process'          => 0,
                        'base_real_amount' => $baseRealAmount
                    ]
                );
            } else {
                /**
                 * If order tax with this code already exist we are reducing amount by old tax item amount,
                 * and later we will add it with updated value
                 */
                if (!$taxItem->isObjectNew()) {
                    $amountReducedByOldTaxItemAmount         = $tax->getAmount() - $taxItem->getAmount();
                    $baseAmountReducedByOldTaxItemAmount     = $tax->getBaseAmount() - $taxItem->getBaseAmount();
                    $baseRealAmountReducedByOldTaxItemAmount = $tax->getBaseRealAmount() - $taxItem->getBaseRealAmount();

                    $tax->addData(
                        [
                            'percent'          => (float)$percent,
                            'amount'           => max((float)$amountReducedByOldTaxItemAmount, 0),
                            'base_amount'      => max((float)$baseAmountReducedByOldTaxItemAmount, 0),
                            'base_real_amount' => max((float)$baseRealAmountReducedByOldTaxItemAmount, 0)
                        ]
                    );
                }
            }

            try {
                $this->taxManager->saveOrderTax($tax);
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(
                    __('Error while saving tax: %1', $e->getMessage())
                );

                continue;
            }

            $taxId = $tax->getTaxId();

            $taxItem->addData(
                [
                    'tax_id'            => $taxId,
                    'item_id'           => $this->getItemId(),
                    'tax_percent'       => (float)$percent,
                    'amount'            => (float)$amount,
                    'base_amount'       => (float)$baseAmount,
                    'real_amount'       => (float)$amount,
                    'real_base_amount'  => (float)$baseAmount,
                    'taxable_item_type' => 'product'
                ]
            );

            try {
                $this->taxManager->saveOrderTaxItem($taxItem);
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(
                    __('Error while saving tax item: %1', $e->getMessage())
                );

                continue;
            }

            if (!$isTaxNew) {
                // Add tax item amount to tax amount
                $tax->addData(
                    [
                        'percent'          => (float)$taxItem->getTaxPercent(),
                        'amount'           => (float)$tax->getAmount() + (float)$taxItem->getAmount(),
                        'base_amount'      => (float)$tax->getBaseAmount() + (float)$taxItem->getBaseAmount(),
                        'base_real_amount' => (float)$tax->getBaseRealAmount() + (float)$taxItem->getBaseRealAmount()
                    ]
                );

                try {
                    $this->taxManager->saveOrderTax($tax);
                } catch (\Exception $e) {
                    $this->messageManager->addErrorMessage(
                        __('Error while saving tax: %1', $e->getMessage())
                    );

                    continue;
                }
            }
        }
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function updateQty()
    {
        // qty ordered
        $alreadyReturnedQty        = (float)$this->getQtyCanceled() + (float)$this->getQtyRefunded();
        $oldQtyOrdered             = (float)$this->getQtyOrdered() - $alreadyReturnedQty;
        $oldQtyOrderedWithReturned = $oldQtyOrdered + $alreadyReturnedQty;
        $newQty                    = isset($this->newParams['fact_qty'])
            ? (float)$this->newParams['fact_qty'] + $alreadyReturnedQty
            : $oldQtyOrderedWithReturned;

        if ($this->isRemovedItem()) {
            // Remove from stock only not removed items (do not remove twice)
            $newQty -= $alreadyReturnedQty;
        }

        if ($oldQtyOrderedWithReturned === $newQty) {
            return;
        }

        if ($this->getProductType() === ConfigurableProductType::TYPE_CODE) {
            $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
            $searchCriteriaBuilder->addFilter('parent_item_id', $this->getItemId());
            $searchCriteria = $searchCriteriaBuilder->create();
            $searchResult   = $this->oeOrderItemRepository->getList($searchCriteria);

            $simpleOrderItems = $searchResult->getItems();
            if ($searchResult->getTotalCount() > 0) {
                /** @var Item $simpleOrderItem */
                $simpleOrderItem = reset($simpleOrderItems);
                $simpleOrderItem->updateQtyProduct($newQty, $simpleOrderItem);
            }
        }
        $this->updateQtyProduct($newQty, $this);
    }

    /**
     * @param float $newQty
     * @param Item $item
     * @return float
     * @throws \Exception
     */
    protected function updateQtyProduct(float $newQty, Item $item): float
    {
        $originalQtyReturned         = (float)$item->getQtyCanceled() + (float)$item->getQtyRefunded();
        $originalQtyOrdered          = (float)$item->getQtyOrdered() - $originalQtyReturned; // Qty real ordered
        $totalQtyOrderedWithReturned = $originalQtyOrdered + $originalQtyReturned;
        if ($totalQtyOrderedWithReturned == $newQty) {
            return $newQty;
        }

        if ($totalQtyOrderedWithReturned > $newQty) {
            /* product was removed */
            $qtyToRemove        = $originalQtyOrdered - $newQty;
            $this->decreasedQty = $this->returnProductInStock($newQty + $originalQtyReturned, $item);

            // Workaround for old logic: do not cancel items, just update qty ordered
            if ($this->helper->getSalesProcessorCode() === 'delete_and_create') {
                $item->setQtyOrdered($newQty);
            } elseif (0 >= (float)$item->getQtyInvoiced()) {
                // Line for normal logic (new): cancel removed items
                $item->setQtyCanceled((float)$item->getQtyCanceled() + $qtyToRemove);
            }
        } else {
            /* product was added */
            $this->increasedQty = $this->removeProductFromStock($newQty, $item);
            $item->setQtyOrdered($newQty);
        }

        // @TODO WE must change the canceled qty against the qty ordered in case order item qty has been changed
        $rowWeight = $item->getWeight() * $newQty;
        $item->setRowWeight($rowWeight);
        try {
            $this->oeOrderItemRepository->save($item);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Error while updating product qty: %1', $e->getMessage())
            );

            throw $e;
        }

        if (!$item->getParentItemId()) {
            $this->logChanges(
                $this->logMessageFactory->create(
                    [
                        'message' => __(
                            'Qty has been changed from <b>%1</b> to <b>%2</b>',
                            round($originalQtyOrdered, 2),
                            round($newQty, 2)
                        ),
                        'level'   => 2,
                        'group'   => 'item_' . $item->getId()
                    ]
                ),
                'item_' . $item->getId()
            );
        }

        return (float)$newQty;
    }

    /**
     * Increase Product In Stock
     *
     * @param float $newQty
     * @param Item $item
     * @return float
     */
    protected function removeProductFromStock(float $newQty, Item $item): float
    {
        $qty = $newQty - (float)$item->getQtyOrdered();
        $qty = max($qty, 0);
        if ($item->getProductType() === 'configurable') {
            return $qty; // Remove from stock only child products
        }

        if ($qty != 0) {
            try {
                $this->stockManager->registerSale($item, $qty);
                $this->_eventManager->dispatch(
                    'mw_ordereditor_item_added',
                    [
                        'order_item' => $item,
                        'qty_to_add' => $qty
                    ]
                );
            } catch (\Exception $exception) {
                $this->_logger->error(__('Register sale error:'));
                $this->_logger->error($exception->getMessage());
                $this->_logger->error($exception->getTraceAsString());
            }
        }

        return (float)$qty;
    }

    /**
     * @param float $newQty
     * @param Item $item
     * @return float
     */
    protected function returnProductInStock(float $newQty, Item $item = null): float
    {
        if ($item === null) {
            $item = $this;
        }

        // $newQty already has cancelled and returned items inside
        $qty = $item->getQtyOrdered() - $newQty;

        if ($qty > 0 && $this->getAllowReturnToStock($item)) {
            try {
                $this->stockManager->registerReturn($item, $qty);
                $this->_eventManager->dispatch(
                    'mw_ordereditor_item_removed',
                    [
                        'order_item'    => $item,
                        'qty_to_remove' => $qty
                    ]
                );
            } catch (\Exception $exception) {
                $this->_logger->error(__('Register return error:'));
                $this->_logger->error($exception->getMessage());
                $this->_logger->error($exception->getTraceAsString());
            }
        }

        return (float)$qty;
    }

    /**
     * @return bool
     */
    protected function getAllowReturnToStock(Item $item): bool
    {
        if ($item->getProductType() === 'configurable') {
            return false;
        }

        return $this->isBackToStockEnabled();
    }

    /**
     * @return bool
     */
    protected function isBackToStockEnabled(): bool
    {
        return isset($this->newParams['back_to_stock']) ? (bool)$this->newParams['back_to_stock'] : false;
    }

    /**
     * Update Item Data
     *
     * @return void
     * @throws \Exception
     */
    protected function updateItemData()
    {
        $changes = [];

        // description
        if (isset($this->newParams['description'])) {
            $this->setDescription($this->newParams['description']);
        }

        // tax amount
        if (isset($this->newParams['tax_amount'])) {
            $taxAmount     = (float)$this->newParams['tax_amount'];
            $baseTaxAmount = $this->currencyConvertToBaseCurrency($taxAmount);
            $origTaxAmount = $this->getTaxAmount();

            $this->setBaseTaxAmount($baseTaxAmount)
                 ->setTaxAmount($taxAmount);

            if ($origTaxAmount != $taxAmount) {
                $changes[] = __(
                    'Tax Amount has been changed from <b>%1</b> to <b>%2</b>',
                    $this->getOrder()->formatPriceTxt($origTaxAmount),
                    $this->getOrder()->formatPriceTxt($taxAmount)
                );
            }
        }

        // discount tax compensation amount
        if (isset($this->newParams['discount_tax_compensation_amount'])) {
            $hiddenTax     = (float)$this->newParams['discount_tax_compensation_amount'];
            $baseHiddenTax = $this->currencyConvertToBaseCurrency($hiddenTax);

            $this->setBaseDiscountTaxCompensationAmount($baseHiddenTax)
                 ->setDiscountTaxCompensationAmount($hiddenTax);
        }

        // tax percent
        if (isset($this->newParams['tax_percent'])) {
            $origValue = $this->getTaxPercent();
            $this->setTaxPercent($this->newParams['tax_percent']);

            if ($origValue != $this->newParams['tax_percent']) {
                $changes[] = __(
                    'Tax Percent has been changed from <b>%1</b> to <b>%2</b>',
                    round($origValue, 2),
                    round($this->newParams['tax_percent'], 2)
                );
            }
        }

        // price
        if (isset($this->newParams['price'])) {
            $price     = (float)$this->newParams['price'];
            $basePrice = $this->currencyConvertToBaseCurrency($price);
            $origPrice = $this->getPrice();

            $this->setBasePrice($basePrice)
                 ->setPrice($price);

            if ($origPrice != $price) {
                $changes[] = __(
                    'Price has been changed from <b>%1</b> to <b>%2</b>',
                    $this->getOrder()->formatPriceTxt($origPrice),
                    $this->getOrder()->formatPriceTxt($price)
                );
            }
        }

        // Price includes tax
        if (isset($this->newParams['price_incl_tax'])) {
            $priceInclTax     = (float)$this->newParams['price_incl_tax'];
            $basePriceInclTax = $this->currencyConvertToBaseCurrency($priceInclTax);

            $this->setBasePriceInclTax($basePriceInclTax)
                 ->setPriceInclTax($priceInclTax);
        }

        // discount amount
        if (isset($this->newParams['discount_amount'])) {
            $discountAmount     = (float)$this->newParams['discount_amount'];
            $baseDiscountAmount = $this->currencyConvertToBaseCurrency($discountAmount);
            $origDiscountAmount = $this->getDiscountAmount();

            $this->setBaseDiscountAmount($baseDiscountAmount)
                 ->setDiscountAmount($discountAmount);

            if ($origDiscountAmount != $discountAmount) {
                $changes[] = __(
                    'Discount has been changed from <b>%1</b> to <b>%2</b>',
                    $this->getOrder()->formatPriceTxt($origDiscountAmount),
                    $this->getOrder()->formatPriceTxt($discountAmount)
                );
            }
        }

        // discount percent
        if (isset($this->newParams['discount_percent'])) {
            $this->setDiscountPercent($this->newParams['discount_percent']);
        }

        // subtotal (row total)
        if (isset($this->newParams['subtotal'])) {
            $currentSubtotal           = (float)$this->newParams['subtotal'];
            $baseCurrencySubtotal      = $this->currencyConvertToBaseCurrency($currentSubtotal);
            $roundBaseCurrencySubtotal = $this->helper->roundAndFormatPrice($baseCurrencySubtotal);

            $this->setBaseRowTotal($roundBaseCurrencySubtotal)
                 ->setRowTotal($currentSubtotal);
        }

        // Subtotal includes tax
        if (isset($this->newParams['subtotal_incl_tax'])) {
            $subtotalInclTax                  = (float)$this->newParams['subtotal_incl_tax'];
            $baseCurrencySubtotalInclTax      = $this->currencyConvertToBaseCurrency($subtotalInclTax);
            $roundBaseCurrencySubtotalInclTax = $this->helper->roundAndFormatPrice($baseCurrencySubtotalInclTax);

            $this->setBaseRowTotalInclTax($roundBaseCurrencySubtotalInclTax)
                 ->setRowTotalInclTax($subtotalInclTax);
        }

        try {
            $this->oeOrderItemRepository->save($this);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Error while updating item data: %1', $e->getMessage())
            );

            throw $e;
        }

        if (!empty($changes)) {
            $logMessages = [];
            foreach ($changes as $changeMessage) {
                $logMessages[] = $this->logMessageFactory->create(
                    ['message' => $changeMessage, 'level' => 2]
                );
            }

            $this->_eventManager->dispatch(
                'mageworx_log_changes_on_order_edit',
                [
                    ChangeLoggerInterface::MESSAGES_KEY => $logMessages,
                    ChangeLoggerInterface::GROUP_CODE   => 'item_' . $this->getId(),
                    ChangeLoggerInterface::TYPE_CODE    => ChangeLoggerInterface::TYPE_ITEM
                ]
            );
        }
    }

    /**
     * Convert store amount to base amount
     *
     * @param float $amount
     * @return float
     */
    protected function currencyConvertToBaseCurrency($amount): float
    {
        if ($this->_order->getBaseCurrencyCode() === $this->_order->getOrderCurrencyCode()) {
            return (float)$amount;
        }

        $rate       = (float)$this->_order->getBaseToOrderRate();
        $rate       = $rate > 0 ? $rate : 1;
        $baseAmount = $amount / $rate;

        return (float)$baseAmount;
    }

    /**
     * Getter decreasedQty
     *
     * @return float
     */
    public function getDecreasedQty()
    {
        return $this->decreasedQty;
    }

    /**
     * Getter increasedQty
     *
     * @return float
     */
    public function getIncreasedQty()
    {
        return $this->increasedQty;
    }

    /**
     * Get Order Item Row Total
     *
     * @param Item|null $item
     * @return float
     */
    protected function getOrderItemRowTotal(Item $item = null): float
    {
        if ($item === null) {
            $item = $this;
        }

        return $item->getRowTotal()
            + $item->getTaxAmount()
            + $item->getWeeeTaxAppliedRowAmount()
            - $item->getDiscountAmount();
    }

    /**
     * @return Item
     */
    protected function initLogger(): Item
    {
        $this->changeLogger->getGroup($this->getLogGroupId())
                           ->setType(ChangeLoggerInterface::TYPE_ITEM);

        return $this;
    }

    /**
     * @param LogMessageInterface $logMessage
     * @param string|null $groupId
     * @return Item
     */
    public function logChanges(LogMessageInterface $logMessage, string $groupId = null): Item
    {
        if (!$groupId) {
            $groupId = $this->getLogGroupId();
        }
        $this->changeLogger->addMessagesToLog([$logMessage], $groupId);

        return $this;
    }

    /**
     * @return string
     */
    public function getLogGroupId(): string
    {
        if ($this->getId()) {
            return 'item_' . $this->getId();
        }

        return 'new_items';
    }

    /**
     * Cancel order item.
     * Need to be overwritten because of incorrect qty_to_cancel calculations:
     * partially cancelled items must take part in whole sum of items cancelled.
     *
     * @return $this
     */
    public function cancel()
    {
        if ($this->getStatusId() !== self::STATUS_CANCELED) {
            if ($this->isBackToStockEnabled()) {
                /**
                 * Next event return items to stock in the
                 * \Magento\InventorySales\Observer\CatalogInventory\CancelOrderItemObserver
                 */
                $this->_eventManager->dispatch('sales_order_item_cancel', ['item' => $this]);
            }
            $this->setQtyCanceled($this->getQtyToCancel() + $this->getQtyCanceled());
            $this->setTaxCanceled(
                $this->getTaxCanceled() + $this->getBaseTaxAmount() * $this->getQtyCanceled() / $this->getQtyOrdered()
            );
            $this->setDiscountTaxCompensationCanceled(
                $this->getDiscountTaxCompensationCanceled() +
                $this->getDiscountTaxCompensationAmount() * $this->getQtyCanceled() / $this->getQtyOrdered()
            );
        }

        return $this;
    }
}
