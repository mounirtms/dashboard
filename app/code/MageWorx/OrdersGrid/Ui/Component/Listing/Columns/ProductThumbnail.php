<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Ui\Component\Listing\Columns;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ProductThumbnail extends Column
{
    const NAME = 'product_thumbnail';

    /**
     * @var \Magento\Catalog\Helper\Image
     */
    protected $imageHelper;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaInterface
     */
    protected $searchCriteria;

    /**
     * @var \Magento\Framework\Api\Search\FilterGroupBuilder
     */
    protected $filterGroupBuilder;

    /**
     * @var \Magento\Framework\Api\FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param \Magento\Catalog\Helper\Image $imageHelper
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Framework\Api\SearchCriteriaInterface $criteria
     * @param \Magento\Framework\Api\Search\FilterGroupBuilder $filterGroupBuilder
     * @param \Magento\Framework\Api\FilterBuilder $filterBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Framework\Api\SearchCriteriaInterface $criteria,
        \Magento\Framework\Api\Search\FilterGroupBuilder $filterGroupBuilder,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->imageHelper        = $imageHelper;
        $this->productRepository  = $productRepository;
        $this->searchCriteria     = $criteria;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->filterBuilder      = $filterBuilder;
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!$dataSource) {
            return $dataSource;
        }

        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $fieldName  = $this->getData('name');
        $productIds = [];
        foreach ($dataSource['data']['items'] as & $item) {
            if (empty($item[$fieldName])) {
                continue;
            }

            $productIds = array_merge($productIds, $this->getItemIds($item, $fieldName));
        }

        if (empty($productIds)) {
            return $dataSource;
        }

        $productIds = array_unique($productIds);
        /** @var \Magento\Framework\Api\Search\FilterGroup $idsFilter */
        $idsFilter = $this->filterGroupBuilder->create();
        $idsFilter->setFilters(
            [
                $this->filterBuilder
                    ->setField('entity_id')
                    ->setConditionType('in')
                    ->setValue($productIds)
                    ->create(),
            ]
        );
        $this->searchCriteria->setFilterGroups([$idsFilter]);
        $products = $this->productRepository->getList($this->searchCriteria);
        /** @var \Magento\Catalog\Api\Data\ProductInterface[] $productItems */
        $productItems = $products->getItems();

        if (empty($productItems)) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as & $item) {
            if (!$item[$fieldName]) {
                continue;
            }

            $productIdsCurrent = $this->getItemIds($item, $fieldName);

            foreach ($productIdsCurrent as $currentProductId) {
                if (empty($productItems[$currentProductId])) {
                    continue;
                }
                /** @var \Magento\Catalog\Model\Product $productItems [$currentProductId] */
                $imageHelper = $this->imageHelper->init(
                    $productItems[$currentProductId],
                    'product_listing_thumbnail'
                );

                $url                                                  = $imageHelper->getUrl();
                $item['product_thumbnails'][$currentProductId]['src'] = $url;
                $item['product_thumbnails'][$currentProductId]['alt'] = $imageHelper->getLabel();
                $item['product_thumbnails'][$currentProductId]['pid'] = $currentProductId;
            }
        }

        return $dataSource;
    }

    /**
     * @param array $dataSourceItem
     * @param string $fieldName
     * @return array
     */
    protected function getItemIds(array $dataSourceItem, string $fieldName): array
    {
        return explode(',', $dataSourceItem[$fieldName]) ?? [];
    }
}
