<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersGrid\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const TABLE_NAME_EXTENDED_GRID = 'mageworx_ordersgrid_grid';

    const XML_PATH_ENABLED                 = 'mageworx_order_management/order_grid/main/enabled';
    const XML_PATH_SYNC_ORDER_GRID         = 'mageworx_order_management/order_grid/main/sync_order_grid';
    const XML_PATH_SYNC_ORDER_COUNT        = 'mageworx_order_management/order_grid/main/sync_order_count';
    const XML_PATH_SYNC_ORDER_BY_CRON      = 'mageworx_order_management/order_grid/main/cron_sync_status';
    const XML_PATH_DISABLED_MASSACTIONS    = 'mageworx_order_management/order_grid/main/disabled_mass_actions';
    const XML_PATH_CAPTURE_INVOICE_COMMENT = 'mageworx_order_management/order_grid/main/capture_notification_comment';

    /**
     * Data constructor.
     *
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    /**
     * Check is enabled modules features
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
    }

    /**
     * Is synchronization using cron enabled (disable life-time synchronization)
     *
     * @return bool
     */
    public function isSyncByCronEnabled()
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SYNC_ORDER_BY_CRON);
    }

    /**
     * Get enable permanent order item removal
     *
     * @return bool
     */
    public function getSyncOrderGrid(): bool
    {
        return (bool)$this->scopeConfig->getValue(self::XML_PATH_SYNC_ORDER_GRID);
    }

    /**
     * Get count of the sync orders
     *
     * @return int
     */
    public function getSyncOrdersCount()
    {
        return intval($this->scopeConfig->getValue(self::XML_PATH_SYNC_ORDER_COUNT));
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getCaptureInvoiceComment(int $storeId = null)
    {
        return $this->scopeConfig->getValue(
            static::XML_PATH_CAPTURE_INVOICE_COMMENT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return array
     */
    public function getDisabledMassActions(): array
    {
        $massActions = $this->scopeConfig->getValue(self::XML_PATH_DISABLED_MASSACTIONS);
        if (empty($massActions)) {
            $massActions = [];
        }

        if (!is_array($massActions)) {
            $massActions = explode(',', $massActions);
        }

        if ($massActions === false) {
            $massActions = [];
        }

        return $massActions;
    }
}
