<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-07-24T12:25:45+00:00
 * File:          Model/ResourceModel/PdfTemplate.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Model\ResourceModel;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PdfTemplate extends AbstractDb
{

    /**
     * Store manager
     *
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param DateTime $dateTime
     * @param EntityManager $entityManager
     * @param MetadataPool $metadataPool
     * @param string $connectionName
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        DateTime $dateTime,
        EntityManager $entityManager,
        MetadataPool $metadataPool,
        $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
        $this->storeManager = $storeManager;
        $this->dateTime = $dateTime;
        $this->entityManager = $entityManager;
        $this->metadataPool = $metadataPool;
    }

    /**
     * Init resource model
     * @return void
     */
    public function _construct()
    {
        $this->_init('xtento_pdf_templates', 'template_id');
    }

    /**
     * Perform operations after object load
     *
     * @param AbstractModel $object
     * @return \Magento\Framework\Model\ResourceModel\Db\AbstractDb
     */
    public function _afterLoad(AbstractModel $object)
    {
        if ($object->getId()) {
            $stores = $this->lookupStoreIds($object->getId());
            $object->setData('store_id', $stores);
        }

        return parent::_afterLoad($object);
    }

    /**
     * Assign $template to store views
     *
     * @param AbstractModel | \Magento\Framework\Model\AbstractModel $object
     * @return \Magento\Framework\Model\ResourceModel\Db\AbstractDb
     */
    public function _afterSave(AbstractModel $object)
    {
        $this->saveStoreRelation($object);
        return parent::_afterSave($object);
    }

    /**
     * Get store ids to which specified item is assigned
     *
     * @param int $templateId
     * @return array
     */
    public function lookupStoreIds($templateId)
    {
        $adapter = $this->getConnection();
        $select = $adapter->select()->from(
            $this->getTable('xtento_pdf_store'),
            'store_id'
        )->where(
            'template_id = ?',
            (int)$templateId
        );

        return $adapter->fetchCol($select);
    }

    /**
     * @param AbstractModel $template
     * @return $this
     */
    public function saveStoreRelation(AbstractModel $template)
    {
        $oldStores = $this->lookupStoreIds($template->getId());
        $newStores = (array)$template->getStoreId();
        if (empty($newStores)) {
            $newStores = (array)$template->getStoreId();
        }
        foreach ($newStores as $key => $newStore) {
            try {
                $this->storeManager->getStore($newStore);
            } catch (NoSuchEntityException $e) {
                // Store does not exist
                unset($newStores[$key]);
            }
        }
        $table = $this->getTable('xtento_pdf_store');
        $insert = array_diff($newStores, $oldStores);
        $delete = array_diff($oldStores, $newStores);

        if ($delete) {
            $where = [
                'template_id = ?' => (int)$template->getId(),
                'store_id IN (?)' => $delete
            ];
            $this->getConnection()->delete($table, $where);
        }

        if ($insert) {
            $data = [];
            foreach ($insert as $storeId) {
                $data[] = [
                    'template_id' => (int)$template->getId(),
                    'store_id' => (int)$storeId
                ];
            }

            $this->getConnection()->insertMultiple($table, $data);
        }

        return $this;
    }
}
