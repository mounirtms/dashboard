<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Controller\Adminhtml\Edit;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Result\PageFactory;
use MageWorx\OrderEditor\Api\ChangeLoggerInterface;
use MageWorx\OrderEditor\Api\OrderRepositoryInterface;
use MageWorx\OrderEditor\Api\QuoteRepositoryInterface;
use MageWorx\OrderEditor\Controller\Adminhtml\AbstractAction;
use MageWorx\OrderEditor\Helper\Data;
use MageWorx\OrderEditor\Model\InventoryDetectionStatusManager;
use MageWorx\OrderEditor\Model\MsiStatusManager;
use MageWorx\OrderEditor\Model\Payment as PaymentModel;
use MageWorx\OrderEditor\Model\Shipping as ShippingModel;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;
use MageWorx\OrderEditor\Api\Data\LogMessageInterfaceFactory;
use Magento\Framework\Locale\ResolverInterface;

/**
 * Class Info
 */
class Info extends AbstractAction
{
    const ADMIN_RESOURCE = 'MageWorx_OrderEditor::edit_info';

    /**
     * @var TimezoneInterface
     */
    protected $localeDate;

    /**
     * @var LogMessageInterfaceFactory
     */
    protected $logMessageFactory;

    /**
     * @var ResolverInterface
     */
    protected $localeResolver;

    /**
     * Info constructor.
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
     * @param TimezoneInterface $timezone
     * @param ResolverInterface $localeResolver
     * @param LogMessageInterfaceFactory $logMessageFactory
     */
    public function __construct(
        Context                                            $context,
        PageFactory                                        $resultPageFactory,
        RawFactory                                         $resultRawFactory,
        Data                                               $helper,
        ScopeConfigInterface                               $scopeConfig,
        QuoteRepositoryInterface                           $quoteRepository,
        ShippingModel                                      $shipping,
        PaymentModel                                       $payment,
        OrderRepositoryInterface                           $orderRepository,
        MsiStatusManager                                   $msiStatusManager,
        InventoryDetectionStatusManager                    $inventoryDetectionStatusManager,
        SerializerJson                                     $serializer,
        TimezoneInterface                                  $timezone,
        ResolverInterface                                  $localeResolver,
        LogMessageInterfaceFactory                         $logMessageFactory
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
        $this->localeDate        = $timezone;
        $this->localeResolver    = $localeResolver;
        $this->logMessageFactory = $logMessageFactory;
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     * @throws \Exception
     */
    protected function update(): void
    {
        $order     = $this->loadOrder();
        $params    = $this->getRequest()->getParams();
        $infoData  = !empty($params['order']['info']) ? $params['order']['info'] : [];
        $changeLog = [];
        if (isset($infoData['created_at'])) {
            $locale    = $this->localeResolver->getLocale();
            $formatter = new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::SHORT,
                \IntlDateFormatter::MEDIUM,
                $this->localeDate->getConfigTimezone()
            );
            $res       = $formatter->parse($infoData['created_at']);
            $newDate   = $this->localeDate->date($res, $locale, false);

            // Save data
            $infoData['created_at'] = $newDate;
            $changeLog[]            = $this->logMessageFactory->create(
                [
                    'message' => __(
                        'Order Date has been changed from %1 to %2',
                        $formatter->format($this->localeDate->date($order->getCreatedAt())),
                        $formatter->format($newDate)
                    ),
                    'level'   => 0
                ]
            );
        }

        if ($infoData['status'] != $order->getStatus()) {
            $changeLog[] = $this->logMessageFactory->create(
                [
                    'message' => __(
                        'Order Status has been changed from %1 to %2',
                        ucwords($order->getStatus()),
                        ucwords($infoData['status'])
                    ),
                    'level'   => 0
                ]
            );
        }

        if ($infoData['state'] != $order->getState()) {
            $changeLog[] = $this->logMessageFactory->create(
                [
                    'message' => __(
                        'Order State has been changed from %1 to %2',
                        ucwords($order->getState()),
                        ucwords($infoData['state'])
                    ),
                    'level'   => 0
                ]
            );
        }

        $order->addData($infoData);
        try {
            $this->orderRepository->save($order);
            $this->_eventManager->dispatch(
                'mageworx_log_changes_on_order_edit',
                [
                    ChangeLoggerInterface::MESSAGES_KEY => $changeLog
                ]
            );
            $this->_eventManager->dispatch(
                'mageworx_save_logged_changes_for_order',
                [
                    'order_id'        => $order->getId(),
                    'notify_customer' => false
                ]
            );
        } catch (\Exception $e) {
            $this->getMessageManager()->addErrorMessage($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function prepareResponse(): string
    {
        return static::ACTION_RELOAD_PAGE;
    }
}
