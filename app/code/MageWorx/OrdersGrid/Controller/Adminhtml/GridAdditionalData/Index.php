<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Controller\Adminhtml\GridAdditionalData;

use Magento\Framework\Controller\ResultFactory;

class Index extends AbstractAction
{
    /**
     * @inheritDoc
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('MageWorx_OrdersGrid::grid_additional_data');
        $resultPage->getConfig()->getTitle()->prepend(__('Order Marks'));

        return $resultPage;
    }
}
