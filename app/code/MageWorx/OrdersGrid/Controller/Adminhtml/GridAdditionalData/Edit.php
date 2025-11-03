<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersGrid\Controller\Adminhtml\GridAdditionalData;

use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class Edit extends AbstractAction
{
    /**
     * Execute action based on request and return result
     *
     * Note: Request will be added as operation argument in future
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     */
    public function execute()
    {
        try {
            $entity = $this->initModel();
        } catch (LocalizedException|NoSuchEntityException $e) {
            return $this->redirectWhenNoModel();
        }

        /** @var \Magento\Backend\Model\View\Result\Page|\Magento\Framework\View\Result\Page $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $id     = $this->detectEntityId();

        if ($id && !$entity->getId()) {
            return $this->redirectWhenNoModel();
        }

        $title = $entity->getId() ? $entity->getName() : __('New Entity');
        $result->getConfig()->getTitle()->prepend($title);
        $data = $this->_session->getPageData();

        if (!empty($data)) {
            $entity->addData($data);
        }

        return $result;
    }
}
