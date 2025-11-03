<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use MageWorx\OrderEditor\Api\Data\QuoteDataBackupInterface as Entity;
use Magento\Framework\Exception\CouldNotSaveException;

interface QuoteDataBackupRepositoryInterface
{
    /**
     * Save entity.
     *
     * @param Entity $entity
     * @return Entity
     * @throws LocalizedException
     * @throws CouldNotSaveException
     */
    public function save(Entity $entity): Entity;

    /**
     * Retrieve entity by Entity ID.
     *
     * @param int $entityId
     * @return Entity
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $entityId): Entity;

    /**
     * Retrieve entities matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return \Magento\Framework\Api\SearchResultsInterface
     * @throws LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria): \Magento\Framework\Api\SearchResultsInterface;

    /**
     * Delete entity.
     *
     * @param Entity $entity
     * @return bool true on success
     * @throws LocalizedException
     */
    public function delete(Entity $entity): bool;

    /**
     * Delete entity by Entity ID.
     *
     * @param int $entityId
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws LocalizedException
     */
    public function deleteById(int $entityId): bool;

    /**
     * Get empty entity
     *
     * @return Entity
     */
    public function getEmptyEntity(): Entity;

    /**
     * Retrieve entity by linked Quote ID.
     *
     * @param int $quoteId
     * @return Entity
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByQuoteId(int $quoteId): Entity;
}
