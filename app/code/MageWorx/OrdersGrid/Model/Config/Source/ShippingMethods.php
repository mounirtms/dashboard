<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersGrid\Model\Config\Source;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Option\ArrayInterface;
use Magento\Shipping\Model\Carrier\AbstractCarrierInterface;
use Magento\Shipping\Model\CarrierFactory;

class ShippingMethods implements ArrayInterface
{
    /**
     * Core store config
     *
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var array|null
     */
    private $dhlMethods;

    /**
     * @var CarrierFactory
     */
    private $carrierFactory;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param CarrierFactory $carrierFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CarrierFactory $carrierFactory
    ) {
        $this->scopeConfig    = $scopeConfig;
        $this->carrierFactory = $carrierFactory;
    }

    /**
     * Return array of carriers.
     * If $isActiveOnlyFlag is set to true, will return only active carriers
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $carriers = $this->getAllCarriers();
        foreach ($carriers as $carrierCode => $carrierModel) {
            if ($carrierModel->getCarrierCode() === 'dhl' &&
                is_a($carrierModel, 'Magento\Dhl\Model\Carrier', true) &&
                method_exists($carrierModel, 'getDhlProductTitle')
            ) {
                /** @var \Magento\Dhl\Model\Carrier $carrierModel */
                $carrierMethods = $this->getDhlAllowedMethods($carrierModel);
            } else {
                $carrierMethods = $carrierModel->getAllowedMethods();
            }

            if (!$carrierMethods) {
                continue;
            }
            $carrierTitle          = $this->scopeConfig->getValue(
                'carriers/' . $carrierCode . '/title',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
            $methods[$carrierCode] = ['label' => $carrierTitle, 'value' => []];
            foreach ($carrierMethods as $methodCode => $methodTitle) {
                if (is_array($methodTitle)) {
                    continue;
                }
                $methods[$carrierCode]['value'][] = [
                    'value' => $carrierCode . '_' . $methodCode,
                    'label' => '[' . $carrierCode . '] ' . ($methodTitle ? $methodTitle : $methodCode),
                ];
            }
        }

        if (empty($methods)) {
            $methods = [
                'label' => [],
                'value' => []
            ];
        }

        return $methods;
    }

    /**
     * @param \Magento\Dhl\Model\Carrier $dhlCarrier
     * @return array
     */
    public function getDhlAllowedMethods($dhlCarrier): array
    {
        if ($this->dhlMethods === null) {
            $docMethodsPath    = 'carriers/dhl/doc_methods';
            $nonDocMethodsPath = 'carriers/dhl/nondoc_methods';

            $docMethods    = $this->scopeConfig->getValue($docMethodsPath);
            $nonDocMethods = $this->scopeConfig->getValue($nonDocMethodsPath);

            $allowedMethods = array_merge(
                explode(',', (string)$docMethods),
                explode(',', (string)$nonDocMethods)
            );

            $dhlMethods = array_merge(
                $dhlCarrier->getDhlProducts($dhlCarrier::DHL_CONTENT_TYPE_DOC),
                $dhlCarrier->getDhlProducts($dhlCarrier::DHL_CONTENT_TYPE_NON_DOC)
            );

            $methods = [];
            foreach ($allowedMethods as $method) {
                $methods[$method] = isset($dhlMethods[$method]) ? (string)$dhlMethods[$method] : 'CODE: ' . $method;
            }

            $this->dhlMethods = $methods;
        }

        return $this->dhlMethods ?? [];
    }

    /**
     * Retrieve all system carriers
     *
     * @param   mixed $store
     * @return  AbstractCarrierInterface[]
     */
    public function getAllCarriers($store = null): array
    {
        $carriers = [];
        $config = $this->scopeConfig->getValue(
            'carriers',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
        foreach (array_keys($config) as $carrierCode) {
            $className = $this->scopeConfig->getValue(
                'carriers/' . $carrierCode . '/model',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $store
            );

            if (!$className || !class_exists($className)) {
                continue;
            }

            $model = $this->carrierFactory->create($carrierCode, $store);
            if ($model) {
                $carriers[$carrierCode] = $model;
            }
        }

        return $carriers ?? [];
    }
}
