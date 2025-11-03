<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Controller\Adminhtml\GridAdditionalData;

class NewAction extends AbstractAction
{
    /**
     * @inheritDoc
     */
    public function execute()
    {
        $this->_forward('edit');
    }
}
