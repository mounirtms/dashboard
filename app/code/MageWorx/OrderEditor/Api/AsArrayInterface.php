<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api;

interface AsArrayInterface
{
    /**
     * @return array
     * @throws \ReflectionException
     */
    public function asArray(): array;
}
