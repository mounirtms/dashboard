<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Model\ResourceModel\Order\Grid;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Sales\Api\Data\OrderAddressInterface as OrderAddress;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OriginalCollection;
use MageWorx\OrdersGrid\Helper\Data as Helper;
use MageWorx\OrdersGrid\Model\Config\Source\ShippedStatus;

/**
 * Order grid extended collection
 */
class Collection extends OriginalCollection
{
    const ALL_COLUMNS = [
        'coupon_code', // sales_order
        'weight', // sales_order
        'subtotal_purchased', // sales_order
        'discount_amount', // sales_order
        'base_total_paid', // sales_order
        'product_name', // sales_order_item
        'product_sku', // sales_order_item
        'invoices', // sales_invoice
        'tracking_number', // sales_shipment_track
        'billing_fax',
        'billing_region',
        'billing_postcode',
        'billing_city',
        'billing_telephone',
        'billing_country_id',
        'billing_company',
        'shipping_fax',
        'shipping_region',
        'shipping_postcode',
        'shipping_city',
        'shipping_telephone',
        'shipping_country_id',
        'shipping_company',
        'product_thumbnail',
        'vat_id',
        'icon_image'
    ];

    const ADDITIONAL_COLUMNS = [
        'coupon_code',
        'weight',
        'subtotal_purchased',
        'discount_amount',
        'base_total_paid'
    ];

    const INVOICE_COLUMNS = [
        'invoices'
    ];

    const SHIPMENT_COLUMNS = [
        'shipments'
    ];

    const SHIPMENT_TRACKING_COLUMNS = [
        'tracking_number'
    ];

    const ORDER_ITEM_COLUMNS = [
        'product_name',
        'product_sku',
        'product_type',
        'product_thumbnail',
        'product_quantity',
        'per_product_quantity',
        'per_product_custom_options',
        'shipment_status'
    ];

    const BILLING_ADDRESS_COLUMNS = [
        'billing_fax',
        'billing_region',
        'billing_postcode',
        'billing_city',
        'billing_telephone',
        'billing_country_id',
        'billing_company',
        'vat_id'
    ];

    const SHIPPING_ADDRESS_COLUMNS = [
        'shipping_fax',
        'shipping_region',
        'shipping_postcode',
        'shipping_city',
        'shipping_telephone',
        'shipping_country_id',
        'shipping_company'
    ];

    const TAX_COLUMNS = [
        'applied_tax_code',
        'applied_tax_amount',
        'applied_tax_base_amount',
        'applied_tax_base_real_amount'
    ];

    const HIDDEN_SERVICE_COLUMNS = [
        'row_color',
        'icon_image'
    ];

    const TAX_RATE_DELIMITER    = '||';
    const TAX_PERCENT_DELIMITER = '::';

    protected function _renderFiltersBefore()
    {
        $joinTable = $this->getTable(Helper::TABLE_NAME_EXTENDED_GRID);
        $columns   = array_merge(
            static::ADDITIONAL_COLUMNS,
            static::INVOICE_COLUMNS,
            static::SHIPMENT_TRACKING_COLUMNS,
            static::SHIPMENT_COLUMNS,
            static::ORDER_ITEM_COLUMNS,
            static::BILLING_ADDRESS_COLUMNS,
            static::SHIPPING_ADDRESS_COLUMNS,
            static::TAX_COLUMNS,
            static::HIDDEN_SERVICE_COLUMNS
        );
        $this->getSelect()->joinLeft(
            ['eog' => $joinTable],
            'main_table.entity_id = eog.order_id',
            $columns
        );
        parent::_renderFiltersBefore();
    }

    /**
     * {@inheritdoc}
     */
    protected function _initSelect()
    {
        parent::_initSelect();

        foreach (static::ALL_COLUMNS as $column) {
            $this->addFilterToMap($column, 'eog.' . $column);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addFieldToFilter($field, $condition = null)
    {
        if ($field === 'product_type') {
            if (is_array($condition)) {
                foreach ($condition as $key => $value) {
                    if ($key === 'in') {
                        foreach ($value as $val) {
                            $condition[]['finset'] = $val;
                        }
                        unset($condition['in']);
                    }
                }
            }
        }

        return parent::addFieldToFilter($field, $condition);
    }

    /**
     * @param int $orderId
     * @return bool
     */
    public function isOrderExists(int $orderId): bool
    {
        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();
        $select     = $connection->select();
        $select->from($this->getTable(Helper::TABLE_NAME_EXTENDED_GRID), ['COUNT(`order_id`)']);
        $select->where('`order_id` = ' . $orderId);
        $result = $connection->fetchOne($select);

        return (bool)$result;
    }

    /**
     * Aggregate orders additional data in our table
     *
     * @param array $orderIds
     * @return $this
     */
    public function syncOrdersData(array $orderIds = []): Collection
    {
        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();

        try {
            if (empty($orderIds)) {
                $connection->truncateTable($this->getTable(Helper::TABLE_NAME_EXTENDED_GRID));
            }

            $this->grabDataFromSalesOrderTable($orderIds);
            $this->grabDataFromInvoiceTable($orderIds);
            $this->grabDataFromTrackShipmentTable($orderIds);
            $this->grabDataFromShipmentTable($orderIds);
            $this->grabDataFromOrderItemsTable($orderIds);
            $this->grabDataFromOrderBillingAddressTable($orderIds);
            $this->grabDataFromOrderShippingAddressTable($orderIds);
            $this->grabDataFromSalesOrderTaxTable($orderIds);
        } catch (\Exception $exception) {
            $this->_logger->critical($exception->getMessage() . ' ' . $exception->getTraceAsString());
        }

        return $this;
    }

    /**
     * Grab data from the sales_order table
     *
     * @param array $orderIds
     * @return $this
     */
    public function grabDataFromSalesOrderTable(array $orderIds = []): Collection
    {
        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();
        $select     = $connection->select();
        $columns    = [
            'order_id'           => 'entity_id',
            'coupon_code'        => 'coupon_code',
            'weight'             => 'weight',
            'subtotal_purchased' => 'subtotal',
            'discount_amount'    => 'discount_amount',
            'base_total_paid'    => 'base_total_paid'
        ];

        $select->from(['so' => $this->getTable('sales_order')], $columns)
               ->join(
                   [
                       'sog' => $this->getTable('sales_order_grid')
                   ],
                   '`sog`.`entity_id` = `so`.`entity_id`',
                   []
               );

        if (!empty($orderIds)) {
            $select->where('`so`.`entity_id` IN (' . implode(',', $orderIds) . ')');
        }

        $columnsToInsert = array_merge(['order_id'], static::ADDITIONAL_COLUMNS);
        $query           = $connection->insertFromSelect(
            $select,
            $this->getTable(Helper::TABLE_NAME_EXTENDED_GRID),
            $columnsToInsert,
            AdapterInterface::INSERT_ON_DUPLICATE
        );

        $connection->query($query);

        $useColorGrid = true; // @TODO - Add setting
        if ($useColorGrid) {
            $connection = $this->getConnection();
            $select     = $connection->select();

            $select->from(['so' => $this->getTable('sales_order')], ['order_id' => 'entity_id'])
                   ->join(
                       [
                           'sog' => $this->getTable('sales_order_grid')
                       ],
                       '`sog`.`entity_id` = `so`.`entity_id`',
                       []
                   )
                   ->joinLeft(
                       [
                           'moadIdx' => $this->getTable('mageworx_ordersgrid_additional_data_index')
                       ],
                       'sog.payment_method = moadIdx.payment_method
                       AND
                       so.shipping_method = moadIdx.shipping_method
                       AND
                       sog.status = moadIdx.order_status
                       ',
                       []
                   )
                   ->joinLeft(
                       [
                           'moad' => $this->getTable('mageworx_ordersgrid_additional_data')
                       ],
                       'moad.entity_id = moadIdx.rule_id',
                       ['row_color' => 'row_color', 'icon_image' => 'icon_image']
                   );

            if (!empty($orderIds)) {
                $select->where('`so`.`entity_id` IN (' . implode(',', $orderIds) . ')');
            }

            $columnsToInsert = [
                'order_id',
                'row_color',
                'icon_image',
            ];

            $query = $connection->insertFromSelect(
                $select,
                $this->getTable(Helper::TABLE_NAME_EXTENDED_GRID),
                $columnsToInsert,
                AdapterInterface::INSERT_ON_DUPLICATE
            );

            $connection->query($query);
        }

        return $this;
    }

    /**
     * Grab data from the sales_invoice table
     *
     * @param array $orderIds
     * @return $this
     */
    public function grabDataFromInvoiceTable(array $orderIds = []): Collection
    {
        /** @var AdapterInterface $connection */
        $connection  = $this->getConnection();
        $sourceTable = $this->getTable('sales_invoice');
        $select      = $connection->select();
        // SELECT `order_id`, GROUP_CONCAT(DISTINCT `increment_id` SEPARATOR ', ') AS `invoices`
        // FROM `sales_invoice` GROUP BY `order_id`
        $columns = [
            'order_id' => 'order_id',
            'invoices' => new \Zend_Db_Expr('GROUP_CONCAT(DISTINCT `si`.`increment_id` SEPARATOR \',\')')
        ];

        $select->from(['si' => $sourceTable], $columns)
               ->group('order_id')
               ->join(
                   [
                       'sog' => $this->getTable('sales_order_grid')
                   ],
                   '`sog`.`entity_id` = `si`.`order_id`',
                   []
               );

        if (!empty($orderIds)) {
            $select->where('`si`.`order_id` IN (' . implode(',', $orderIds) . ')');
        }

        $columnsToInsert = array_merge(['order_id'], static::INVOICE_COLUMNS);
        $query           = $connection->insertFromSelect(
            $select,
            $this->getTable(Helper::TABLE_NAME_EXTENDED_GRID),
            $columnsToInsert,
            AdapterInterface::INSERT_ON_DUPLICATE
        );

        $connection->query($query);

        return $this;
    }

    /**
     * Grab data from the sales_shipment table
     *
     * @param array $orderIds
     * @return $this
     */
    public function grabDataFromShipmentTable(array $orderIds = []): Collection
    {
        /** @var AdapterInterface $connection */
        $connection  = $this->getConnection();
        $sourceTable = $this->getTable('sales_shipment');
        $select      = $connection->select();
        $columns     = [
            'order_id'  => 'order_id',
            'shipments' => new \Zend_Db_Expr('GROUP_CONCAT(DISTINCT `ss`.`increment_id` SEPARATOR \',\')')
        ];

        $select->from(['ss' => $sourceTable], $columns)
               ->group('order_id')
               ->join(
                   [
                       'sog' => $this->getTable('sales_order_grid')
                   ],
                   'sog.entity_id = ss.order_id',
                   []
               );

        if (!empty($orderIds)) {
            $select->where('`ss`.`order_id` IN (' . implode(',', $orderIds) . ')');
        }

        $columnsToInsert = array_merge(['order_id'], static::SHIPMENT_COLUMNS);
        $query           = $connection->insertFromSelect(
            $select,
            $this->getTable(Helper::TABLE_NAME_EXTENDED_GRID),
            $columnsToInsert,
            AdapterInterface::INSERT_ON_DUPLICATE
        );

        $connection->query($query);

        return $this;
    }

    /**
     * Grab data from the sales_shipment_track table
     *
     * @param array $orderIds
     * @return $this
     */
    public function grabDataFromTrackShipmentTable(array $orderIds = []): Collection
    {
        /** @var AdapterInterface $connection */
        $connection  = $this->getConnection();
        $sourceTable = $this->getTable('sales_shipment_track');
        $select      = $connection->select();
        $columns     = [
            'order_id'        => 'order_id',
            'tracking_number' => new \Zend_Db_Expr('GROUP_CONCAT(DISTINCT `sst`.`track_number` SEPARATOR \',\')')
        ];

        $select->from(['sst' => $sourceTable], $columns)
               ->group('order_id')
               ->join(
                   [
                       'sog' => $this->getTable('sales_order_grid')
                   ],
                   '`sog`.`entity_id` = `sst`.`order_id`',
                   []
               );

        if (!empty($orderIds)) {
            $select->where('`sst`.`order_id` IN (' . implode(',', $orderIds) . ')');
        }

        $columnsToInsert = array_merge(['order_id'], static::SHIPMENT_TRACKING_COLUMNS);
        $query           = $connection->insertFromSelect(
            $select,
            $this->getTable(Helper::TABLE_NAME_EXTENDED_GRID),
            $columnsToInsert,
            AdapterInterface::INSERT_ON_DUPLICATE
        );

        $connection->query($query);

        return $this;
    }

    /**
     * Grab data from the sales_order_item table
     *
     * @param array $orderIds
     * @return $this
     */
    public function grabDataFromOrderItemsTable(array $orderIds = []): Collection
    {
        /** @var AdapterInterface $connection */
        $connection  = $this->getConnection();
        $sourceTable = $this->getTable('sales_order_item');
        $select      = $connection->select();
        $columns     = [
            'order_id'                   => 'order_id',
            'product_name'               => new \Zend_Db_Expr('GROUP_CONCAT(DISTINCT `name` SEPARATOR \',\')'),
            'product_sku'                => new \Zend_Db_Expr('GROUP_CONCAT(DISTINCT `sku` SEPARATOR \',\')'),
            'product_type'               => new \Zend_Db_Expr('GROUP_CONCAT(DISTINCT `product_type` SEPARATOR \',\')'),
            'product_thumbnail'          => new \Zend_Db_Expr('GROUP_CONCAT(DISTINCT `product_id` SEPARATOR \',\')'),
            'product_quantity'           => new \Zend_Db_Expr('SUM(`qty_ordered`)'),
            'per_product_quantity'       => new \Zend_Db_Expr(
                'GROUP_CONCAT(`sku`, \' : \', `qty_ordered` SEPARATOR \',\')'
            ),
            'per_product_custom_options' => new \Zend_Db_Expr(
                'CONCAT(\'{\', GROUP_CONCAT(\'"\', `item_id`, \'" : \', JSON_EXTRACT(`product_options` , \'$.options\') SEPARATOR \',\'), \'}\')'
            ),
            'shipment_status'            => new \Zend_Db_Expr(
                '
                IF(
                       SUM(`qty_shipped`) > 0,
                       IF(SUM(`qty_ordered`) - SUM(`qty_canceled`) > SUM(`qty_shipped`), ' . ShippedStatus::PARTIALLY_SHIPPED . ', ' . ShippedStatus::FULLY_SHIPPED . '),
                       ' . ShippedStatus::NOT_SHIPPED . '
                )
            '
            ),
        ];
        $select->from($sourceTable, $columns)
               ->where('parent_item_id IS NULL')
               ->group('order_id')
               ->join(
                   [
                       'sog' => $this->getTable('sales_order_grid')
                   ],
                   'sog.entity_id = order_id',
                   []
               );
        if (!empty($orderIds)) {
            $select->where('order_id IN (' . implode(',', $orderIds) . ')');
        }

        $columnsToInsert = array_merge(['order_id'], static::ORDER_ITEM_COLUMNS);
        $query           = $connection->insertFromSelect(
            $select,
            $this->getTable(Helper::TABLE_NAME_EXTENDED_GRID),
            $columnsToInsert,
            AdapterInterface::INSERT_ON_DUPLICATE
        );

        $connection->query($query);

        return $this;
    }

    /**
     * Grab data from the sales_order table
     *
     * @param array $orderIds
     * @return $this
     */
    public function grabDataFromOrderBillingAddressTable(array $orderIds = []): Collection
    {
        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();
        $select     = $connection->select();
        $columns    = [
            'order_id'           => OrderAddress::PARENT_ID,
            'billing_fax'        => OrderAddress::FAX,
            'billing_region'     => OrderAddress::REGION,
            'billing_postcode'   => OrderAddress::POSTCODE,
            'billing_city'       => OrderAddress::CITY,
            'billing_telephone'  => OrderAddress::TELEPHONE,
            'billing_country_id' => OrderAddress::COUNTRY_ID,
            'billing_company'    => OrderAddress::COMPANY,
            'vat_id'             => OrderAddress::VAT_ID,
        ];

        $select->from(['soa' => $this->getTable('sales_order_address')], $columns)
               ->where('`soa`.`address_type` = "billing"')
               ->join(
                   [
                       'sog' => $this->getTable('sales_order_grid')
                   ],
                   '`sog`.`entity_id` = `soa`.`' . OrderAddress::PARENT_ID . '`',
                   []
               );
        if (!empty($orderIds)) {
            $select->where('`soa`.`' . OrderAddress::PARENT_ID . '` IN (' . implode(',', $orderIds) . ')');
        }

        $columnsToInsert = array_merge(['order_id'], static::BILLING_ADDRESS_COLUMNS);
        $query           = $connection->insertFromSelect(
            $select,
            $this->getTable(Helper::TABLE_NAME_EXTENDED_GRID),
            $columnsToInsert,
            AdapterInterface::INSERT_ON_DUPLICATE
        );

        $connection->query($query);

        return $this;
    }

    /**
     * Grab data from the sales_order table
     *
     * @param array $orderIds
     * @return $this
     */
    public function grabDataFromOrderShippingAddressTable(array $orderIds = []): Collection
    {
        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();
        $select     = $connection->select();
        $columns    = [
            'order_id'            => OrderAddress::PARENT_ID,
            'shipping_fax'        => OrderAddress::FAX,
            'shipping_region'     => OrderAddress::REGION,
            'shipping_postcode'   => OrderAddress::POSTCODE,
            'shipping_city'       => OrderAddress::CITY,
            'shipping_telephone'  => OrderAddress::TELEPHONE,
            'shipping_country_id' => OrderAddress::COUNTRY_ID,
            'shipping_company'    => OrderAddress::COMPANY,
        ];

        $select->from(['soa' => $this->getTable('sales_order_address')], $columns)
               ->where('`soa`.`address_type` = "shipping"')
               ->join(
                   [
                       'sog' => $this->getTable('sales_order_grid')
                   ],
                   '`sog`.`entity_id` = `soa`.`' . OrderAddress::PARENT_ID . '`',
                   []
               );

        if (!empty($orderIds)) {
            $select->where('`soa`.`' . OrderAddress::PARENT_ID . '` IN (' . implode(',', $orderIds) . ')');
        }

        $columnsToInsert = array_merge(['order_id'], static::SHIPPING_ADDRESS_COLUMNS);
        $query           = $connection->insertFromSelect(
            $select,
            $this->getTable(Helper::TABLE_NAME_EXTENDED_GRID),
            $columnsToInsert,
            AdapterInterface::INSERT_ON_DUPLICATE
        );

        $connection->query($query);

        return $this;
    }

    /**
     * Grab data from the sales_order_tax table
     *
     * @param array $orderIds
     * @return $this
     */
    public function grabDataFromSalesOrderTaxTable(array $orderIds = []): Collection
    {
        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();

        if (!empty($orderIds)) {
            $connection->update(
                $this->getTable(
                    Helper::TABLE_NAME_EXTENDED_GRID
                ),
                [
                    'applied_tax_code'             => null,
                    'applied_tax_percent'          => null,
                    'applied_tax_amount'           => null,
                    'applied_tax_base_amount'      => null,
                    'applied_tax_base_real_amount' => null
                ],
                '`order_id` IN (' . implode(',', $orderIds) . ')'
            );
        }

        $select        = $connection->select();
        $taxExpression = sprintf(
            'GROUP_CONCAT(DISTINCT CONCAT(`code`, \'%s\', `percent`) SEPARATOR \'%s\')',
            self::TAX_PERCENT_DELIMITER,
            self::TAX_RATE_DELIMITER
        );
        $columns       = [
            'order_id'                     => 'order_id',
            'applied_tax_code'             => new \Zend_Db_Expr($taxExpression),
            'applied_tax_amount'           => new \Zend_Db_Expr('SUM(`amount`)'),
            'applied_tax_base_amount'      => new \Zend_Db_Expr('SUM(`base_amount`)'),
            'applied_tax_base_real_amount' => new \Zend_Db_Expr('SUM(`base_real_amount`)'),
        ];
        $select->from(['sot' => $this->getTable('sales_order_tax')], $columns)
               ->join(
                   [
                       'sog' => $this->getTable('sales_order_grid')
                   ],
                   '`sog`.`entity_id` = `sot`.`order_id`',
                   []
               );

        if (!empty($orderIds)) {
            $select->where('`sot`.`order_id` IN (' . implode(',', $orderIds) . ')');
        }

        $select->group('sot.order_id');

        $columnsToInsert = array_merge(['order_id'], static::TAX_COLUMNS);
        $query           = $connection->insertFromSelect(
            $select,
            $this->getTable(Helper::TABLE_NAME_EXTENDED_GRID),
            $columnsToInsert,
            AdapterInterface::INSERT_ON_DUPLICATE
        );

        $connection->query($query);

        return $this;
    }

    /**
     * Delete data from table
     *
     * @param array $orderIds
     * @return $this
     */
    public function deleteData(array $orderIds = []): Collection
    {
        /** @var AdapterInterface $connection */
        $connection = $this->getConnection();
        if (empty($orderIds)) {
            $connection->truncateTable($this->getTable(Helper::TABLE_NAME_EXTENDED_GRID));

            return $this;
        }

        $select = $connection->select();
        $select->from($this->getTable(Helper::TABLE_NAME_EXTENDED_GRID));
        if (!empty($orderIds)) {
            if (is_string($orderIds)) {
                $orderIds = [$orderIds];
            }
            $select->where('`order_id` IN (' . implode(',', $orderIds) . ')');
        }
        $query = $select->deleteFromSelect($this->getTable(Helper::TABLE_NAME_EXTENDED_GRID));
        $connection->query($query);

        return $this;
    }
}
