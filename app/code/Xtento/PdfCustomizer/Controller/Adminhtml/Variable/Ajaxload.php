<?php

/**
 * Product:       Xtento_PdfCustomizer
 * ID:            %!uniqueid!%
 * Last Modified: 2019-02-05T17:13:45+00:00
 * File:          Controller/Adminhtml/Variable/Ajaxload.php
 * Copyright:     Copyright (c) XTENTO GmbH & Co. KG <info@xtento.com> / All rights reserved.
 */

namespace Xtento\PdfCustomizer\Controller\Adminhtml\Variable;

use Xtento\PdfCustomizer\Model\Source\TemplateType;
use Magento\Catalog\Api\ProductRepositoryInterface;

/**
 * Class Ajaxload
 * @package Xtento\PdfCustomizer\Controller\Adminhtml\Variable
 */
class Ajaxload extends Template
{

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|null|object
     */
    public function execute()
    {
        $this->_initTemplate();

        $templateType = $this->getRequest()->getParam('template_type');
        if (!$templateType) {
            return null;
        }

        $templateTypeName = TemplateType::TYPES[$templateType];

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();

        $collection = $this->collection($templateTypeName);

        if (empty($collection)) {
            return $resultJson->setData([]);
        }

        $incrementIdList = [];
        if (is_object($collection)) {
            $data = $collection->getData();
            foreach ($data as $incrementId) {
                $incrementIdList[] = $incrementId['increment_id'];
            }
        } else {
            foreach ($collection as $product) {
                $incrementIdList[] = $product->getId();
            }
        }

        if ($this->getRequest()->getParam('get_one', false)) {
            $result = $resultJson->setData($incrementIdList[0]);
        } else {
            $result = $resultJson->setData([$incrementIdList]);
        }

        return $this->addResponse($result);
    }

    /**
     * @param $templateTypeName
     * @return bool|mixed
     */
    public function collection($templateTypeName)
    {
        if ($templateTypeName == 'product') {
            return $this->productCollection();
        }

        $this->criteriaBuilder->addFilters(
            [
                $this->filterBuilder
                    ->setField('increment_id')
                    ->setValue($this->getRequest()->getParam('variables_entity_id'))
                    ->setConditionType('neq')
                    ->create()
            ]
        );
        $creationReverseOrder = $this->_objectManager->create('\Magento\Framework\Api\SortOrderBuilder')->setField('created_at')
            ->setDescendingDirection()
            ->create();
        $this->criteriaBuilder->addSortOrder($creationReverseOrder);
        $this->criteriaBuilder->setPageSize(5);
        $this->criteriaBuilder->setCurrentPage(1);
        $searchCriteria = $this->criteriaBuilder->create();

        //@codingStandardsIgnoreLine
        $collection = $this->_objectManager->create(
            'Magento\Sales\Api\\' .
            ucfirst($templateTypeName) .
            'RepositoryInterface'
        )->getList($searchCriteria);

        if (!$collection->count()) {
            return false;
        }

        return $collection;
    }

    /**
     * @return []
     */
    public function productCollection()
    {
        $this->criteriaBuilder->addFilters(
            [
                $this->filterBuilder
                    ->setField('entity_id')
                    ->setValue(11)
                    ->setConditionType('lt')
                    ->create()
            ]
        );
        $creationReverseOrder = $this->_objectManager->create('\Magento\Framework\Api\SortOrderBuilder')->setField('created_at')
            ->setDescendingDirection()
            ->create();
        $this->criteriaBuilder->addSortOrder($creationReverseOrder);
        $this->criteriaBuilder->setPageSize(5);
        $this->criteriaBuilder->setCurrentPage(1);
        $searchCriteria = $this->criteriaBuilder->create();

        //@codingStandardsIgnoreLine
        $collection = $this->_objectManager->create(
            ProductRepositoryInterface::class
        )->getList($searchCriteria);

        return $collection->getItems();
    }
}
