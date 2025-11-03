<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Replace original view model for compatibility purposes with old Magento versions (<=2.4.5).
 * @see \Magento\Backend\ViewModel\LimitTotalNumberOfProductsInGrid
 * Used in vendor/magento/module-backend/view/adminhtml/templates/widget/grid/extended.phtml
 */
class ProductsSearchGridViewModel implements ArgumentInterface
{
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if configuration setting to limit total number of products in grid is enabled.
     * Replace original view model for compatibility purposes with old Magento versions (<=2.4.5).
     *
     * @return bool
     */
    public function limitTotalNumberOfProducts(): bool
    {
        return $this->scopeConfig->isSetFlag('admin/grid/limit_total_number_of_products');
    }

    /**
     * Get records threshold for limit total number of products in collection.
     * Replace original view model for compatibility purposes with old Magento versions (<=2.4.5).
     *
     * @return int
     */
    public function getRecordsLimit(): int
    {
        return (int)$this->scopeConfig->getValue('admin/grid/records_limit');
    }
}
