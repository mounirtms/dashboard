<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Controller\Adminhtml\GridAdditionalData;

use Magento\Framework\Exception\LocalizedException;

class Delete extends AbstractAction
{
    /**
     * @inheritDoc
     */
    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('entity_id');
        if ($id) {
            try {
                $this->repository->deleteById($id);
                $this->messageManager->addSuccessMessage(__('You deleted the data.'));
                $this->_redirect('*/*/');

                return;
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(
                    __('We can\'t delete the data right now. Please review the log and try again.')
                );
                $this->logger->critical($e);
                $this->_redirect('*/*/edit', ['entity_id' => $this->getRequest()->getParam('entity_id')]);

                return;
            }
        }
        $this->messageManager->addErrorMessage(__('We can\'t find a data to delete.'));
        $this->_redirect('*/*/');
    }
}
