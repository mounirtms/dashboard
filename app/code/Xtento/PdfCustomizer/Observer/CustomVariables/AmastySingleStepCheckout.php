<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2020-01-08T19:43:07+00:00
 * File:          Observer/CustomVariables/AmastySingleStepCheckout.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Observer\CustomVariables;

use Magento\Framework\Event\ObserverInterface;

/**
 * Add custom fields added by Amasty Single Step Checkout extension to the list of variable that can be exported
 *
 * Class AmastySingleStepCheckout
 * @package Xtento\PdfCustomizer\Observer\CustomVariables
 */
class AmastySingleStepCheckout implements ObserverInterface
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    protected $moduleList;

    /**
     * AmastySingleStepCheckout constructor.
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Module\ModuleListInterface $moduleList
    ) {
        $this->objectManager = $objectManager;
        $this->moduleList = $moduleList;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->moduleList->getOne('Amasty_Checkout') === null) {
            // Not installed
            return $this;
        }

        $event = $observer->getEvent();
        if (isset($event->getVariables()["order"]) && is_object($event->getVariables()["order"])) {
            $order = $event->getVariables()["order"];

            $customVariables['amasty_checkout'] = new \Magento\Framework\DataObject();
            try {
                $delivery = $this->objectManager->get('\Amasty\Checkout\Model\DeliveryFactory')->create();
                $delivery = $delivery->findByOrderId($order->getId());
                if ($delivery->getId()) {
                    $customVariables['amasty_checkout']['date'] = $delivery->getDate();
                    $customVariables['amasty_checkout']['time'] = $delivery->getTime();
                    $customVariables['amasty_checkout']['comment'] = $delivery->getComment();
                }
            } catch (\Exception $e) {}

            $customVariables['amasty_checkout_additional'] = new \Magento\Framework\DataObject();
            try {
                $additionalFieldsCollection = $this->objectManager->get('\Amasty\Checkout\Model\ResourceModel\AdditionalFields\CollectionFactory')->create();
                $additionalFields = $additionalFieldsCollection->addFieldToFilter('quote_id', $order->getQuoteId())->getFirstItem();
                if ($additionalFields->getId()) {
                    foreach ($additionalFields->getData() as $key => $value) {
                        $customVariables['amasty_checkout_additional'][$key] = $value;
                    }
                }
            } catch (\Exception $e) {}

            // Return custom variables, merge with other custom variables
            $event->getTransport()->setCustomVariables(array_merge($event->getTransport()->getCustomVariables(), $customVariables));
        }

        return $this;
    }
}