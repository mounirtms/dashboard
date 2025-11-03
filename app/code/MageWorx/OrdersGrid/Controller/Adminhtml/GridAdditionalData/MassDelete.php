<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersGrid\Controller\Adminhtml\GridAdditionalData;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use MageWorx\OrdersGrid\Api\GridAdditionalDataRepositoryInterface;
use MageWorx\OrdersGrid\Model\ResourceModel\GridAdditionalData\CollectionFactory;

class MassDelete extends Action
{
    const ADMIN_RESOURCE = 'MageWorx_OrdersGrid::grid_additional_data';

    /**
     * @var Filter
     */
    private $filter;

    /**
     * @var string
     */
    private $redirectUrl = '*/*/index';

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var GridAdditionalDataRepositoryInterface
     */
    private $repository;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param GridAdditionalDataRepositoryInterface $repository
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        GridAdditionalDataRepositoryInterface $repository
    ) {
        parent::__construct($context);
        $this->filter            = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->repository        = $repository;
    }

    /**
     * Update is active status
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        try {
            $collection   = $this->filter->getCollection($this->collectionFactory->create());
            $deletedCount = 0;
            foreach ($collection->getAllIds() as $entityId) {
                $this->repository->deleteById($entityId);
                $deletedCount++;
            }

            if ($deletedCount) {
                $this->messageManager->addSuccessMessage(__('A total of %1 record(s) were deleted.', $deletedCount));
            }

            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory
                ->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setPath($this->redirectUrl);

            return $resultRedirect;
        } catch (\Exception $e) {
            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $this->messageManager->addErrorMessage($e->getMessage());
            $resultRedirect = $this->resultFactory
                ->create(ResultFactory::TYPE_REDIRECT);

            return $resultRedirect->setPath($this->redirectUrl);
        }
    }
}
