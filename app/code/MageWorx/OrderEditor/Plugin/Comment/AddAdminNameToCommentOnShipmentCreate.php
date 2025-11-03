<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Plugin\Comment;

use Magento\Backend\App\Action as AppAction;

/**
 * Add admin name to the regular comment on shipment create (add comment with create shipment action)
 */
class AddAdminNameToCommentOnShipmentCreate extends AddAdminNameToCommentAbstract
{
    /**
     * @param AppAction $subject
     * @return array
     */
    public function beforeExecute(
        AppAction $subject
    ): array {
        $request = $subject->getRequest();
        $data    = $request->getPost('shipment');

        if ($this->helper->isNeedToAddAdminNameToLog() && !empty($data['comment_text'])) {
            if (is_string($data['comment_text'])) {
                $data['comment_text'] .= $this->getCommentAuthorPart();

                $request->setPostValue('shipment', $data);
            }
        }

        return [];
    }
}
