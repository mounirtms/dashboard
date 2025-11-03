<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model\OrderManager;

use Magento\Framework\Api\AttributeInterfaceFactory;
use MageWorx\OrderEditor\Api\Data\OrderManager\PaymentMethodDataInterface;
use MageWorx\OrderEditor\Api\Data\OrderManager\PaymentMethodDataInterfaceFactory;
use MageWorx\OrderEditor\Api\OrderManager\PaymentMethodManagerInterface;
use MageWorx\OrderEditor\Api\OrderRepositoryInterface;

/**
 * Class PaymentMethodManager
 *
 * Manage payment method of the order
 *
 * @api
 */
class PaymentMethodManager implements PaymentMethodManagerInterface
{
    /**
     * @var PaymentMethodDataInterfaceFactory
     */
    private $paymentMethodDataFactory;

    /**
     * @var AttributeInterfaceFactory
     */
    private $customAttributesFactory;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var \MageWorx\OrderEditor\Model\Payment
     */
    private $paymentEditor;

    /**
     * PaymentMethodManager constructor.
     *
     * @param PaymentMethodDataInterfaceFactory $paymentMethodDataFactory
     * @param AttributeInterfaceFactory $customAttributesFactory
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        PaymentMethodDataInterfaceFactory $paymentMethodDataFactory,
        AttributeInterfaceFactory $customAttributesFactory,
        OrderRepositoryInterface $orderRepository,
        \MageWorx\OrderEditor\Model\Payment $paymentEditor
    ) {
        $this->paymentMethodDataFactory = $paymentMethodDataFactory;
        $this->customAttributesFactory  = $customAttributesFactory;
        $this->orderRepository          = $orderRepository;
        $this->paymentEditor            = $paymentEditor;
    }

    /**
     * Get information about selected payment method
     *
     * @param int $orderId
     * @return PaymentMethodDataInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getPaymentMethodByOrderId(int $orderId): PaymentMethodDataInterface
    {
        /** @var PaymentMethodDataInterface $paymentMethodData */
        $paymentMethodData = $this->paymentMethodDataFactory->create();
        $order             = $this->orderRepository->getById($orderId);

        $paymentMethodData->setCode($order->getPayment()->getMethod());

        return $paymentMethodData;
    }

    /**
     * @param int $orderId
     * @param PaymentMethodDataInterface $paymentMethodData
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return void
     */
    public function updatePaymentMethodByOrderId(
        int $orderId,
        PaymentMethodDataInterface $paymentMethodData
    ): void {
        $params = [
            'order_id'       => $orderId,
            'payment_method' => $paymentMethodData->getCode()
        ];

        $customAttributes = $this->extractCustomAttributes($paymentMethodData);
        $params           = array_merge_recursive($params, $customAttributes);
        $this->paymentEditor->initParams($params);
        $this->paymentEditor->updatePaymentMethod();
    }

    /**
     * @param PaymentMethodDataInterface $paymentMethodData
     * @return array
     */
    private function extractCustomAttributes(PaymentMethodDataInterface $paymentMethodData): array
    {
        $data = [];

        $customAttributes = $paymentMethodData->getCustomAttributes();
        if (!empty($customAttributes)) {
            foreach ($customAttributes as $attribute) {
                $data[$attribute->getAttributeCode()] = $attribute->getValue();
            }
        }

        $data = array_filter($data);

        return $data;
    }
}
