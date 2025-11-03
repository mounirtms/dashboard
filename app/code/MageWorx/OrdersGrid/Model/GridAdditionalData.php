<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Model;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use MageWorx\OrdersGrid\Api\Data\GridAdditionalDataInterface;
use MageWorx\OrdersGrid\Helper\Image as Imagehelper;

/**
 * Additional data for orders grid: color, icon etc.
 */
class GridAdditionalData extends AbstractExtensibleModel implements GridAdditionalDataInterface
{
    /**
     * @var Imagehelper
     */
    protected $imageHelper;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Imagehelper $imageHelper
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context                    $context,
        Registry                   $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory      $customAttributeFactory,
        Imagehelper                $imageHelper,
        AbstractResource           $resource = null,
        AbstractDb                 $resourceCollection = null,
        array                      $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $resource,
            $resourceCollection,
            $data
        );
        $this->imageHelper = $imageHelper;
    }

    /**
     * Set resource model and ID field name
     *
     * @return void
     */
    protected function _construct(): void
    {
        parent::_construct();
        $this->_init(\MageWorx\OrdersGrid\Model\ResourceModel\GridAdditionalData::class);
        $this->setIdFieldName(static::KEY_ID);
    }

    /**
     * Get URL for icon image
     *
     * @return string|null
     */
    public function getIconImageUrl(): ?string
    {
        $imagePath = $this->getIconImage();
        if (!$imagePath) {
            return '';
        }

        return $this->imageHelper->getMediaUrl($imagePath);
    }

    /**
     * @param array $imageData
     * @return GridAdditionalDataInterface
     */
    public function setIconImageData(array $imageData): GridAdditionalDataInterface
    {
        return $this->setData('icon_image_data', $imageData);
    }

    /**
     * @inheritDoc
     */
    public function getIsActive(): bool
    {
        return (bool)$this->getData(static::KEY_IS_ACTIVE);
    }

    /**
     * @inheritDoc
     */
    public function setIsActive(bool $value): GridAdditionalDataInterface
    {
        return $this->setData(static::KEY_IS_ACTIVE, $value);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return (string)$this->getData(static::KEY_NAME);
    }

    /**
     * @inheritDoc
     */
    public function setName(string $value): GridAdditionalDataInterface
    {
        return $this->setData(static::KEY_NAME, $value);
    }

    /**
     * @inheritDoc
     */
    public function getRowColor(): ?string
    {
        return $this->getData(static::KEY_ROW_COLOR);
    }

    /**
     * @inheritDoc
     */
    public function setRowColor(?string $value): GridAdditionalDataInterface
    {
        return $this->setData(static::KEY_ROW_COLOR, $value);
    }

    /**
     * @inheritDoc
     */
    public function getIconImage(): ?string
    {
        return $this->getData(static::KEY_ICON_IMAGE);
    }

    /**
     * @inheritDoc
     */
    public function setIconImage(?string $value): GridAdditionalDataInterface
    {
        return $this->setData(static::KEY_ICON_IMAGE, $value);
    }

    /**
     * @inheritDoc
     */
    public function getShippingMethods(): ?array
    {
        return $this->getDataAsArray(static::KEY_SHIPPING_METHODS);
    }

    /**
     * @inheritDoc
     */
    public function setShippingMethods(?array $value): GridAdditionalDataInterface
    {
        return $this->setData(static::KEY_SHIPPING_METHODS, $value);
    }

    /**
     * @inheritDoc
     */
    public function getPaymentMethods(): ?array
    {
        return $this->getDataAsArray(static::KEY_PAYMENT_METHODS);
    }

    /**
     * @inheritDoc
     */
    public function setPaymentMethods(?array $value): GridAdditionalDataInterface
    {
        return $this->setData(static::KEY_PAYMENT_METHODS, $value);
    }

    /**
     * @inheritDoc
     */
    public function getOrderStatuses(): ?array
    {
        return $this->getDataAsArray(static::KEY_ORDER_STATUSES);
    }

    /**
     * @inheritDoc
     */
    public function setOrderStatuses(?array $value): GridAdditionalDataInterface
    {
        return $this->setData(static::KEY_ORDER_STATUSES, $value);
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): ?int
    {
        return (int)$this->getData(static::KEY_SORT_ORDER);
    }

    /**
     * @inheritDoc
     */
    public function setSortOrder(?int $value): GridAdditionalDataInterface
    {
        return $this->setData(static::KEY_SORT_ORDER, $value);
    }

    /**
     * @param string $key
     * @return array
     */
    protected function getDataAsArray(string $key): array
    {
        $data = $this->getData($key);
        if (empty($data)) {
            return [];
        }

        if (!is_array($data)) {
            $data = explode(',', $data);
        }

        return $data;
    }
}
