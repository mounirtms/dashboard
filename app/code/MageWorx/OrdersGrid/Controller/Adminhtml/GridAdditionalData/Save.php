<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Controller\Adminhtml\GridAdditionalData;

use Magento\Framework\Exception\LocalizedException;

class Save extends AbstractAction
{
    /**
     * @inheritDoc
     */
    public function execute()
    {
        if (!$this->getRequest()->getPostValue()) {
            $this->_redirect('*/*/');
        }

        try {
            $data = $this->getRequest()->getParam('general');
            $id   = $data['entity_id'] ?? false;
            /** @var $model \MageWorx\OrdersGrid\Api\Data\GridAdditionalDataInterface|\MageWorx\OrdersGrid\Model\GridAdditionalData */
            if ($id) {
                $model = $this->repository->getById($id);
            } else {
                $model = $this->repository->getEmptyEntity();
            }

            $this->_eventManager->dispatch(
                'adminhtml_controller_ordersgrid_gridadditionaldata_prepare_save',
                ['request' => $this->getRequest()]
            );

            $data = $this->prepareData($data);
            $model->addData($data);
            $this->_session->setPageData($model->getData());

            $this->repository->save($model);
            $this->messageManager->addSuccessMessage(__('You saved the data.'));
            $this->_session->setPageData(false);
            if ($this->getRequest()->getParam('back') == 'newAction') {
                $this->_redirect('*/*/newAction');

                return;
            }
            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', ['entity_id' => $model->getId()]);

                return;
            }
            $this->_redirect('*/*/');

            return;
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $id = (int)$this->getRequest()->getParam('entity_id');
            if (!empty($id)) {
                $this->_redirect('*/*/edit', ['id' => $id]);
            } else {
                $this->_redirect('*/*/newAction');
            }

            return;
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Something went wrong while saving the data. Please review the error log.')
            );
            $this->logger->critical($e);
            $data = !empty($data) ? $data : [];
            $this->_session->setPageData($data);
            $this->_redirect('*/*/edit', ['entity_id' => $this->getRequest()->getParam('entity_id')]);

            return;
        }
    }

    /**
     * Prepares specific data
     *
     * @param array $data
     * @return array
     */
    protected function prepareData(array $data): array
    {
        if (!empty($data['icon_image_data'][0]['file'])) {
            $data['icon_image'] = $data['icon_image_data'][0]['file'];
        } elseif (!empty($data['icon_image_data'][0]['path'])) {
            $data['icon_image'] = $data['icon_image_data'][0]['path'];
        } elseif (empty($data['icon_image_data'][0])) {
            $data['icon_image'] = null;
        } else {
            unset($data['icon_image']);
        }

        if (empty($data['entity_id'])) {
            unset($data['entity_id']); // Clear object id to mark object as new
        }

        return $data;
    }
}
