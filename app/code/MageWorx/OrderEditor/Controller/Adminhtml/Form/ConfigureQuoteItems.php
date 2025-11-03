<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Controller\Adminhtml\Form;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Catalog\Helper\Product\Composite as CompositeProductHelper;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Layout;
use Magento\Framework\View\Result\PageFactory;
use MageWorx\OrderEditor\Api\QuoteItemRepositoryInterface as CartItemRepositoryInterface;
use MageWorx\OrderEditor\Api\QuoteRepositoryInterface as CartRepositoryInterface;
use Magento\Quote\Model\ResourceModel\Quote\Item\Option\CollectionFactory as QuoteItemOptionCollectionFactory;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use MageWorx\OrderEditor\Api\OrderRepositoryInterface;
use MageWorx\OrderEditor\Helper\Data;
use MageWorx\OrderEditor\Model\Quote\Item;
use Magento\Sales\Controller\Adminhtml\Order\Create;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote\Item\CartItemOptionsProcessor;

/**
 * Class ConfigureQuoteItems
 */
class ConfigureQuoteItems extends Create
{
    /**
     * @var CompositeProductHelper
     */
    protected $productCompositeHelper;

    /**
     * @var OrderItemRepositoryInterface
     */
    protected $orderItemRepository;

    /**
     * @var CartItemRepositoryInterface
     */
    protected $cartItemRepository;

    /**
     * @var DataObjectFactory
     */
    protected $dataObjectFactory;

    /**
     * @var QuoteItemOptionCollectionFactory
     */
    protected $quoteItemOptionCollectionFactory;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var CartItemOptionsProcessor
     */
    protected $cartItemOptionsProcessor;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var \MageWorx\OrderEditor\Model\Edit\Quote
     */
    protected $editQuoteModel;

    /**
     * ConfigureQuoteItems constructor.
     *
     * @param Action\Context $context
     * @param ProductHelper $productHelper
     * @param Escaper $escaper
     * @param PageFactory $resultPageFactory
     * @param ForwardFactory $resultForwardFactory
     * @param CompositeProductHelper $productCompositeHelper
     * @param OrderItemRepositoryInterface $orderItemRepository
     * @param CartItemRepositoryInterface $cartItemRepository
     * @param DataObjectFactory $dataObjectFactory
     * @param QuoteItemOptionCollectionFactory $quoteItemOptionCollectionFactory
     * @param CartRepositoryInterface $quoteRepository
     * @param CartItemOptionsProcessor $cartItemOptionsProcessor
     * @param OrderRepositoryInterface $orderRepository
     * @param Data $helper
     */
    public function __construct(
        Action\Context $context,
        ProductHelper $productHelper,
        Escaper $escaper,
        PageFactory $resultPageFactory,
        ForwardFactory $resultForwardFactory,
        CompositeProductHelper $productCompositeHelper,
        OrderItemRepositoryInterface $orderItemRepository,
        CartItemRepositoryInterface $cartItemRepository,
        DataObjectFactory $dataObjectFactory,
        QuoteItemOptionCollectionFactory $quoteItemOptionCollectionFactory,
        CartRepositoryInterface $quoteRepository,
        CartItemOptionsProcessor $cartItemOptionsProcessor,
        OrderRepositoryInterface $orderRepository,
        Data $helper,
        \MageWorx\OrderEditor\Model\Edit\Quote $editQuoteModel
    ) {
        $this->productCompositeHelper           = $productCompositeHelper;
        $this->orderItemRepository              = $orderItemRepository;
        $this->cartItemRepository               = $cartItemRepository;
        $this->dataObjectFactory                = $dataObjectFactory;
        $this->quoteItemOptionCollectionFactory = $quoteItemOptionCollectionFactory;
        $this->quoteRepository                  = $quoteRepository;
        $this->cartItemOptionsProcessor         = $cartItemOptionsProcessor;
        $this->orderRepository                  = $orderRepository;
        $this->helper                           = $helper;
        $this->editQuoteModel                   = $editQuoteModel;
        parent::__construct(
            $context,
            $productHelper,
            $escaper,
            $resultPageFactory,
            $resultForwardFactory
        );
    }

    /**
     * @return Layout
     */
    public function execute(): Layout
    {
        // Prepare data
        $configureResult = $this->dataObjectFactory->create();
        try {
            $quoteItem = $this->getQuoteItem();

            $configureResult->setOk(true);

            $buyRequest = $this->getUpdatedBuyRequestFromItem($quoteItem);
            $configureResult->setBuyRequest($buyRequest);
            $configureResult->setCurrentStoreId($quoteItem->getStoreId());
            $configureResult->setProductId($quoteItem->getProductId());
        } catch (\Exception $e) {
            $configureResult->setError(true);
            $configureResult->setMessage($e->getMessage());
        }

        return $this->productCompositeHelper
            ->renderConfigureResult($configureResult);
    }

    /**
     * @return CartItemInterface
     * @throws LocalizedException
     */
    protected function getQuoteItem(): CartItemInterface
    {
        $orderItemId = $this->getRequest()->getParam('id');
        if (!$orderItemId) {
            throw new LocalizedException(__('Order item id is not received.'));
        }

        $prefixIdLength = strlen(Item::PREFIX_ID);
        if (substr($orderItemId, 0, $prefixIdLength) == Item::PREFIX_ID) {
            $quoteItemId = substr(
                $orderItemId,
                $prefixIdLength,
                strlen($orderItemId)
            );
            $orderId     = $this->getRequest()->getParam('order_id');
            /** @var \MageWorx\OrderEditor\Model\Order $order */
            $order   = $this->orderRepository->getById($orderId);
            $quoteId = $order->getQuoteId();
        } else {
            $orderItem   = $this->loadOrderItem($orderItemId);
            $quoteId     = (int)$orderItem->getOrder()->getQuoteId();
            $quoteItemId = (int)$orderItem->getQuoteItemId();
        }

        return $this->loadQuoteItem($quoteId, $quoteItemId);
    }

    /**
     * @param int $quoteId
     * @param int $quoteItemId
     * @return CartItemInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     */
    protected function loadQuoteItem(
        int $quoteId,
        int $quoteItemId
    ): CartItemInterface {
        /**
         * Need to prevent errors during loading quote items for old quote
         *
         * @see \Magento\Quote\Model\QuoteRepository::getActive()
         */
        $quote       = $this->quoteRepository->getById($quoteId);
        $orderItemId = $this->getRequest()->getParam('id');
        if (!$orderItemId) {
            throw new LocalizedException(__('Order item id is not received.'));
        }
        /** @var \Magento\Sales\Model\Order\Item $orderItem */
        $orderItem = $this->loadOrderItem($orderItemId);

        // Restore quote item if needed
        if (!$quote->getItemById($quoteItemId)) {
            $quoteItemRestored = $this->helper->initFromOrderItem($orderItem, $quote);
            if ($quoteItemRestored) {
                if (!$quoteItemRestored->getId()) {
                    $quoteItemRestored->setQuote($quote)
                                      ->setQuoteId($quote->getId());
                    $this->cartItemRepository->save($quoteItemRestored);
                }
                $orderItem->setQuoteItemId($quoteItemRestored->getId());
                try {
                    $this->orderItemRepository->save($orderItem);
                } catch (LocalizedException $localizedException) {
                    // Unable to update order item, do nothing
                }
            } else {
                throw new NoSuchEntityException(__('Unable to restore quote item.'));
            }
        }

        $quoteItem = $this->editQuoteModel->convertOrderItemToQuoteItem(
            $orderItem,
            $orderItem->getBuyRequest()->convertToArray(),
            true
        );

        return $quoteItem;
    }

    /**
     * @param int $orderItemId
     * @return OrderItemInterface|\Magento\Sales\Model\Order\Item
     * @throws NoSuchEntityException
     */
    protected function loadOrderItem(int $orderItemId): OrderItemInterface
    {
        /** @var \Magento\Sales\Model\Order\Item $orderItem */
        $orderItem = $this->orderItemRepository->get($orderItemId);

        if (!$orderItem->getId()) {
            throw new NoSuchEntityException(__('Order item is not loaded.'));
        }

        return $orderItem;
    }

    /**
     * @param Item $item
     * @return \Magento\Framework\DataObject
     */
    protected function getUpdatedBuyRequestFromItem(\MageWorx\OrderEditor\Model\Quote\Item $item): DataObject
    {
        $buyRequest = $item->getBuyRequest();
        $options    = $buyRequest->getOptions();
        if (empty($options)) {
            // No custom options
            return $buyRequest;
        }

        $optionsByCode = $item->getOptionsByCode();
        foreach ($options as $optionId => $value) {
            if (!isset($optionsByCode['option_' . $optionId])) {
                continue;
            }
            /** @var \Magento\Quote\Model\Quote\Item\Option $option */
            $option         = $optionsByCode['option_' . $optionId];
            $optionInstance = $item->getProduct()->getOptionById($optionId);
            if (in_array($optionInstance->getType(), ['checkbox', 'multiselect']) && !is_array($value)) {
                $value = explode(',', $value);
            }
            $options[$optionId] = $value;
            $option->setValue($value);
        }

        $buyRequest->setData('options', $options);

        return $buyRequest;
    }
}
