<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Controller\Adminhtml\GridAdditionalData;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageWorx\OrdersGrid\Api\GridAdditionalDataRepositoryInterface as EntityRepository;
use MageWorx\OrdersGrid\Api\Data\GridAdditionalDataInterface as EntityModelInterface;

abstract class AbstractAction extends Action
{
    /**
     * ACL Resource Key
     */
    const ADMIN_RESOURCE = 'MageWorx_OrdersGrid::grid_additional_data';

    const MENU_ID = 'MageWorx_OrdersGrid::grid_additional_data';

    /**
     * @var EntityRepository
     */
    protected $repository;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @param Context $context
     * @param EntityRepository $repository
     */
    public function __construct(
        Context                  $context,
        EntityRepository         $repository,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->repository = $repository;
        $this->logger     = $logger;
        parent::__construct($context);
    }

    /**
     * Init entity using params from request
     *
     * @return EntityModelInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function initModel(): EntityModelInterface
    {
        $id = $this->detectEntityId();
        /** @var EntityModelInterface $model */
        if ($id) {
            $model = $this->repository->getById($id);
        } else {
            $model = $this->repository->getEmptyEntity();
        }

        return $model;
    }

    /**
     * Detect entity id from request
     * Returns null when no id found
     *
     * @return int|null
     */
    protected function detectEntityId(): ?int
    {
        $id = $this->getRequest()->getParam(EntityModelInterface::KEY_ID);

        if ($id !== null) {
            $id = (int)$id;
        }

        return $id;
    }

    /**
     * Redirect back to grid (or dashboard) when something goes wrong
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    protected function redirectWhenNoModel(): ResultInterface
    {
        $this->messageManager->addErrorMessage(__('Requested model no longer exists.'));
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($this->getRequest()->getActionName() !== 'index') {
            $resultRedirect->setPath('mageworx_ordersgrid/gridAdditionalData/index');
        } else {
            $resultRedirect->setPath('');
        }

        return $resultRedirect;
    }
}
