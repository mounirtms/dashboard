<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersGrid\Controller\Adminhtml\GridAdditionalData;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Ui\Component\MassAction\Filter;
use MageWorx\OrdersGrid\Api\Data\GridAdditionalDataInterface;
use MageWorx\OrdersGrid\Api\GridAdditionalDataRepositoryInterface;
use MageWorx\OrdersGrid\Model\ResourceModel\GridAdditionalData\CollectionFactory;

class MassStatus extends Action
{
    const ADMIN_RESOURCE = 'MageWorx_OrdersGrid::grid_additional_data';

    const STATUS_ENABLED  = '1';
    const STATUS_DISABLED = '0';

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
     * @var string
     */
    private $activeFieldName;

    /**
     * @var string
     */
    private $activeRequestParamName;

    /**
     * @var \MageWorx\DeliveryDate\Api\Repository\DeliveryOptionRepositoryInterface
     */
    private $repository;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param GridAdditionalDataRepositoryInterface $repository
     * @param string $activeFieldName
     * @param string $activeRequestParamName
     */
    public function __construct(
        Context                               $context,
        Filter                                $filter,
        CollectionFactory                     $collectionFactory,
        GridAdditionalDataRepositoryInterface $repository,
        string                                $activeFieldName = 'is_active',
        string                                $activeRequestParamName = 'is_active'
    ) {
        parent::__construct($context);
        $this->filter                 = $filter;
        $this->collectionFactory      = $collectionFactory;
        $this->repository             = $repository;
        $this->activeFieldName        = $activeFieldName;
        $this->activeRequestParamName = $activeRequestParamName;
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
            $updatedCount = 0;

            switch ($this->getRequest()->getParam($this->activeRequestParamName)) {
                case static::STATUS_ENABLED:
                    $active = 1;
                    break;
                case static::STATUS_DISABLED:
                    $active = 0;
                    break;
                default:
                    $active = 1;
            }

            foreach ($collection->getAllIds() as $entityId) {
                try {
                    /** @var AbstractModel|GridAdditionalDataInterface $entity */
                    $entity = $this->repository->getById($entityId);
                    $entity->setData($this->activeFieldName, $active);
                    $this->repository->save($entity);
                    $updatedCount++;
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage($e->getMessage());
                    continue;
                }
            }

            if ($updatedCount) {
                $this->messageManager->addSuccessMessage(__('A total of %1 record(s) were updated.', $updatedCount));
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
