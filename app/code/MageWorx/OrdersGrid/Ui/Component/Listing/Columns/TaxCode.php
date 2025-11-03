<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Ui\Component\Listing\Columns;

use Magento\Ui\Component\Listing\Columns\Column;
use MageWorx\OrdersGrid\Model\ResourceModel\Order\Grid\Collection as OrderGridCollection;

class TaxCode extends Column
{
    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $rates = [];
                if (!$item[$this->getData('name')]) {
                    continue;
                }

                $taxRatesWithPercent = explode(OrderGridCollection::TAX_RATE_DELIMITER, $item[$this->getData('name')]);
                foreach ($taxRatesWithPercent as $taxRateWithPercent) {
                    $parts = explode(OrderGridCollection::TAX_PERCENT_DELIMITER, $taxRateWithPercent);
                    if (empty($parts)) {
                        continue;
                    }

                    if (isset($parts[0]) && isset($parts[1])) {
                        $rates[] = $parts[0] . ' (' . floatval($parts[1]) . '%)';
                    } else {
                        $rates[] = $parts[0];
                    }
                }
                $item[$this->getData('name')] = implode(',', $rates);
            }
        }

        return $dataSource;
    }
}
