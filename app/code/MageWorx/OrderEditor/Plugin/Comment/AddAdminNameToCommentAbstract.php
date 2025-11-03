<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Plugin\Comment;

use Magento\Backend\Model\Auth\Session as AuthSession;
use MageWorx\OrderEditor\Helper\Data as Helper;

/**
 * Add admin name to the regular comment of order, invoice, creditmemo
 */
class AddAdminNameToCommentAbstract
{
    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var AuthSession
     */
    protected $authSession;

    /**
     * @param Helper $helper
     */
    public function __construct(
        Helper      $helper,
        AuthSession $authSession
    ) {
        $this->helper      = $helper;
        $this->authSession = $authSession;
    }

    /**
     * @return string
     */
    protected function getCommentAuthorPart(): string
    {
        return '<br/>' . PHP_EOL . '<i>' . __(
            'Made by %1',
            $this->getAdminName()
        ) . '</i><br/>' . PHP_EOL;
    }

    /**
     * @return string
     */
    protected function getAdminName(): string
    {
        $admin = $this->authSession->getUser();
        if (!$admin) {
            return 'Unknown admin';
        }

        $name = $admin->getFirstName() . ' ' . $admin->getLastName();
        if (!$name) {
            $name = $admin->getUserName();
        }

        return $name ?? 'No username';
    }
}
