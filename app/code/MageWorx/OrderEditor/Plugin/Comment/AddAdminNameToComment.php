<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Plugin\Comment;

use Magento\Backend\App\Action as AppAction;

/**
 * Add admin name to the regular comment of invoice, creditmemo (add comment to existing entity)
 */
class AddAdminNameToComment extends AddAdminNameToCommentAbstract
{
    /**
     * @param AppAction $subject
     * @return array
     */
    public function beforeExecute(
        AppAction $subject
    ): array {
        $request = $subject->getRequest();
        $data    = $request->getPost('comment');

        if ($this->helper->isNeedToAddAdminNameToLog()) {
            if (!empty($data['comment']) && is_string($data['comment'])) {
                $data['comment'] .= $this->getCommentAuthorPart();

                $request->setPostValue('comment', $data);
            }
        }

        return [];
    }
}
