<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model;

use Magento\Framework\Api\Search\Document;
use MageWorx\OrderEditor\Api\Data\OrderManager\PaymentMethodDataInterface;

/**
 * Class PaymentMethodData
 *
 * Payment method data object
 */
class PaymentMethodData extends Document implements PaymentMethodDataInterface
{
    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return $this->_get(static::CODE);
    }

    /**
     * @inheritDoc
     */
    public function setCode(string $value): PaymentMethodDataInterface
    {
        return $this->setData(static::CODE, $value);
    }
}
