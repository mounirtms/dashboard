<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersBase\Api;

interface AreaCodeParserInterface extends DataParserInterface
{
    const AREA_UNKNOWN = 0;
    const AREA_FRONT   = 1;
    const AREA_ADMIN   = 2;
    const AREA_REST    = 3;
    const AREA_SOAP    = 4;

    /**
     * Get area code from which order was placed
     *
     * @return string
     */
    public function getAreaName(): string;
}
