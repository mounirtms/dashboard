<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageWorx\OrdersGrid\Api\Data\GridAdditionalDataInterface as Entity;
use MageWorx\OrdersGrid\Model\GridAdditionalData as EntityModel;

interface GridAdditionalDataRepositoryInterface
{
    const ENTITY_DATA_INTERFACE = Entity::class;

    /**
     * Save entity.
     *
     * @param Entity $entity
     * @return Entity|EntityModel
     * @throws LocalizedException
     */
    public function save(Entity $entity): Entity;

    /**
     * Retrieve entity.
     *
     * @param int $entityId
     * @return Entity|EntityModel
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getById($entityId): Entity;

    /**
     * Retrieve entities matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * Delete entity.
     *
     * @param Entity|EntityModel $entity
     * @return void
     * @throws LocalizedException
     */
    public function delete(Entity $entity): void;

    /**
     * Delete entity by ID.
     *
     * @param int $entityId
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function deleteById(int $entityId): void;

    /**
     * Get empty Entity
     *
     * @return Entity|EntityModel
     */
    public function getEmptyEntity(): Entity;
}
