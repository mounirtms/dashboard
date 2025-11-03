<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Controller\Adminhtml\Form;

use Magento\Backend\Model\View\Result\Redirect as ResultRedirect;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use MageWorx\OrderEditor\Api\OrderRepositoryInterface;
use MageWorx\OrderEditor\Model\MsiStatusManager;
use MageWorx\OrderEditor\Model\Edit\Quote as OrderEditorQuote;
use MageWorx\OrderEditor\Model\InventoryDetectionStatusManager;
use MageWorx\OrderEditor\Model\Order;
use MageWorx\OrderEditor\Model\Quote\Item;
use Magento\Framework\View\Result\PageFactory;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Sales\Controller\Adminhtml\Order\Create;
use MageWorx\OrderEditor\Helper\Data as Helper;

/**
 * Class Options
 */
class Options extends Create
{
    /**
     * @var OrderEditorQuote $quote
     */
    protected $quote;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var DataObjectFactory
     */
    protected $dataObjectFactory;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var InventoryDetectionStatusManager
     */
    protected $inventoryDetectionStatusManager;

    /**
     * @var MsiStatusManager
     */
    protected $msiStatusManager;

    /**
     * Options constructor.
     *
     * @param Action\Context $context
     * @param ProductHelper $productHelper
     * @param Escaper $escaper
     * @param PageFactory $resultPageFactory
     * @param ForwardFactory $resultForwardFactory
     * @param OrderEditorQuote $quote
     * @param Helper $helper
     * @param DataObjectFactory $dataObjectFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param InventoryDetectionStatusManager $inventoryDetectionStatusManager
     * @param MsiStatusManager $msiStatusManager
     */
    public function __construct(
        Action\Context $context,
        ProductHelper $productHelper,
        Escaper $escaper,
        PageFactory $resultPageFactory,
        ForwardFactory $resultForwardFactory,
        OrderEditorQuote $quote,
        Helper $helper,
        DataObjectFactory $dataObjectFactory,
        OrderRepositoryInterface $orderRepository,
        InventoryDetectionStatusManager $inventoryDetectionStatusManager,
        MsiStatusManager $msiStatusManager
    ) {
        $this->quote                           = $quote;
        $this->helper                          = $helper;
        $this->dataObjectFactory               = $dataObjectFactory;
        $this->orderRepository                 = $orderRepository;
        $this->inventoryDetectionStatusManager = $inventoryDetectionStatusManager;
        $this->msiStatusManager                = $msiStatusManager;

        parent::__construct(
            $context,
            $productHelper,
            $escaper,
            $resultPageFactory,
            $resultForwardFactory
        );
    }

    /**
     * @return ResultRedirect
     */
    public function execute(): ResultRedirect
    {
        $updateResult = $this->dataObjectFactory->create();

        try {
            $orderItemId = $this->getRequest()->getParam('id');
            $params      = $this->getRequest()->getParams();

            $this->msiStatusManager->disableMSI();
            $this->inventoryDetectionStatusManager->disableInventoryDetection();

            $prefixIdLength = strlen(Item::PREFIX_ID);
            if (substr($orderItemId, 0, $prefixIdLength) == Item::PREFIX_ID) {
                $quoteItemId = substr(
                    $orderItemId,
                    $prefixIdLength,
                    strlen($orderItemId)
                );
                $orderItem   = $this->quote->getUpdatedOrderItem($quoteItemId, $params);
            } else {
                $orderItem = $this->quote->createNewOrderItem($orderItemId, $params);
                $orderItem->setId($orderItemId);
            }

            $this->inventoryDetectionStatusManager->enableInventoryDetection();
            $this->msiStatusManager->enableMSI();

            $resultPage = $this->resultPageFactory->create();
            /** @var \Mageworx\OrderEditor\Block\Adminhtml\Sales\Order\Edit\Form\Items\Options $optionsBlock */
            $optionsBlock = $resultPage->getLayout()
                                       ->getBlock('ordereditor_order_edit_form_items_options');
            if (!empty($optionsBlock)) {
                $optionsHtml = $optionsBlock
                    ->setOrderItem($orderItem)
                    ->toHtml();

                $updateResult->setOptionsHtml($optionsHtml);
            }

            $productOptions = $orderItem->getData('product_options');
            $options        = $this->helper->encodeBuyRequestValue($productOptions);

            $updateResult->setProductOptions($options);

            $orderId = $this->getRequest()->getParam('order_id');
            if (!$orderId) {
                throw new LocalizedException(__('Missed parameter: "%1"', 'order_id'));
            }
            /** @var Order $order */
            $order = $this->orderRepository->getById($orderId);

            $rate  = $order->getBaseCurrency()->getAnyRate($order->getOrderCurrency());
            $price = $orderItem->getData('base_price') * $rate;

            $updateResult->setPrice($price);
            $updateResult->setName($orderItem->getData('name'));
            $updateResult->setSku($orderItem->getData('sku'));
            $updateResult->setItemId($orderItemId);

            // Add new products (from APO)
            $newItemsBlockHtml = $this->getNewItemsBlockHtml($order);
            $updateResult->setNewItemsHtml($newItemsBlockHtml);

            $updateResult->setOk(true);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $updateResult->setError(true);
            $updateResult->setMessage($errorMessage);
        }

        $jsVarName = $this->getRequest()->getParam('as_js_varname');
        $updateResult->setJsVarName($jsVarName);

        $this->_session->setCompositeProductResult($updateResult);

        /** @var ResultRedirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory
            ->create()
            ->setPath('catalog/product/showUpdateResult');

        return $resultRedirect;
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function getNewItemsBlockHtml(Order $order)
    {
        $resultPage = $this->resultPageFactory->create();

        $formContainer = $resultPage->getLayout()
                                    ->getBlock('ordereditor_order_items_form_container');
        if (empty($formContainer)) {
            $message = __('Can not load block');
            throw new LocalizedException($message);
        }

        $orderItems = $this->getNewOrderItems($order);

        $formContainer->setOrder($order);
        $formContainer->setNewOrderItems($orderItems);

        return $formContainer->toHtml();
    }

    /**
     * @return \MageWorx\OrderEditor\Model\Order\Item[]
     * @throws NoSuchEntityException
     */
    protected function getNewOrderItems(Order $order): array
    {
        return $this->helper->getNewOrderItems();
    }
}
