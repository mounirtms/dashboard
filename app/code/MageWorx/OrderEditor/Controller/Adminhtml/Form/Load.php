<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types = 1);

namespace MageWorx\OrderEditor\Controller\Adminhtml\Form;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;
use MageWorx\OrderEditor\Api\OrderRepositoryInterface;
use MageWorx\OrderEditor\Api\QuoteDataBackupRepositoryInterface;
use MageWorx\OrderEditor\Api\QuoteRepositoryInterface;
use MageWorx\OrderEditor\Api\RestoreQuoteInterface;
use MageWorx\OrderEditor\Model\Address;
use MageWorx\OrderEditor\Model\InventoryDetectionStatusManager;
use MageWorx\OrderEditor\Model\MsiStatusManager;
use MageWorx\OrderEditor\Model\Order;
use MageWorx\OrderEditor\Model\Quote;

/**
 * Class Load
 */
class Load extends Action
{
    /**
     * Page factory
     *
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $pageFactory;

    /**
     * Raw factory
     *
     * @var \Magento\Framework\Controller\Result\RawFactory
     */
    protected $rawFactory;

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $coreRegistry;

    /**
     * Order Editor helper
     *
     * @var \MageWorx\OrderEditor\Helper\Data
     */
    protected $helperData;

    /**
     * @var string
     */
    protected $blockId;

    /**
     * @var Order
     */
    protected $order;

    /**
     * @var Quote
     */
    protected $quote;

    /**
     * @var Address
     */
    protected $address;

    /**
     * @var \MageWorx\OrderEditor\Block\Adminhtml\Sales\Order\Edit\Form\Payment\Method
     */
    protected $method;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var QuoteRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var InventoryDetectionStatusManager
     */
    protected $inventoryDetectionStatusManager;

    /**
     * @var MsiStatusManager
     */
    private $msiStatusManager;

    /**
     * @var SerializerJson
     */
    protected $serializer;

    /**
     * @var RestoreQuoteInterface
     */
    protected $backupQuote;

    /**
     * @var QuoteDataBackupRepositoryInterface
     */
    protected $quoteBackupRepository;

    /**
     * Load constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     * @param \Magento\Framework\Controller\Result\RawFactory $rawFactory
     * @param \MageWorx\OrderEditor\Helper\Data $helperData
     * @param \Magento\Framework\Registry $registry
     * @param \MageWorx\OrderEditor\Block\Adminhtml\Sales\Order\Edit\Form\Payment\Method $method
     * @param OrderRepositoryInterface $orderRepository
     * @param QuoteRepositoryInterface $quoteRepository
     * @param Address $address
     * @param InventoryDetectionStatusManager $inventoryDetectionStatusManager
     * @param MsiStatusManager $msiStatusManager
     * @param SerializerJson $serializer
     * @param RestoreQuoteInterface $backupQuote
     * @param QuoteDataBackupRepositoryInterface $quoteBackupRepository
     */
    public function __construct(
        \Magento\Framework\App\Action\Context                                      $context,
        \Magento\Framework\View\Result\PageFactory                                 $pageFactory,
        \Magento\Framework\Controller\Result\RawFactory                            $rawFactory,
        \MageWorx\OrderEditor\Helper\Data                                          $helperData,
        \Magento\Framework\Registry                                                $registry,
        \MageWorx\OrderEditor\Block\Adminhtml\Sales\Order\Edit\Form\Payment\Method $method,
        OrderRepositoryInterface                                                   $orderRepository,
        QuoteRepositoryInterface                                                   $quoteRepository,
        Address                                                                    $address,
        InventoryDetectionStatusManager                                            $inventoryDetectionStatusManager,
        MsiStatusManager                                                           $msiStatusManager,
        SerializerJson                                                             $serializer,
        RestoreQuoteInterface                                                      $backupQuote,
        QuoteDataBackupRepositoryInterface                                         $quoteBackupRepository
    ) {
        $this->rawFactory      = $rawFactory;
        $this->pageFactory     = $pageFactory;
        $this->helperData      = $helperData;
        $this->coreRegistry    = $registry;
        $this->orderRepository = $orderRepository;
        $this->quoteRepository = $quoteRepository;
        $this->address         = $address;
        $this->method          = $method;

        $this->inventoryDetectionStatusManager = $inventoryDetectionStatusManager;
        $this->msiStatusManager                = $msiStatusManager;

        $this->serializer            = $serializer;
        $this->backupQuote           = $backupQuote;
        $this->quoteBackupRepository = $quoteBackupRepository;

        return parent::__construct($context);
    }

    /**
     * Render block form
     *
     * @return ResultInterface
     * @throws \Exception
     */
    public function execute(): ResultInterface
    {
        try {
            $this->msiStatusManager->disableMSI();
            $this->inventoryDetectionStatusManager->disableInventoryDetection();
            $response = [
                'result' => $this->getResultHtml(),
                'status' => true
            ];
            $this->inventoryDetectionStatusManager->enableInventoryDetection();
        } catch (\Exception $e) {
            $response = [
                'result' => $e->getMessage() . ' ' . $e->getTraceAsString(),
                'error'  => $e->getMessage(),
                'status' => false
            ];
        } finally {
            $this->msiStatusManager->enableMSI();
        }

        if ($this->getRequest()->getParam('raw')) {
            $result = $this->rawFactory->create()->setContents($response['result']);
        } else {
            $result = $this->rawFactory->create()->setContents($this->serializer->serialize($response));
        }

        return $result;
    }

    /**
     * @return string
     */
    protected function getResultHtml(): string
    {
        $this->blockId = $this->getRequest()->getParam('block_id');

        $this->registerOrder();
        $this->registerQuote();
        $this->registerAddress();

        if ($this->blockId === 'payment_method') {
            $this->method->setPaymentMethod();
        }

        $resultPage = $this->pageFactory->create();
        $resultPage->addHandle('ordereditor_load_block_' . $this->blockId);

        return $resultPage->getLayout()->renderElement('content');
    }

    /**
     * Register order
     *
     * @return void
     * @throws LocalizedException
     */
    private function registerOrder(): void
    {
        $orderId     = (int)$this->getRequest()->getParam('order_id');
        $this->order = $this->orderRepository->getById($orderId);
        $this->helperData->setOrder($this->order);
    }

    /**
     * Register quote
     *
     * @return void
     * @throws \Exception
     */
    private function registerQuote(): void
    {
        if ($this->blockId == 'shipping_method'
            || $this->blockId == 'payment_method'
            || $this->blockId == 'order_items'
        ) {
            $quoteId = (int)$this->order->getQuoteId();
            try {
                $this->quote = $this->quoteRepository->getById($quoteId);
                $this->quote->setOrigOrderId((int)$this->order->getId());
                $this->helperData->setQuote($this->quote);
            } catch (NoSuchEntityException $exception) {
                $this->messageManager->addErrorMessage(__('Required quote with id %1 does not exist.', $quoteId));
                try {
                    $this->quote = $this->helperData->getQuote();
                    $this->helperData->setQuote($this->quote);
                } catch (LocalizedException $exception) {
                    $this->messageManager->addErrorMessage(__('Unable to recreate quote.', $quoteId));
                }
            }

            if ($this->quote) {
                $this->initBackendSession();
                if ($this->blockId == 'order_items') {
                    try {
                        $this->createQuoteBackup();
                    } catch (LocalizedException $localizedException) {
                        $this->messageManager->addNoticeMessage(
                            __('Unable to backup quote: %1', $localizedException->getMessage())
                        );
                    }
                }
            }
        }
    }

    /**
     * @throws LocalizedException
     */
    private function createQuoteBackup(): void
    {
        if (!$this->quote) {
            return; // Do not backup non existing quote
        }

        try {
            $existingBackup = $this->quoteBackupRepository->getByQuoteId((int)$this->quote->getId());
            $backupExist    = (bool)$existingBackup->getId();
        } catch (NoSuchEntityException $noSuchEntityException) {
            $backupExist = false;
        }

        if (!$backupExist) {
            $this->backupQuote->backupInitialQuoteState($this->quote);
        }
    }

    /**
     * Register order address
     *
     * @return void
     * @throws LocalizedException
     */
    private function registerAddress(): void
    {
        $addressId = 0;

        if ($this->blockId == 'billing_address') {
            $addressId = (int)$this->order->getBillingAddressId();
        } elseif ($this->blockId == 'shipping_address') {
            $addressId = (int)$this->order->getShippingAddressId();
        }

        if (!$addressId) {
            return;
        }

        $address = $this->address->loadAddress($addressId);
        if ($address->getId()) {
            $this->coreRegistry->register('order_address', $address);
        } else {
            throw new LocalizedException(__('Can not load address'));
        }
    }

    /**
     * Initializes the backend customer session. This is crucial for configuring quote items as it accurately maintains
     * the correct store ID and quote ID. This session setup ensures that operations on the quote are executed with the
     * appropriate context regarding the store, customer, and order details.
     * It is imperative to clear the session after completing all quote-related operations to prevent unintended data
     * persistence.
     *
     * @return void
     */
    protected function initBackendSession(): void
    {
        $session = ObjectManager::getInstance()->get(\Magento\Backend\Model\Session\Quote::class);
        $session->clearStorage();
        $session->setQuoteId($this->quote->getId());
        $session->setOrderId($this->order->getId());
        $session->setStoreId($this->order->getStoreId());
        $session->setCustomerId($this->order->getCustomerId());
        $session->setCurrencyId($this->order->getOrderCurrencyCode());
    }
}
