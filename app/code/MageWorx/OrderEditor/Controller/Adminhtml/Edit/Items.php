<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Controller\Adminhtml\Edit;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;
use Magento\Framework\View\Result\PageFactory;
use MageWorx\OrderEditor\Api\OrderRepositoryInterface;
use MageWorx\OrderEditor\Api\QuoteDataBackupRepositoryInterface;
use MageWorx\OrderEditor\Api\QuoteRepositoryInterface;
use MageWorx\OrderEditor\Controller\Adminhtml\AbstractAction;
use MageWorx\OrderEditor\Helper\Data;
use MageWorx\OrderEditor\Model\InventoryDetectionStatusManager;
use MageWorx\OrderEditor\Model\MsiStatusManager;
use MageWorx\OrderEditor\Model\Payment as PaymentModel;
use MageWorx\OrderEditor\Model\Shipping as ShippingModel;

/**
 * Class Items
 */
class Items extends AbstractAction
{
    const ADMIN_RESOURCE = 'MageWorx_OrderEditor::edit_items';

    /**
     * @var QuoteDataBackupRepositoryInterface
     */
    private $backupRepository;

    /**
     * Items constructor.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param RawFactory $resultRawFactory
     * @param Data $helper
     * @param ScopeConfigInterface $scopeConfig
     * @param QuoteRepositoryInterface $quoteRepository
     * @param ShippingModel $shipping
     * @param PaymentModel $payment
     * @param OrderRepositoryInterface $orderRepository
     * @param MsiStatusManager $msiStatusManager
     * @param InventoryDetectionStatusManager $inventoryDetectionStatusManager
     * @param SerializerJson $serializer
     * @param QuoteDataBackupRepositoryInterface $backupRepository
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        RawFactory $resultRawFactory,
        Data $helper,
        ScopeConfigInterface $scopeConfig,
        QuoteRepositoryInterface $quoteRepository,
        ShippingModel $shipping,
        PaymentModel $payment,
        OrderRepositoryInterface $orderRepository,
        MsiStatusManager $msiStatusManager,
        InventoryDetectionStatusManager $inventoryDetectionStatusManager,
        SerializerJson $serializer,
        QuoteDataBackupRepositoryInterface $backupRepository
    ) {
        parent::__construct(
            $context,
            $resultPageFactory,
            $resultRawFactory,
            $helper,
            $scopeConfig,
            $quoteRepository,
            $shipping,
            $payment,
            $orderRepository,
            $msiStatusManager,
            $inventoryDetectionStatusManager,
            $serializer
        );
        $this->backupRepository = $backupRepository;
    }

    /**
     * @return void
     */
    protected function update(int $orderId = null, array $params = []): void
    {
        if (!$orderId) {
            $orderId = $this->getRequest()->getParam('order_id');
        }

        if (empty($params)) {
            $params = $this->getRequest()->getParams();
        }

        $order = $this->orderRepository->getById($orderId);
        $this->updateOrderItems($order, $params);

        try {
            $quoteBackup = $this->backupRepository->getByQuoteId($order->getQuoteId());
            $this->backupRepository->delete($quoteBackup);
        } catch (NoSuchEntityException $e) {
            return;
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
    }

    /**
     * @return void
     */
    protected function updateOrderItems(\Magento\Sales\Model\Order $order, array $params): void
    {
        $order->editItems($params);
    }

    /**
     * @return string
     */
    protected function prepareResponse(): string
    {
        return static::ACTION_RELOAD_PAGE;
    }
}
