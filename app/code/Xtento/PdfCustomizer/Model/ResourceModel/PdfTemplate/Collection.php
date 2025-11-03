<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2020-06-07T19:40:48+00:00
 * File:          Model/ResourceModel/PdfTemplate/Collection.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Model\ResourceModel\PdfTemplate;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Collection extends AbstractCollection
{
    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * Collection constructor.
     *
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param StoreManagerInterface $storeManager
     * @param MetadataPool $metadataPool
     * @param AdapterInterface|null $connection
     * @param AbstractDb|null $resource
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        StoreManagerInterface $storeManager,
        MetadataPool $metadataPool,
        AdapterInterface $connection = null,
        AbstractDb $resource = null
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $storeManager, $metadataPool, $connection, $resource);
        $this->_storeManager = $storeManager;
    }


    /**
     * @var string
     */
    //@codingStandardsIgnoreLine
    protected $_idFieldName = 'template_id';

    /**
     * Init resource model
     * @return void
     */
    public function _construct()
    {
        $this->_init(
            'Xtento\PdfCustomizer\Model\PdfTemplate',
            'Xtento\PdfCustomizer\Model\ResourceModel\PdfTemplate'
        );

        $this->_map['fields']['template_id'] = 'main_table.template_id';
        $this->_map['fields']['store'] = 'store_table.store_id';
    }

    /**
     * Add filter by store
     *
     * @param int|array|\Magento\Store\Model\Store $store
     * @param bool $withAdmin
     * @return $this
     */
    public function addStoreFilter($store, $withAdmin = true)
    {
        if ($this->_storeManager->isSingleStoreMode()) {
            return $this;
        }
        if (!$this->getFlag('store_filter_added')) {
            $this->performAddStoreFilter($store, $withAdmin);
        }

        return $this;
    }

    /**
     * Perform operations after collection load
     *
     * @return \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
     */
    public function _afterLoad()
    {
        $this->performAfterLoad('xtento_pdf_store', 'template_id');

        return parent::_afterLoad();
    }

    /**
     * Perform operations before rendering filters
     *
     * @return void
     */
    public function _renderFiltersBefore()
    {
        $this->joinStoreRelationTable('xtento_pdf_store', 'template_id');
    }
}
