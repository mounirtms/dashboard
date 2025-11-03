<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Controller\Adminhtml\Edit;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;

class UpdateComment extends Action
{
    const ADMIN_RESOURCE = 'MageWorx_OrderEditor::edit_comments';

    /**
     * @var OrderStatusHistoryRepositoryInterface
     */
    protected $orderStatusHistoryRepository;

    public function __construct(
        Context                               $context,
        OrderStatusHistoryRepositoryInterface $orderStatusHistoryRepository
    ) {
        parent::__construct($context);
        $this->orderStatusHistoryRepository = $orderStatusHistoryRepository;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if (!isset($params['isAjax']) || (bool)$params['isAjax'] !== true) {
            $result->setData(['error' => true, 'message' => __('Incorrect request')]);

            return $result;
        }

        try {
            $commentId   = $params['comment_id'] ?? null;
            $commentText = $params['comment_text'] ?? null;
            if (!$commentId || !$commentText) {
                throw new LocalizedException(__('Incorrect request params.'));
            }
            $comment = $this->orderStatusHistoryRepository->get($commentId);
            $comment->setComment($commentText);
            $this->orderStatusHistoryRepository->save($comment);

            $result->setData(['ok' => true, 'requestParams' => $params, 'newComment' => $comment->getComment()]);

            return $result;
        } catch (LocalizedException $localizedException) {
            $result->setData(['error' => true, 'message' => $localizedException->getMessage()]);

            return $result;
        }
    }
}
