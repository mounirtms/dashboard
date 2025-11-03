<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api;

use Magento\Framework\Exception\LocalizedException;

/**
 * Interface RestoreQuoteInterface
 *
 * Restores quote to initial state, including quote items, shipping&billing address,
 * taxes etc.
 *
 * Will use the temporary storage of the previous quote versions.
 */
interface RestoreQuoteInterface
{
    const TABLE_NAME = 'mageworx_order_editor_quote_data';

    /**
     * Restores the quote to a previous state.
     * Allows to restore the quote and corresponding entities from backup on any stage.
     *
     * @param \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote $quote
     * @throws LocalizedException
     */
    public function restore(\Magento\Quote\Api\Data\CartInterface $quote): void;

    /**
     * Start order editing from this method:
     * - backup initial quote state in additional table
     * - set up the "edit" flags in original tables
     *
     * When used before editing allow to restore the quote and corresponding entities from backup on any stage.
     *
     * @param \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote $quote
     * @throws LocalizedException
     */
    public function backupInitialQuoteState(\Magento\Quote\Api\Data\CartInterface $quote): void;
}
