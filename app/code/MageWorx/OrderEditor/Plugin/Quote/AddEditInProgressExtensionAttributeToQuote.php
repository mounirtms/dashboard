<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Plugin\Quote;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Class AddEditInProgressExtensionAttributeToQuote
 *
 * Add extension attribute "edit_in_progress" to the Quote Model
 */
class AddEditInProgressExtensionAttributeToQuote
{
    /**
     * @var \MageWorx\OrderEditor\Api\QuoteDataBackupRepositoryInterface
     */
    private $quoteDataBackupRepository;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Magento\Quote\Api\Data\CartExtensionInterfaceFactory
     */
    private $cartExtensionFactory;

    /**
     * AddEditInProgressExtensionAttributeToQuote constructor.
     *
     * @param \MageWorx\OrderEditor\Api\QuoteDataBackupRepositoryInterface $quoteDataBackupRepository
     * @param \Magento\Quote\Api\Data\CartExtensionInterfaceFactory $cartExtensionFactory
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \MageWorx\OrderEditor\Api\QuoteDataBackupRepositoryInterface $quoteDataBackupRepository,
        \Magento\Quote\Api\Data\CartExtensionInterfaceFactory $cartExtensionFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->quoteDataBackupRepository = $quoteDataBackupRepository;
        $this->cartExtensionFactory      = $cartExtensionFactory;
        $this->logger                    = $logger;
    }

    /**
     * Add extension attribute "edit_in_progress" to the Quote
     *
     * @param CartRepositoryInterface $subject
     * @param CartInterface $quote
     * @return CartInterface
     */
    public function afterGet(
        CartRepositoryInterface $subject,
        CartInterface $quote
    ): CartInterface {
        try {
            $isEditInProgress = (bool)$this->quoteDataBackupRepository->getByQuoteId($quote->getId())->getOrderId();
        } catch (NoSuchEntityException $e) {
            $isEditInProgress = false;
        } catch (LocalizedException $e) {
            $this->logger->critical($e->getLogMessage());

            return $quote;
        }

        $extensionAttributes = $quote->getExtensionAttributes();
        $extensionAttributes = $extensionAttributes ? $extensionAttributes : $this->cartExtensionFactory->create();
        $extensionAttributes->setEditInProgress($isEditInProgress);

        $quote->setExtensionAttributes($extensionAttributes);

        return $quote;
    }
}
