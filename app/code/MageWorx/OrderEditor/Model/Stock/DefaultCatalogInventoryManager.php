<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Model\Stock;

use MageWorx\OrderEditor\Api\StockManagerInterface;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Magento\CatalogInventory\Api\StockManagementInterface;
use Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;

class DefaultCatalogInventoryManager implements StockManagerInterface
{
    /**
     * @var StockRegistryProviderInterface
     */
    protected $stockRegistryProvider;

    /**
     * @var StockManagementInterface
     */
    protected $stockManagement;

    /**
     * @var StockItemRepositoryInterface
     */
    protected $stockItemRepository;

    /**
     * @var MessageManagerInterface
     */
    protected $messageManager;

    /**
     * DefaultCatalogInventoryManager constructor.
     *
     * @param StockRegistryProviderInterface $stockRegistryProvider
     * @param StockManagementInterface $stockManagement
     * @param StockItemRepositoryInterface $stockItemRepository
     * @param MessageManagerInterface $messageManager
     */
    public function __construct(
        StockRegistryProviderInterface $stockRegistryProvider,
        StockManagementInterface $stockManagement,
        StockItemRepositoryInterface $stockItemRepository,
        MessageManagerInterface $messageManager
    ) {
        $this->stockRegistryProvider = $stockRegistryProvider;
        $this->stockManagement       = $stockManagement;
        $this->stockItemRepository   = $stockItemRepository;
        $this->messageManager        = $messageManager;
    }

    /**
     * @inheritDoc
     *
     * @ex \MageWorx\OrderEditor\Order\Item::productFromInventory()
     */
    public function registerSale(\Magento\Sales\Api\Data\OrderItemInterface $item, float $qty): void
    {
        $productId = (int)$item->getProductId();
        $websiteId = (int)$item->getStore()->getWebsiteId();
        $stockItem = $this->stockRegistryProvider
            ->getStockItem($productId, $websiteId);

        if ($stockItem->getManageStock()) {
            $stockItem->setQty($stockItem->getQty() - $qty);

            try {
                $this->stockItemRepository->save($stockItem);
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(
                    __('Error while removing product from inventory: %1', $e->getMessage())
                );
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function registerReturn(\Magento\Sales\Api\Data\OrderItemInterface $item, float $qty): void
    {
        $productId = (int)$item->getProductId();
        $websiteId = (int)$item->getStore()->getWebsiteId();
        $this->registerReturnByProductId($productId, $qty, $websiteId);
    }

    /**
     * @inheritDoc
     */
    public function registerReturnByProductId(int $productId, float $qty, int $websiteId): void
    {
        $this->stockManagement->backItemQty($productId, $qty, $websiteId);
    }
}
