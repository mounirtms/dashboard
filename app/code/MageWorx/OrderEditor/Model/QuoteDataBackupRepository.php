<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Model;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\EntityManager\Operation\Read\ReadExtensions;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageWorx\OrderEditor\Api\Data\QuoteDataBackupInterface as Entity;
use MageWorx\OrderEditor\Api\Data\QuoteDataBackupInterfaceFactory as EntityFactory;
use MageWorx\OrderEditor\Model\ResourceModel\QuoteDataBackup as EntityResource;
use MageWorx\OrderEditor\Model\ResourceModel\QuoteDataBackup\Collection as EntityCollection;
use MageWorx\OrderEditor\Model\ResourceModel\QuoteDataBackup\CollectionFactory as EntityCollectionFactory;
use MageWorx\OrderEditor\Api\QuoteDataBackupRepositoryInterface as RepositoryInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface as ExtensionAttributeJoinProcessor;

class QuoteDataBackupRepository implements RepositoryInterface
{
    /**
     * @var EntityFactory
     */
    private $entityFactory;

    /**
     * @var EntityResource
     */
    private $entityResource;

    /**
     * @var SearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @var EntityCollectionFactory
     */
    private $collectionFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @var ExtensionAttributeJoinProcessor
     */
    private $extensionAttributesJoinProcessor;

    /**
     * @var ReadExtensions
     */
    private $readExtensions;

    /**
     * QuoteDataBackupRepository constructor.
     *
     * @param EntityFactory $entityFactory
     * @param EntityResource $entityResource
     * @param EntityCollectionFactory $collectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param ExtensionAttributeJoinProcessor $extensionAttributesJoinProcessor
     * @param ReadExtensions $readExtensions
     */
    public function __construct(
        EntityFactory $entityFactory,
        EntityResource $entityResource,
        EntityCollectionFactory $collectionFactory,
        SearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor,
        ExtensionAttributeJoinProcessor $extensionAttributesJoinProcessor,
        ReadExtensions $readExtensions
    ) {
        $this->entityFactory                    = $entityFactory;
        $this->entityResource                   = $entityResource;
        $this->collectionFactory                = $collectionFactory;
        $this->searchResultsFactory             = $searchResultsFactory;
        $this->collectionProcessor              = $collectionProcessor;
        $this->extensionAttributesJoinProcessor = $extensionAttributesJoinProcessor;
        $this->readExtensions                   = $readExtensions;
    }

    /**
     * @inheritDoc
     */
    public function save(Entity $entity): Entity
    {
        if (!$entity instanceof \Magento\Framework\Model\AbstractModel) {
            $message = __(
                'Instance of %1 expected, got: %2',
                '\Magento\Framework\Model\AbstractModel',
                get_class($entity)
            );
            throw new LocalizedException(
                $message
            );
        }

        try {
            $this->entityResource->save($entity);
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
    public function getById(int $entityId): Entity
    {
        /** @var Entity $entity */
        $entity = $this->entityFactory->create();
        if (!$entity instanceof \Magento\Framework\Model\AbstractModel) {
            $message = __(
                'Instance of %1 expected, got: %2',
                '\Magento\Framework\Model\AbstractModel',
                get_class($entity)
            );
            throw new LocalizedException(
                $message
            );
        }

        $this->entityResource->load($entity, $entityId);
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
        /** @var EntityCollection $collection */
        $collection = $this->collectionFactory->create();
        $this->extensionAttributesJoinProcessor->process($collection);

        $this->collectionProcessor->process($searchCriteria, $collection);

        $collection->load();

        $this->addExtensionAttributes($collection);

        /** @var SearchResultsInterface $searchResults */
        $searchResult = $this->searchResultsFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);
        $searchResult->setItems($collection->getItems());
        $searchResult->setTotalCount($collection->getSize());

        return $searchResult;
    }

    /**
     * @inheritDoc
     */
    public function delete(Entity $entity): bool
    {
        if (!$entity instanceof \Magento\Framework\Model\AbstractModel) {
            $message = __(
                'Instance of %1 expected, got: %2',
                '\Magento\Framework\Model\AbstractModel',
                get_class($entity)
            );
            throw new LocalizedException(
                $message
            );
        }

        try {
            $this->entityResource->delete($entity);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __(
                    'Could not delete the entity: %1',
                    $exception->getMessage()
                )
            );
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $entityId): bool
    {
        return $this->delete($this->getById($entityId));
    }

    /**
     * @inheritDoc
     */
    public function getEmptyEntity(): Entity
    {
        return $this->entityFactory->create();
    }

    /**
     * @inheritDoc
     */
    public function getByQuoteId(int $quoteId): Entity
    {
        /** @var Entity $entity */
        $entity = $this->entityFactory->create();
        if (!$entity instanceof \Magento\Framework\Model\AbstractModel) {
            $message = __(
                'Instance of %1 expected, got: %2',
                '\Magento\Framework\Model\AbstractModel',
                get_class($entity)
            );
            throw new LocalizedException(
                $message
            );
        }

        $this->entityResource->load($entity, $quoteId, Entity::QUOTE_ID);
        if (!$entity->getId()) {
            throw new NoSuchEntityException(__('Entity for quote id "%1" does not exist.', $quoteId));
        }

        return $entity;
    }

    /**
     * Add extension attributes to loaded items.
     *
     * @param EntityCollection $collection
     * @return EntityCollection
     */
    private function addExtensionAttributes(EntityCollection $collection): EntityCollection
    {
        foreach ($collection->getItems() as $item) {
            $this->readExtensions->execute($item);
        }

        return $collection;
    }
}
