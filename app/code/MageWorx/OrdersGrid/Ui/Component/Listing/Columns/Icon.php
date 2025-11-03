<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Ui\Component\Listing\Columns;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use MageWorx\OrdersGrid\Helper\Image as ImageHelper;

class Icon extends Column
{
    const NAME = 'icon_image';

    /**
     * @var ImageHelper
     */
    protected $imageHelper;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param ImageHelper $imageHelper
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface   $context,
        UiComponentFactory $uiComponentFactory,
        ImageHelper        $imageHelper,
        array              $components = [],
        array              $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->imageHelper = $imageHelper;
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

        $fieldName = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            if (!$item[$fieldName]) {
                continue;
            }

            try {
                $item[$fieldName] = $this->imageHelper->getImageUrl($item[$fieldName]);
            } catch (NoSuchEntityException $noSuchEntityException) {
                continue;
            }
        }

        return $dataSource;
    }
}
