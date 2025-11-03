<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Plugin\Comment;

use Magento\Backend\App\Action as AppAction;
use Magento\Sales\Model\Order;

/**
 * Add admin name to the regular comment of order(add comment to existing entity)
 */
class AddAdminNameToCommentOrder extends AddAdminNameToCommentAbstract
{
    /**
     * @var string
     */
    protected $originalCommentWithTags = '';

    /**
     * @param AppAction $subject
     * @return array
     */
    public function beforeExecute(
        AppAction $subject
    ): array {
        $request = $subject->getRequest();
        $data    = $request->getPost('history');

        if ($this->helper->isNeedToAddAdminNameToLog()) {
            if (!empty($data['comment']) && is_string($data['comment'])) {
                $data['comment'] .= $this->getCommentAuthorPart();

                // Save comment with tags for later use
                $this->saveOriginalComment($data['comment']);

                $request->setPostValue('history', $data);
            }
        }

        return [];
    }

    /**
     * @param Order $subject
     * @param ...$arguments
     * @return array
     */
    public function beforeAddStatusHistoryComment(
        Order $subject,
        ...$arguments
    ): array {
        $comment = &$arguments[0];

        if ($this->sameAsOriginal((string)$comment)) {
            $comment = $this->getSavedCommentWithTags();
        }

        return $arguments;
    }

    /**
     * @param string $comment
     * @return void
     */
    protected function saveOriginalComment(string $comment): void
    {
        $this->originalCommentWithTags = $comment;
    }

    /**
     * @return string
     */
    public function getSavedCommentWithTags(): string
    {
        return $this->originalCommentWithTags;
    }

    /**
     * @return string
     */
    protected function getCommentWithStrippedTags(): string
    {
        $comment = $this->getSavedCommentWithTags();

        return trim(strip_tags($comment));
    }

    /**
     * Compare comment with saved one after same manipulation (trim & strip_tags).
     * Need to make sure we are still working with the same comment.
     *
     * @param string $comment
     * @return bool
     */
    protected function sameAsOriginal(string $comment): bool
    {
        $strippedSavedOne = $this->getCommentWithStrippedTags();

        return $comment === $strippedSavedOne;
    }
}
