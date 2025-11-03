<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api\Data\OrderManager;

use Magento\Framework\Api\CustomAttributesDataInterface;

interface OrderInfoInterface extends CustomAttributesDataInterface
{
    /**
     * Default params:
     */
    const CREATED_AT = 'created_at';
    const STATUS     = 'status';
    const STATE      = 'state';

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @param string $value
     * @return OrderInfoInterface
     */
    public function setCreatedAt(string $value): OrderInfoInterface;

    /**
     * @return string|null
     */
    public function getStatus(): ?string;

    /**
     * @param string $value
     * @return OrderInfoInterface
     */
    public function setStatus(string $value): OrderInfoInterface;

    /**
     * @return string|null
     */
    public function getState(): ?string;

    /**
     * @param string $value
     * @return OrderInfoInterface
     */
    public function setState(string $value): OrderInfoInterface;
}
