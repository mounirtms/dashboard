<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Plugin\Comment;

use Magento\Backend\App\Action as AppAction;

/**
 * Add admin name to the regular comment on creditmemo create (add comment with create creditmemo action)
 */
class AddAdminNameToCommentOnCreditmemoCreate extends AddAdminNameToCommentAbstract
{
    /**
     * @param AppAction $subject
     * @return array
     */
    public function beforeExecute(
        AppAction $subject
    ): array {
        $request = $subject->getRequest();
        $data    = $request->getPost('creditmemo');

        if ($this->helper->isNeedToAddAdminNameToLog() && !empty($data['comment_text'])) {
            if (!empty($data['comment_text']) && is_string($data['comment_text'])) {
                $data['comment_text'] .= $this->getCommentAuthorPart();

                $request->setPostValue('creditmemo', $data);
            }
        }

        return [];
    }
}
