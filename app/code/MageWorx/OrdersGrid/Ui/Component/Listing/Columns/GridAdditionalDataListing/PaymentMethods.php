<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersGrid\Ui\Component\Listing\Columns\GridAdditionalDataListing;

use Magento\Ui\Component\Listing\Columns\Column;
use MageWorx\OrdersGrid\Api\Data\GridAdditionalDataInterface;

class PaymentMethods extends Column
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
            'label' => __('All Payment Methods'),
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
        if (!empty($item[GridAdditionalDataInterface::KEY_PAYMENT_METHODS])) {
            $content = $item[GridAdditionalDataInterface::KEY_PAYMENT_METHODS];
        } else {
            $content = '';
        }

        return $content;
    }
}
