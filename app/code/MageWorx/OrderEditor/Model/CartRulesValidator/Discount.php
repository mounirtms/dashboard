<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Model\CartRulesValidator;

use Laminas\Validator\ValidatorInterface;

/**
 * Class Discount
 *
 * Ignore discount from cart rules during order editing
 */
class Discount implements ValidatorInterface
{
    /**
     * @param $value
     * @return bool
     */
    public function isValid($value)
    {
        if (!$value instanceof \Magento\Quote\Model\Quote\Item\AbstractItem) {
            return true;
        }

        if ($value->getIgnoreCartRules()) {
            return false;
        }

        return true;
    }

    /**
     * @return array|string[]
     */
    public function getMessages()
    {
        return [];
    }
}
