<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Plugin;

/**
 * Replace regular array rates to data-objects with array access, to prevent fatal error in template:
 * vendor/magento/module-sales/view/adminhtml/templates/order/totals/tax.phtml
 * (call getId on array when creditmemo has refunded tax but without any item)
 */
class TaxHelper
{
    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @param \Magento\Framework\DataObjectFactory $dataObjectFactory
     */
    public function __construct(
        \Magento\Framework\DataObjectFactory $dataObjectFactory
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * @param \Magento\Sales\Block\Adminhtml\Order\Totals\Tax $subject
     * @param array $result
     * @return array
     */
    public function afterGetFullTaxInfo(\Magento\Sales\Block\Adminhtml\Order\Totals\Tax $subject, array $result): array
    {
        foreach ($result as &$item) {
            if (empty($item['rates'])) {
                continue;
            }

            foreach ($item['rates'] as $key => $rate) {
                if (is_object($rate)) {
                    continue; // already object
                }

                $rateAsObjectWithArrayAccess = $this->dataObjectFactory->create(['data' => $rate]);
                if (!$rateAsObjectWithArrayAccess->getId()) {
                    $rateAsObjectWithArrayAccess->setData('id', $key);
                }
                $item['rates'][$key] = $rateAsObjectWithArrayAccess;
            }
        }

        return $result;
    }
}
