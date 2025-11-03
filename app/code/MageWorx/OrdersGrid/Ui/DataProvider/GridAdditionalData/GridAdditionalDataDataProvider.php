<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Ui\DataProvider\GridAdditionalData;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Magento\Ui\DataProvider\AddFieldToCollectionInterface;
use Magento\Ui\DataProvider\AddFilterToCollectionInterface;
use MageWorx\OrdersGrid\Helper\Image as ImageHelper;
use MageWorx\OrdersGrid\Model\GridAdditionalData as GridAdditionalDataModel;
use MageWorx\OrdersGrid\Model\ResourceModel\GridAdditionalData\Grid\CollectionFactory;
use MageWorx\OrdersGrid\Api\Data\GridAdditionalDataInterface;

class GridAdditionalDataDataProvider extends AbstractDataProvider
{
    const LISTING_NAME = 'grid_additional_data_listing_data_source';

    /**
     * Main Collection
     *
     * @var \MageWorx\OrdersGrid\Model\ResourceModel\GridAdditionalData\Grid\Collection
     */
    protected $collection;

    /**
     * @var \Magento\Ui\DataProvider\AddFieldToCollectionInterface[]
     */
    protected $addFieldStrategies;

    /**
     * @var \Magento\Ui\DataProvider\AddFilterToCollectionInterface[]
     */
    protected $addFilterStrategies;

    /**
     * @var ImageHelper
     */
    protected $imageHelper;

    /**
     * Construct
     *
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param ImageHelper $imageHelper
     * @param AddFieldToCollectionInterface $addFieldStrategies
     * @param AddFilterToCollectionInterface $addFilterStrategies
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string            $name,
        string            $primaryFieldName,
        string            $requestFieldName,
        CollectionFactory $collectionFactory,
        ImageHelper       $imageHelper,
        array             $addFieldStrategies = [],
        array             $addFilterStrategies = [],
        array             $meta = [],
        array             $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection          = $collectionFactory->create();
        $this->addFieldStrategies  = $addFieldStrategies;
        $this->addFilterStrategies = $addFilterStrategies;
        $this->imageHelper         = $imageHelper;
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData(): array
    {
        if (!$this->getCollection()->isLoaded()) {
            $this->getCollection()
                 ->load();
        }
        $items = $this->getCollection()->toArray();
        if (empty($items['items'])) {
            return [
                'totalRecords' => 0,
                'items'        => []
            ];
        }

        $indexedItems = [];
        $size         = $this->getCollection()->getSize();
        if ($this->getName() == static::LISTING_NAME) {
            $indexedItems = $items['items'];
        } else {
            foreach ($items['items'] as $item) {
                if (empty($item[GridAdditionalDataInterface::KEY_ID])) {
                    $size--;
                    continue;
                }
                $this->prepareImage($item);
                $item['id_field_name']            = GridAdditionalDataInterface::KEY_ID;
                $indexedItems[$item['entity_id']] = $item;
            }
        }

        $data = [
            'totalRecords' => $size,
            'items'        => $indexedItems,
        ];

        return $data;
    }

    /**
     * @param array $item
     * @return void
     */
    protected function prepareImage(array &$item): void
    {
        if (!empty($item['icon_image'])) {
            try {
                $image[0]['name']        = $item['icon_image'];
                $image[0]['type']        = 'image';
                $image[0]['url']         = $this->imageHelper->getMediaUrl($item['icon_image']);
                $image[0]['size']        = $this->imageHelper->getImageOrigSize($item['icon_image']);
                $item['icon_image_data'] = $image;
            } catch (NoSuchEntityException $noSuchEntityException) {
                $item['icon_image_data'] = [];
            }
        }
    }

    /**
     * Add field to select
     *
     * @param string|array $field
     * @param string|null $alias
     * @return void
     */
    public function addField($field, $alias = null)
    {
        if (isset($this->addFieldStrategies[$field])) {
            $this->addFieldStrategies[$field]->addField($this->getCollection(), $field, $alias);
        } else {
            parent::addField($field, $alias);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addFilter(\Magento\Framework\Api\Filter $filter)
    {
        if (isset($this->addFilterStrategies[$filter->getField()])) {
            $this->addFilterStrategies[$filter->getField()]
                ->addFilter(
                    $this->getCollection(),
                    $filter->getField(),
                    [$filter->getConditionType() => $filter->getValue()]
                );
        } else {
            parent::addFilter($filter);
        }
    }
}
