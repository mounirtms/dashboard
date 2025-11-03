<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types = 1);

namespace MageWorx\OrderEditor\Model\CustomerGroupPrices;

use Magento\Framework\Registry;

class DetectCustomerGroupIdFromEditedOrder
{
    protected Registry $registry;
    protected int      $sortOrder = 30;

    public function __construct(
        Registry $registry,
        int      $sortOrder = 30
    ) {
        $this->registry  = $registry;
        $this->sortOrder = $sortOrder;
    }

    /**
     * Detect current customer group id for active calculations.
     * May be different for different scopes (frontend, adminhtml, webapi_rest etc.).
     *
     * @return int|null
     */
    public function detect(): ?int
    {
        $customerGroupId = null;
        /** @var \MageWorx\OrderEditor\Model\Order|null $currentlyEditedOrder */
        $currentlyEditedOrder = $this->registry->registry('ordereditor_order');
        if ($currentlyEditedOrder instanceof \Magento\Sales\Model\Order) {
            $customerGroupId = $currentlyEditedOrder->getCustomerGroupId();
            // Check $groupId type. If not int or null convert it to int
            if (!is_int($customerGroupId) && !is_null($customerGroupId)) {
                $customerGroupId = (int)$customerGroupId;
            }
        }

        return $customerGroupId;
    }

    /**
     * Sort order of this detector. The higher the value, the higher the priority.
     * If two detectors have the same priority, the one that was added first will be used.
     *
     * @return int
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
