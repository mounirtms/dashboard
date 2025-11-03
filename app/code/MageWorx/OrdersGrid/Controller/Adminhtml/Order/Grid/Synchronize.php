<?php
/**
 * Copyright © 2017 MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersGrid\Controller\Adminhtml\Order\Grid;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Ui\Component\MassAction\Filter;
use MageWorx\OrdersGrid\Model\ResourceModel\Order\Grid\CollectionFactory as OrderGridCustomCollectionFactory;

/**
 * Class Synchronize
 *
 * Synchronize selected orders mass-action (orders grid)
 */
class Synchronize extends Action
{
    /**
     * @var OrderGridCustomCollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var string
     */
    protected $redirectUrl = 'sales/order/index';

    /**
     * @var OrderCollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * Synchronize constructor.
     *
     * @param Context $context
     * @param OrderGridCustomCollectionFactory $collectionFactory
     * @param Filter $filter
     * @param OrderCollectionFactory $orderCollectionFactory
     */
    public function __construct(
        Context $context,
        OrderGridCustomCollectionFactory $collectionFactory,
        Filter $filter,
        OrderCollectionFactory $orderCollectionFactory
    ) {
        parent::__construct($context);
        $this->collectionFactory      = $collectionFactory;
        $this->filter                 = $filter;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * Sync orders data with our table
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        try {
            $orderCollection = $this->filter->getCollection($this->orderCollectionFactory->create());
            $orderIds        = $orderCollection->getAllIds();

            /** @var \MageWorx\OrdersGrid\Model\ResourceModel\Order\Grid\Collection $collection */
            $collection = $this->collectionFactory->create();
            $collection->syncOrdersData($orderIds);

            $this->messageManager->addSuccessMessage(__('Synchronization completed successfully'));
        } catch (\Exception $e) {
            /** @var \Magento\Framework\Controller\Result\Json $resultJson */
            $this->messageManager->addErrorMessage($e->getMessage());
        } finally {
            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultFactory
                ->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setPath($this->redirectUrl);

            return $resultRedirect;
        }
    }

    /**
     * Returns result of current user permission check on resource and privilege
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('MageWorx_OrdersGrid::sync');
    }
}
