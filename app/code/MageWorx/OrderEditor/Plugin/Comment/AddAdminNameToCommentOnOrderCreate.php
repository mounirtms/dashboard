<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Plugin\Comment;

use Magento\Backend\App\Action as AppAction;

/**
 * Add admin name to the regular comment on order create (add comment with create order action)
 */
class AddAdminNameToCommentOnOrderCreate extends AddAdminNameToCommentAbstract
{
    /**
     * @param AppAction $subject
     * @return array
     */
    public function beforeExecute(
        AppAction $subject
    ): array {
        $request = $subject->getRequest();
        $data    = $request->getPost('order');

        if ($this->helper->isNeedToAddAdminNameToLog() && !empty($data['comment'])) {
            if (is_string($data['comment'])) {
                $data['comment'] .= $this->getCommentAuthorPart();
            } elseif (!empty($data['comment']['customer_note']) && is_string($data['comment']['customer_note'])) {
                $data['comment']['customer_note'] .= $this->getCommentAuthorPart();
            }

            $request->setPostValue('order', $data);
        }

        return [];
    }
}
