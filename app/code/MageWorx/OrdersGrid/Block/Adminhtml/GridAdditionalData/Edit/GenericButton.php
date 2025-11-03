<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersGrid\Block\Adminhtml\GridAdditionalData\Edit;

use Magento\Backend\Block\Widget\Context;
use MageWorx\OrdersGrid\Api\GridAdditionalDataRepositoryInterface as RepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class GenericButton
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var RepositoryInterface
     */
    protected $repository;

    /**
     * @param Context $context
     * @param RepositoryInterface $repository
     */
    public function __construct(
        Context             $context,
        RepositoryInterface $repository
    ) {
        $this->context    = $context;
        $this->repository = $repository;
    }

    /**
     * Return entity ID
     *
     * @return int|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getEntityId(): ?int
    {
        try {
            return $this->repository->getById(
                $this->context->getRequest()->getParam('entity_id')
            )->getId();
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Generate url by route and parameters
     *
     * @param string|null $route
     * @param array|null $params
     * @return  string
     */
    public function getUrl(?string $route = '', ?array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
