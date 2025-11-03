<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\GoogleTagManager\Model\DataLayer;

use Magefan\GoogleTagManager\Model\AbstractDataLayer;
use Magefan\GoogleTagManager\Model\Config;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Magefan\GoogleTagManager\Api\DataLayer\Order\ItemInterface;

abstract class AbstractOrder extends AbstractDataLayer
{
    /**
     * @var ItemInterface
     */
    private $gtmItem;

    /**
     * Purchase constructor.
     *
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param CategoryRepositoryInterface $categoryRepository
     * @param ItemInterface $gtmItem
     */
    public function __construct(
        Config $config,
        StoreManagerInterface $storeManager,
        CategoryRepositoryInterface $categoryRepository,
        ItemInterface $gtmItem
    ) {
        $this->gtmItem = $gtmItem;
        parent::__construct($config, $storeManager, $categoryRepository);
    }

    /**
     * @inheritDoc
     */
    public function get(Order $order, string $requester = ''): array
    {
        if ($order) {
            $items = [];
            foreach ($order->getAllVisibleItems() as $item) {
                $items[] = $this->gtmItem->get($item);
            }

            // Add Mageplaza Table Rate Shipping details if applicable
            $shippingMethodCode = $order->getShippingMethod();
            $mageplazaShipping = [];
            if (strpos($shippingMethodCode, 'mptablerate') !== false) {
                try {
                    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                    $methodFactory = $objectManager->get(\Mageplaza\TableRateShipping\Model\MethodFactory::class);
                    $method = $methodFactory->create()->load($order->getShippingMethod());
                    $mageplazaShipping = [
                        'mageplaza_method_title' => $method->getName(),
                        'mageplaza_method_comment' => $method->getComment(),
                        // Add more fields as needed
                    ];
                } catch (\Exception $e) {
                    $mageplazaShipping = ['error' => $e->getMessage()];
                }
            }

            return $this->eventWrap([
                'event' => $this->getEventName(),
                'ecommerce' => [
                    'transaction_id' => $order->getIncrementId(),
                    'value' => $this->getValue($order),
                    'tax' => $this->formatPrice((float)$order->getTaxAmount()),
                    'shipping' => $this->formatPrice((float)$order->getShippingAmount()),
                    'currency' => $this->getCurrentCurrencyCode(),
                    'coupon' => $order->getCouponCode() ?: '',
                    'items' => $items,
                    'mageplaza_shipping' => $mageplazaShipping
                ],
                'is_virtual' => (bool)$order->getIsVirtual(),
                'shipping_description' => $order->getShippingDescription(),
                'customer_is_guest' => (bool)$order->getCustomerIsGuest(),
                'customer_identifier' => hash('sha256', (string)$order->getCustomerEmail()),
            ]);
        }

        return [];
    }

    /**
     * @param Order $order
     * @return float
     */
    protected function getValue(Order $order): float
    {
        $orderValue = (float)$order->getGrandTotal();

        if (!$this->config->isPurchaseTaxEnabled()) {
            $orderValue -= $order->getTaxAmount();
        }

        if (!$this->config->isPurchaseShippingEnabled()) {
            $orderValue -= $order->getShippingAmount();
        }

        return $this->formatPrice((float)$orderValue);
    }

    /**
     * @return string
     */
    abstract protected function getEventName(): string;
}
