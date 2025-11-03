<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrderEditor\Model;

use Magento\Framework\Model\AbstractExtensibleModel;
use MageWorx\OrderEditor\Api\Data\QuoteDataBackupInterface;

class QuoteDataBackup extends AbstractExtensibleModel implements QuoteDataBackupInterface
{
    /**
     * Set resource model and Id field name
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init(\MageWorx\OrderEditor\Model\ResourceModel\QuoteDataBackup::class);
        $this->setIdFieldName(QuoteDataBackupInterface::ID);
    }

    /**
     * @inheritDoc
     */
    public function getQuoteId(): ?int
    {
        return $this->getData(static::QUOTE_ID);
    }

    /**
     * @inheritDoc
     */
    public function getOrderId(): ?int
    {
        return (int)$this->getData(static::ORDER_ID);
    }

    /**
     * @inheritDoc
     */
    public function getDataSerialized(): string
    {
        return (string)$this->getData(static::DATA_SERIALIZED);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->getData(static::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->getData(static::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setQuoteId(int $value): QuoteDataBackupInterface
    {
        return $this->setData(static::QUOTE_ID, $value);
    }

    /**
     * @inheritDoc
     */
    public function setOrderId(int $value): QuoteDataBackupInterface
    {
        return $this->setData(static::ORDER_ID, $value);
    }

    /**
     * @inheritDoc
     */
    public function setDataSerialized(string $jsonString): QuoteDataBackupInterface
    {
        return $this->setData(static::DATA_SERIALIZED, $jsonString);
    }
}
