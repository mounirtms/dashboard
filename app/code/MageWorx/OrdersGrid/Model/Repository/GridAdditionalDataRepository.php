<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Model\Repository;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use MageWorx\OrdersGrid\Api\Data\GridAdditionalDataInterface as Entity;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use MageWorx\OrdersGrid\Model\GridAdditionalData as EntityModel;
use MageWorx\OrdersGrid\Model\GridAdditionalDataFactory as EntityFactory;
use MageWorx\OrdersGrid\Model\ResourceModel\GridAdditionalData as EntityResource;
use MageWorx\OrdersGrid\Model\ResourceModel\GridAdditionalData\CollectionFactory as EntityCollectionFactory;
use MageWorx\OrdersGrid\Model\ResourceModel\GridAdditionalData\Collection as EntityCollection;
use MageWorx\OrdersGrid\Api\GridAdditionalDataRepositoryInterface as RepositoryInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;

class GridAdditionalDataRepository implements RepositoryInterface
{
    /**
     * @var EntityResource
     */
    protected $resource;

    /**
     * @var EntityFactory
     */
    protected $entityFactory;

    /**
     * @var EntityCollectionFactory
     */
    protected $entityCollectionFactory;

    /**
     * @var SearchResultsInterfaceFactory
     */
    protected $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    protected $collectionProcessor;

    /**
     * @param EntityResource $resource
     * @param EntityFactory $entityFactory
     * @param EntityCollectionFactory $entityCollectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        EntityResource                $resource,
        EntityFactory                 $entityFactory,
        EntityCollectionFactory       $entityCollectionFactory,
        SearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface  $collectionProcessor
    ) {
        $this->resource                = $resource;
        $this->entityFactory           = $entityFactory;
        $this->entityCollectionFactory = $entityCollectionFactory;
        $this->searchResultsFactory    = $searchResultsFactory;
        $this->collectionProcessor     = $collectionProcessor;
    }

    /**
     * @inheritDoc
     */
    public function save(Entity $entity): Entity
    {
        try {
            if (!$entity instanceof AbstractModel) {
                throw new LocalizedException(__('Entity must be instance of \Magento\Framework\Model\AbstractModel'));
            }
            /** @var Entity|EntityModel $entity */
            $this->resource->save($entity);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __(
                    'Could not save the entity: %1',
                    $exception->getMessage()
                )
            );
        }

        return $entity;
    }

    /**
     * @inheritDoc
     */
    public function getById($entityId): Entity
    {
        /** @var Entity|EntityModel $entity */
        $entity = $this->entityFactory->create();
        $this->resource->load($entity, $entityId);
        if (!$entity->getId()) {
            throw new NoSuchEntityException(__('Entity with id "%1" does not exist.', $entityId));
        }

        return $entity;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        /** @var SearchResultsInterface|EntityCollection $searchResult */
        $searchResult = $this->searchResultsFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);
        $this->collectionProcessor->process($searchCriteria, $searchResult);

        return $searchResult;
    }

    /**
     * @inheritDoc
     */
    public function delete(Entity $entity): void
    {
        try {
            $this->resource->delete($entity);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __(
                    'Could not delete the entity: %1',
                    $exception->getMessage()
                )
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $entityId): void
    {
        $this->delete($this->getById($entityId));
    }

    /**
     * @inheritDoc
     */
    public function getEmptyEntity(): Entity
    {
        /** @var Entity|EntityModel $entity */
        $entity = $this->entityFactory->create();

        return $entity;
    }
}
