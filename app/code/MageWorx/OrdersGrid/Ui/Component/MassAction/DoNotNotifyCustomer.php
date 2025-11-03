<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Ui\Component\MassAction;

class DoNotNotifyCustomer extends OptionsAbstract implements \JsonSerializable
{
    const SEND_EMAIL = 0;

    /**
     * Get options
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        if (empty($this->options)) {
            $this->prepareOptionsData();

            /**
             * Capture
             * Invoice
             * Invoice → Print
             * Ship
             * Ship → Print
             * Invoice → Capture
             * Invoice → Capture → Ship
             * Invoice → Capture → Ship → Print
             */
            $this->getMatchingOptions();
            $this->filterOptions();
            $this->options = array_values($this->options);
        }

        return $this->options;
    }

    /**
     * @return string
     */
    protected function getUrlPath(): string
    {
        return 'mageworx_ordersgrid/order_grid/massShipInvoiceCapture';
    }
}
