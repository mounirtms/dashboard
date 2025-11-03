<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Api\Data;

interface GridAdditionalDataInterface
{
    const TABLE_NAME = 'mageworx_ordersgrid_additional_data';

    const KEY_ID               = 'entity_id';
    const KEY_IS_ACTIVE        = 'is_active';
    const KEY_SORT_ORDER       = 'sort_order';
    const KEY_NAME             = 'name';
    const KEY_ROW_COLOR        = 'row_color';
    const KEY_ICON_IMAGE       = 'icon_image';
    const KEY_SHIPPING_METHODS = 'shipping_methods';
    const KEY_PAYMENT_METHODS  = 'payment_methods';
    const KEY_ORDER_STATUSES   = 'order_statuses';

    /**
     * @return bool
     */
    public function getIsActive(): bool;

    /**
     * @param bool $value
     * @return GridAdditionalDataInterface
     */
    public function setIsActive(bool $value): GridAdditionalDataInterface;

    /**
     * @return int|null
     */
    public function getSortOrder(): ?int;

    /**
     * @param int|null $value
     * @return GridAdditionalDataInterface
     */
    public function setSortOrder(?int $value): GridAdditionalDataInterface;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param string $value
     * @return GridAdditionalDataInterface
     */
    public function setName(string $value): GridAdditionalDataInterface;

    /**
     * Row color in any supported CSS format
     *
     * @return string|null
     */
    public function getRowColor(): ?string;

    /**
     * Row color in any supported CSS format
     *
     * @param string|null $value
     * @return GridAdditionalDataInterface
     */
    public function setRowColor(?string $value): GridAdditionalDataInterface;

    /**
     * Path to icon image
     *
     * @return string|null
     */
    public function getIconImage(): ?string;

    /**
     * Path to icon image
     *
     * @param string|null $value
     * @return GridAdditionalDataInterface
     */
    public function setIconImage(?string $value): GridAdditionalDataInterface;

    /**
     * List of affected shipping methods. Null - means for all methods (skip this check).
     *
     * @return array|null
     */
    public function getShippingMethods(): ?array;

    /**
     * List of affected shipping methods. Null - means for all methods (skip this check).
     *
     * @param array|null $value
     * @return GridAdditionalDataInterface
     */
    public function setShippingMethods(?array $value): GridAdditionalDataInterface;

    /**
     * List of affected payment methods. Null - means for all methods (skip this check).
     *
     * @return array|null
     */
    public function getPaymentMethods(): ?array;

    /**
     * List of affected payment methods. Null - means for all methods (skip this check).
     *
     * @param array|null $value
     * @return GridAdditionalDataInterface
     */
    public function setPaymentMethods(?array $value): GridAdditionalDataInterface;

    /**
     * List of affected order statuses. Null - means for all orders (skip this check).
     *
     * @return array|null
     */
    public function getOrderStatuses(): ?array;

    /**
     * List of affected order statuses. Null - means for all orders (skip this check).
     *
     * @param array|null $value
     * @return GridAdditionalDataInterface
     */
    public function setOrderStatuses(?array $value): GridAdditionalDataInterface;
}
