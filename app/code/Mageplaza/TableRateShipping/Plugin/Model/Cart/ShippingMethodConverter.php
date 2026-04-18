<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_TableRateShipping
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\TableRateShipping\Plugin\Model\Cart;

use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Mageplaza\Core\Helper\Media;
use Mageplaza\TableRateShipping\Model\Method;
use Mageplaza\TableRateShipping\Model\MethodFactory;
use Psr\Log\LoggerInterface;

/**
 * Class ShippingMethodConverter
 * @package Mageplaza\TableRateShipping\Plugin\Model\Cart
 */
class ShippingMethodConverter
{
    /**
     * @var Media
     */
    private $mediaHelper;

    /**
     * @var MethodFactory
     */
    private $methodFactory;

    /**
     * @var ExtensionAttributesFactory
     */
    private $attributesFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ShippingMethodConverter constructor.
     *
     * @param Media $mediaHelper
     * @param MethodFactory $methodFactory
     * @param ExtensionAttributesFactory $attributesFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Media $mediaHelper,
        MethodFactory $methodFactory,
        ExtensionAttributesFactory $attributesFactory,
        LoggerInterface $logger
    ) {
        $this->mediaHelper       = $mediaHelper;
        $this->methodFactory     = $methodFactory;
        $this->attributesFactory = $attributesFactory;
        $this->logger            = $logger;
    }

    /**
     * @param \Magento\Quote\Model\Cart\ShippingMethodConverter $subject
     * @param ShippingMethodInterface $result
     *
     * @return ShippingMethodInterface
     */
    public function afterModelToDataObject(
        \Magento\Quote\Model\Cart\ShippingMethodConverter $subject,
        ShippingMethodInterface $result
    ) {
        if ($result->getCarrierCode() !== 'mptablerate') {
            return $result;
        }

        // CRITICAL FIX: Check if method_code is valid before loading
        $methodCode = $result->getMethodCode();
        if (!$methodCode || $methodCode === null || $methodCode === '') {
            $this->logger->warning('Mageplaza TableRate: Skipping rate with null method_code', [
                'carrier' => $result->getCarrierCode(),
                'title' => $result->getCarrierTitle()
            ]);
            return $result;
        }

        /** @var Method $method */
        $method = $this->methodFactory->create()->load($methodCode);

        // Verify method loaded successfully
        if (!$method || !$method->getId()) {
            $this->logger->warning('Mageplaza TableRate: Could not load method', [
                'method_code' => $methodCode
            ]);
            return $result;
        }

        $attributes = $result->getExtensionAttributes();

        if ($attributes === null) {
            $attributes = $this->attributesFactory->create(ShippingMethodInterface::class);
        }

        if ($img = $method->getImage()) {
            try {
                $attributes->setMptablerateImage($this->mediaHelper->getMediaUrl($img));
            } catch (NoSuchEntityException $e) {
                $this->logger->critical($e->getMessage());
            }
        }

        $attributes->setMptablerateComment(__($method->getComment()));

        $result->setExtensionAttributes($attributes);

        return $result;
    }
}
