<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-12-11T14:54:26+00:00
 * File:          Observer/CustomVariables/AmastyOrderAttributes.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Observer\CustomVariables;

use Magento\Framework\Event\ObserverInterface;

/**
 * Add custom fields added by Amasty Order Attributes extension to the list of variable that can be exported
 *
 * Class AmastyOrderAttributes
 * @package Xtento\PdfCustomizer\Observer\CustomVariables
 */
class AmastyOrderAttributes implements ObserverInterface
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
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $localeDate;

    /**
     * AmastyOrderAttributes constructor.
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
    ) {
        $this->objectManager = $objectManager;
        $this->moduleList = $moduleList;
        $this->localeDate = $localeDate;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->moduleList->getOne('Amasty_Orderattr') === null) {
            // Not installed
            return $this;
        }

        $event = $observer->getEvent();
        if (isset($event->getVariables()["order"]) && is_object($event->getVariables()["order"])) {
            $order = $event->getVariables()["order"];

            $customVariables['amasty_orderattributes'] = new \Magento\Framework\DataObject();
            try {
                // Check module version
                $moduleInfo = $this->moduleList->getOne('Amasty_Orderattr');
                if (isset($moduleInfo['setup_version']) && version_compare($moduleInfo['setup_version'], '3.0.0', '>=')) {
                    // Version 3.0.0+
                    $entity = $this->objectManager->get('\Amasty\Orderattr\Model\Entity\EntityResolver')->getEntityByOrder($order);
                    if (!$entity->isObjectNew()) {
                        $form = $this->createEntityForm($entity, $order);
                        $outputData = $form->outputData(\Magento\Eav\Model\AttributeDataFactory::OUTPUT_FORMAT_HTML);
                        foreach ($outputData as $attributeCode => $data) {
                            if (!empty($data)) {
                                $customVariables['amasty_orderattributes'][$attributeCode] = $data;
                            }
                        }
                    }
                } else {
                    $orderAttributeValue = $this->objectManager->get('\Amasty\Orderattr\Model\Order\Attribute\Value');
                    $orderAttributeValue->loadByOrderId($order->getId());
                    $attributeMetadataDataProvider = $this->objectManager->get('\Amasty\Orderattr\Model\AttributeMetadataDataProvider');
                    $attributeCollection = $attributeMetadataDataProvider->loadAttributesForEditFormByStoreId($order->getStoreId());
                    if ($attributeCollection->getSize()) {
                        foreach ($attributeCollection as $attribute) {
                            $value = $this->prepareAttributeValue($orderAttributeValue, $attribute);
                            if ($attribute->getFrontendLabel() && $value) {
                                $customVariables['amasty_orderattributes'][$attribute->getAttributeCode()] = str_replace('$', '\$', $value);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {

            }

            // Return custom variables, merge with other custom variables
            $event->getTransport()->setCustomVariables(array_merge($event->getTransport()->getCustomVariables(), $customVariables));
        }

        return $this;
    }

    protected function prepareAttributeValue($orderAttributeValue, $attribute)
    {
        $value = $orderAttributeValue->getData($attribute->getAttributeCode());
        switch ($attribute->getFrontendInput())
        {
            case 'select':
            case 'boolean':
            case 'radios':
                $value = $attribute->getSource()->getOptionText($value);
                break;
            case 'date':
                $value = $this->localeDate->formatDate($value);
                break;
            case 'datetime':
                $value = $this->localeDate->formatDateTime($value);
                break;
            case 'checkboxes':
                $value = explode(',', $value);
                $labels = [];
                foreach ($value as $item) {
                    $labels[] = $attribute->getSource()->getOptionText($item);
                }
                $value = implode(', ', $labels);
                break;
        }

        return $value;
    }

    protected function createEntityForm($entity, $order)
    {
        $formProcessor = $this->objectManager->get('\Amasty\Orderattr\Model\Value\Metadata\FormFactory')->create();
        $formProcessor->setFormCode('adminhtml_order_view')
            ->setEntity($entity)
            ->setStore($order->getStore());

        return $formProcessor;
    }
}