<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Api\Data;


interface QuoteDataBackupInterface
{
    const ID              = 'entity_id';
    const QUOTE_ID        = 'quote_id';
    const ORDER_ID        = 'order_id';
    const DATA_SERIALIZED = 'data_serialized';
    const UPDATED_AT      = 'updated_at';
    const CREATED_AT      = 'created_at';

    /**
     * @return int|null
     */
    public function getId();

    /**
     * @return int|null
     */
    public function getQuoteId(): ?int;

    /**
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * Serialized quote data backup in JSON format.
     * Empty string is possible value.
     *
     * @return string
     */
    public function getDataSerialized(): string;

    /**
     * @return \DateTimeInterface
     */
    public function getUpdatedAt(): \DateTimeInterface;

    /**
     * @return \DateTimeInterface
     */
    public function getCreatedAt(): \DateTimeInterface;

    /**
     * @param int $value
     * @return QuoteDataBackupInterface
     */
    public function setQuoteId(int $value): QuoteDataBackupInterface;


    /**
     * @param int $value
     * @return QuoteDataBackupInterface
     */
    public function setOrderId(int $value): QuoteDataBackupInterface;

    /**
     * Quote backup data in JSON format
     *
     * @param string $jsonString
     * @return QuoteDataBackupInterface
     */
    public function setDataSerialized(string $jsonString): QuoteDataBackupInterface;
}
