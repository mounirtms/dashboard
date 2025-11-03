<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersBase\Api;

interface DeviceTypeParserInterface extends DataParserInterface
{
    /**
     * Get device name (human readable) by code
     *
     * @param string|null $code
     * @return string
     */
    public function getDeviceName(string $code = null): string;

    /**
     * Get device code from which order was placed
     *
     * @return string|null
     */
    public function getDeviceCode(): ?string;
}
