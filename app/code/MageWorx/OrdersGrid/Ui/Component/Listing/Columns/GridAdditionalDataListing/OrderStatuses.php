<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersGrid\Ui\Component\Listing\Columns\GridAdditionalDataListing;

use Magento\Ui\Component\Listing\Columns\Column;
use MageWorx\OrdersGrid\Api\Data\GridAdditionalDataInterface;

class OrderStatuses extends Column
{
    /**
     * Prepare component configuration
     *
     * @return void
     */
    public function prepare(): void
    {
        parent::prepare();
        $config = $this->getData('config');
        $config['options'][] = [
            'label' => __('Any Order Status'),
            'value' => ''
        ];
        $this->setData('config', $config);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $item[$this->getData('name')] = $this->prepareItem($item);
            }
        }

        return $dataSource ?? [];
    }

    /**
     * Get data
     *
     * @param array $item
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function prepareItem(array $item): string
    {
        if (!empty($item[GridAdditionalDataInterface::KEY_ORDER_STATUSES])) {
            $content = $item[GridAdditionalDataInterface::KEY_ORDER_STATUSES];
        } else {
            $content = '';
        }

        return $content;
    }
}
