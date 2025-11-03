<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;
use MageWorx\OrderEditor\Api\QuoteDataBackupRepositoryInterface;
use MageWorx\OrderEditor\Api\QuoteItemRepositoryInterface;
use MageWorx\OrderEditor\Api\QuoteRepositoryInterface;
use MageWorx\OrderEditor\Api\RestoreQuoteInterface;
use MageWorx\OrderEditor\Model\Quote\ItemFactory as QuoteItemFactory;

class RestoreQuote implements RestoreQuoteInterface
{
    /**
     * @var QuoteDataBackupRepositoryInterface
     */
    protected $quoteDataBackupRepository;

    /**
     * @var QuoteRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var SerializerJson
     */
    protected $serializer;

    /**
     * @var QuoteItemFactory
     */
    protected $quoteItemFactory;

    /**
     * @var QuoteItemRepositoryInterface
     */
    protected $quoteItemRepository;

    /**
     * RestoreQuote constructor.
     *
     * @param QuoteDataBackupRepositoryInterface $quoteDataBackupRepository
     * @param QuoteRepositoryInterface $quoteRepository
     * @param Quote\ItemFactory $quoteItemFactory
     * @param QuoteItemRepositoryInterface $quoteItemRepository
     * @param SerializerJson $serializer
     */
    public function __construct(
        QuoteDataBackupRepositoryInterface $quoteDataBackupRepository,
        QuoteRepositoryInterface $quoteRepository,
        QuoteItemFactory $quoteItemFactory,
        QuoteItemRepositoryInterface $quoteItemRepository,
        SerializerJson $serializer
    ) {
        $this->quoteDataBackupRepository = $quoteDataBackupRepository;
        $this->quoteRepository           = $quoteRepository;
        $this->quoteItemFactory          = $quoteItemFactory;
        $this->quoteItemRepository       = $quoteItemRepository;
        $this->serializer                = $serializer;
    }

    /**
     * Restores the quote to a previous state
     *
     * @param \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote $quote
     * @throws LocalizedException
     */
    public function restore(\Magento\Quote\Api\Data\CartInterface $quote): void
    {
        $quoteId = $quote->getId();
        if (!$quoteId) {
            throw new LocalizedException(__('Unable to restore quote: empty quote id'));
        }

        /** @var \MageWorx\OrderEditor\Model\Quote $quote */
        $quote = $this->quoteRepository->getById($quoteId);
        /** @var \MageWorx\OrderEditor\Api\Data\QuoteDataBackupInterface $backupInstance */
        $backupInstance      = $this->quoteDataBackupRepository->getByQuoteId($quoteId);
        $quoteDataSerialized = $backupInstance->getDataSerialized();
        $quoteBackupData     = $this->serializer->unserialize($quoteDataSerialized);
        if (empty($quoteBackupData)) {
            throw new LocalizedException(__('Quote data is empty for quote %1', $quoteId));
        }

        $quoteData = $quoteBackupData['quote'];
        $quote->setData($quoteData);

        if (!empty($quoteBackupData['quote_items'])) {
            $itemsFromBackup = $quoteBackupData['quote_items'];
            /** @var \Magento\Quote\Api\Data\CartItemInterface[]|\Magento\Quote\Model\Quote\Item[] $existingQuoteItems */
            $existingQuoteItems = $quote->getAllItems();
            foreach ($existingQuoteItems as $existingItem) {
                $existingItemId = $existingItem->getId();
                if (empty($itemsFromBackup[$existingItemId])) {
                    $this->quoteItemRepository->deleteById($existingItemId);
                } else {
                    $itemFromBackup = $itemsFromBackup[$existingItemId];
                    $itemFromBackup['product'] = null;
                    $existingItem->setData($itemFromBackup);
                    $this->quoteItemRepository->save($existingItem);
                }

                // Clear queue: only new items left
                unset($itemsFromBackup[$existingItemId]);
            }
            foreach ($itemsFromBackup as $itemId => $newItem) {
                /** @var \Magento\Quote\Api\Data\CartItemInterface|\Magento\Quote\Model\Quote\Item $restoredQuoteItem */
                $restoredQuoteItem = $this->quoteItemFactory->create();
                $newItem['product'] = null;
                $restoredQuoteItem->addData($newItem);
                $restoredQuoteItem->setQuote($quote);
                $this->quoteItemRepository->save($restoredQuoteItem);
            }
        }

        // Reload items cache in quote
        $quoteItemsNew = $quote->getItemsCollection(false);
        $this->quoteRepository->save($quote);
        $this->quoteDataBackupRepository->delete($backupInstance);
    }

    /**
     * Start order editing from this method:
     * - backup initial quote state in additional table
     * - set up the "edit" flags in original tables
     *
     * When used before editing allow to restore the quote and corresponding entities from backup on any stage.
     *
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @throws LocalizedException
     */
    public function backupInitialQuoteState(\Magento\Quote\Api\Data\CartInterface $quote): void
    {
        $quoteId = (int)$quote->getId();
        $orderId = (int)$quote->getOrigOrderId();
        if (!$quoteId) {
            throw new LocalizedException(__('Unable to backup quote: empty quote id'));
        }

        /** @var \Magento\Quote\Model\Quote $quote */
        $data['quote'] = $quote->getData();
        /** @var \Magento\Quote\Model\Quote\Item[] $quoteItems */
        $quoteItems = $quote->getAllItems();
        foreach ($quoteItems as $quoteItem) {
            $data['quote_items'][$quoteItem->getId()] = $quoteItem->getData();
        }
        $jsonData = $this->serializer->serialize($data);

        /** @var \MageWorx\OrderEditor\Api\Data\QuoteDataBackupInterface $backupInstance */
        try {
            $backupInstance = $this->quoteDataBackupRepository->getByQuoteId($quoteId);
        } catch (NoSuchEntityException $exception) {
            $backupInstance = $this->quoteDataBackupRepository->getEmptyEntity();
        }

        $backupInstance->setQuoteId($quoteId);
        $backupInstance->setOrderId($orderId);
        $backupInstance->setDataSerialized($jsonData);

        $this->quoteDataBackupRepository->save($backupInstance);
    }
}
