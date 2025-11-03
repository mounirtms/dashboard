<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model\OrderManager;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use MageWorx\OrderEditor\Api\Data\OrderManager\ShippingMethodDataInterface;
use MageWorx\OrderEditor\Api\Data\OrderManager\ShippingMethodDataInterfaceFactory;
use MageWorx\OrderEditor\Api\OrderManager\ShippingMethodManagerInterface;
use MageWorx\OrderEditor\Api\OrderRepositoryInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use MageWorx\OrderEditor\Model\Shipping as EditShippingModel;

class ShippingMethodManager implements ShippingMethodManagerInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var ShippingMethodDataInterfaceFactory
     */
    private $shippingMethodDataFactory;

    /**
     * @var EditShippingModel
     */
    private $editShippingModel;

    /**
     * @var TaxHelper
     */
    private $taxHelper;

    /**
     * ShippingMethodManager constructor.
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param ShippingMethodDataInterfaceFactory $shippingMethodDataFactory
     * @param EditShippingModel $editShippingModel
     * @param TaxHelper $taxHelper
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ShippingMethodDataInterfaceFactory $shippingMethodDataFactory,
        EditShippingModel $editShippingModel,
        TaxHelper $taxHelper
    ) {
        $this->orderRepository           = $orderRepository;
        $this->shippingMethodDataFactory = $shippingMethodDataFactory;
        $this->editShippingModel         = $editShippingModel;
        $this->taxHelper                 = $taxHelper;
    }

    /**
     * @inheritDoc
     */
    public function getShippingMethodByOrderId(int $orderId): ShippingMethodDataInterface
    {
        $order          = $this->orderRepository->getById($orderId);
        $shippingMethod = $order->getShippingMethod(false);

        $shippingPriceExclTax = $order->getShippingInclTax();
        $shippingTax          = $order->getShippingTaxAmount();
        $baseShippingExclTax  = $order->getBaseShippingAmount();
        $baseShippingTax      = $order->getBaseShippingTaxAmount();
        if ($this->taxHelper->displayPriceExcludingTax()) {
            $price     = $shippingPriceExclTax;
            $basePrice = $baseShippingExclTax;
        } else {
            $price     = $shippingPriceExclTax + $shippingTax;
            $basePrice = $baseShippingExclTax + $baseShippingTax;
        }

        /** @var ShippingMethodDataInterface $shippingMethodData */
        $shippingMethodData = $this->shippingMethodDataFactory->create();
        if ($order->getShippingTaxAmount() && $order->getShippingAmount() > 0) {
            $shippingTaxPercent = $order->getShippingTaxAmount() / $order->getShippingAmount() * 100;
        } else {
            $shippingTaxPercent = 0;
        }
        $shippingMethodData->setCode($shippingMethod)
                           ->setTitle($order->getShippingDescription())
                           ->setPriceExclTax($order->getShippingAmount())
                           ->setPriceInclTax($order->getShippingInclTax())
                           ->setDiscountAmount($order->getShippingDiscountAmount())
                           ->setTaxPercent($shippingTaxPercent);

        return $shippingMethodData;
    }

    /**
     * @inheritDoc
     */
    public function setShippingMethodByOrderId(
        int $orderId,
        ShippingMethodDataInterface $shippingMethodData
    ): OrderInterface {
        $params             = $this->prepareParams($shippingMethodData);
        $params['order_id'] = $orderId;

        $this->editShippingModel->setOrderId($orderId)
                                ->initParams($params);

        $this->editShippingModel->updateShippingMethod();

        $order = $this->orderRepository->getById($orderId);

        return $order;
    }

    /**
     * Collect params from the shipping method data to the array.
     * Contains validation.
     *
     * @param ShippingMethodDataInterface $shippingMethodData
     * @return array
     * @throws LocalizedException
     */
    protected function prepareParams(ShippingMethodDataInterface $shippingMethodData): array
    {
        $params                    = [];
        $params['shipping_method'] = $shippingMethodData->getCode();
        $params['description']     = $shippingMethodData->getTitle();
        $params['price_excl_tax']  = $shippingMethodData->getPriceExclTax();
        $params['price_incl_tax']  = $shippingMethodData->getPriceInclTax();
        $params['tax_percent']     = $shippingMethodData->getTaxPercent();
        $params['discount_amount'] = $shippingMethodData->getDiscountAmount();
        $params['tax_rates']       = $shippingMethodData->getTaxRates() ?? [];

        return $params;
    }
}
