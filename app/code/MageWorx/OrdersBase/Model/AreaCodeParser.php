<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersBase\Model;

use Magento\Framework\App\Area;
use MageWorx\OrdersBase\Api\Data;

class AreaCodeParser implements \MageWorx\OrdersBase\Api\AreaCodeParserInterface
{
    protected static $areaCodes = [
        self::AREA_UNKNOWN => 'Unknown',
        self::AREA_FRONT   => 'Frontend',
        self::AREA_ADMIN   => 'Admin',
        self::AREA_REST    => 'REST API',
        self::AREA_SOAP    => 'SOAP API',
    ];

    /**
     * @var \Magento\Framework\HTTP\Header
     */
    protected $httpHeader;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;

    /**
     * @var \MageWorx\OrdersBase\Helper\Data
     */
    protected $helper;

    /**
     * @return string[]
     */
    public static function getAllAreas(): array
    {
        return self::$areaCodes;
    }

    /**
     * AreaCodeParser constructor.
     *
     * @param \Magento\Framework\HTTP\Header $httpHeader
     * @param \Magento\Framework\App\State $state
     * @param \MageWorx\OrdersBase\Helper\Data $helper
     */
    public function __construct(
        \Magento\Framework\HTTP\Header $httpHeader,
        \Magento\Framework\App\State $state,
        \MageWorx\OrdersBase\Helper\Data $helper
    ) {
        $this->httpHeader = $httpHeader;
        $this->state      = $state;
        $this->helper     = $helper;
    }

    /**
     * @inheritDoc
     */
    public function getAreaName(): string
    {
        $referer          = $this->httpHeader->getHttpReferer(true);
        $urlPath          = parse_url($referer, PHP_URL_PATH);
        $checkoutBasePath = $this->helper->getCheckoutUrl();
        $searchablePath   = '/' . $checkoutBasePath . '/';

        if ($this->state->getAreaCode() == Area::AREA_FRONTEND) {
            $areaCode = static::AREA_FRONT;
        } elseif ($this->state->getAreaCode() == Area::AREA_ADMINHTML) {
            $areaCode = static::AREA_ADMIN;
        } elseif ($this->state->getAreaCode() == Area::AREA_WEBAPI_REST) {
            if (preg_match($searchablePath, $urlPath)) {
                $areaCode = static::AREA_FRONT;
            } else {
                $areaCode = static::AREA_REST;
            }
        } elseif ($this->state->getAreaCode() == Area::AREA_WEBAPI_SOAP) {
            $areaCode = static::AREA_SOAP;
        } else {
            $areaCode = static::AREA_UNKNOWN;
        }

        return static::$areaCodes[$areaCode];
    }

    /**
     * @inheritDoc
     */
    public function parseData(
        \Magento\Sales\Api\Data\OrderInterface $order,
        Data\DeviceDataInterface $deviceData
    ): void {
        $deviceData->setAreaName($this->getAreaName());
    }
}
