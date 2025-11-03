<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Plugin\Block\Sales\Adminhtml\Order\View;

use Magento\Backend\Block\Template;

class Items
{
    /**
     * @param Template $originalBlock
     * @param array $result
     * @return array
     */
    public function afterGetColumns(Template $originalBlock, array $result): array
    {
        return ['thumbnail' => __("Thumbnail")] + $result;
    }
}
