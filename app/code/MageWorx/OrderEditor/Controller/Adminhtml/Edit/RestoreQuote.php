<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Controller\Adminhtml\Edit;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json as ResultJson;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\DataObjectFactory;

class RestoreQuote extends Action
{
    const ADMIN_RESOURCE = 'MageWorx_OrderEditor::edit_order';

    /**
     * @var \MageWorx\OrderEditor\Api\RestoreQuoteInterface
     */
    private $restoreQuote;

    /**
     * @var \MageWorx\OrderEditor\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var \MageWorx\OrderEditor\Api\QuoteRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * RestoreQuote constructor.
     *
     * @param Context $context
     * @param \MageWorx\OrderEditor\Api\RestoreQuoteInterface $restoreQuote
     * @param \MageWorx\OrderEditor\Api\OrderRepositoryInterface $orderRepository
     * @param \MageWorx\OrderEditor\Api\QuoteRepositoryInterface $quoteRepository
     */
    public function __construct(
        Context $context,
        \MageWorx\OrderEditor\Api\RestoreQuoteInterface $restoreQuote,
        \MageWorx\OrderEditor\Api\OrderRepositoryInterface $orderRepository,
        \MageWorx\OrderEditor\Api\QuoteRepositoryInterface $quoteRepository,
        DataObjectFactory $dataObjectFactory
    ) {
        parent::__construct($context);
        $this->restoreQuote      = $restoreQuote;
        $this->orderRepository   = $orderRepository;
        $this->quoteRepository   = $quoteRepository;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');
        if (!$orderId) {
            throw new InputException(__('Order id is empty'));
        }

        $order = $this->orderRepository->getById($orderId);
        $quote = $this->quoteRepository->getById((int)$order->getQuoteId());

        $this->restoreQuote->restore($quote);

        $message = __(
            'Quote %1 for the order %2 has been restored from backup successfully.',
            $quote->getId(),
            $orderId
        );
        $updateResult = $this->dataObjectFactory->create(['data' => ['order_id' => $orderId, 'success' => true, 'message' => $message]]);
        /** @var ResultJson $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        return $resultJson->setData($updateResult);
    }
}
