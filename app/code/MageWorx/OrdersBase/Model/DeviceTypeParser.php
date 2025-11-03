<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersBase\Model;

use MageWorx\OrdersBase\Api\Data;

/**
 * Class DeviceDataParser
 *
 * Default data parser: does nothing, should be replaced via di.xml with real parser class
 */
class DeviceTypeParser implements \MageWorx\OrdersBase\Api\DeviceTypeParserInterface
{
    const DEVICE_TYPE_DESKTOP              = 0;
    const DEVICE_TYPE_SMARTPHONE           = 1;
    const DEVICE_TYPE_TABLET               = 2;
    const DEVICE_TYPE_FEATURE_PHONE        = 3;
    const DEVICE_TYPE_CONSOLE              = 4;
    const DEVICE_TYPE_TV                   = 5;
    const DEVICE_TYPE_CAR_BROWSER          = 6;
    const DEVICE_TYPE_SMART_DISPLAY        = 7;
    const DEVICE_TYPE_CAMERA               = 8;
    const DEVICE_TYPE_PORTABLE_MEDIA_PAYER = 9;
    const DEVICE_TYPE_PHABLET              = 10;

    /**
     * Detectable device types
     *
     * @var array
     */
    protected static $deviceTypes = [
        'desktop'               => self::DEVICE_TYPE_DESKTOP,
        'smartphone'            => self::DEVICE_TYPE_SMARTPHONE,
        'tablet'                => self::DEVICE_TYPE_TABLET,
        'feature phone'         => self::DEVICE_TYPE_FEATURE_PHONE,
        'console'               => self::DEVICE_TYPE_CONSOLE,
        'tv'                    => self::DEVICE_TYPE_TV,
        'car browser'           => self::DEVICE_TYPE_CAR_BROWSER,
        'smart display'         => self::DEVICE_TYPE_SMART_DISPLAY,
        'camera'                => self::DEVICE_TYPE_CAMERA,
        'portable media player' => self::DEVICE_TYPE_PORTABLE_MEDIA_PAYER,
        'phablet'               => self::DEVICE_TYPE_PHABLET
    ];

    /**
     * @return array|int[]
     */
    public static function getAllDevices(): array
    {
        return self::$deviceTypes;
    }

    /**
     * @inheritDoc
     */
    public function getDeviceName(string $code = null): string
    {
        if ($code !== null) {
            $staticResult = \array_search($code, self::$deviceTypes);
        }

        return !empty($staticResult) ? ucwords($staticResult) : 'n/a';
    }

    /**
     * @inheritDoc
     */
    public function parseData(
        \Magento\Sales\Api\Data\OrderInterface $order,
        Data\DeviceDataInterface $deviceData
    ): void {
        $deviceName = $this->getDeviceName($this->getDeviceCode());
        $deviceData->setDeviceName($deviceName);
    }

    /**
     * @inheritDoc
     */
    public function getDeviceCode(): ?string
    {
        return null;
    }
}
