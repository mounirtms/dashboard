<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Test\Unit\Model\RestoreQuoteClass;

/**
 * Class BackupEmptyQuoteTest
 *
 * When we call backup method for an empty quote exception must be thrown.
 */
class BackupEmptyQuoteNegativeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \MageWorx\OrderEditor\Api\RestoreQuoteInterface
     */
    private $restoreQuoteModel;

    /**
     * @var \Magento\Quote\Api\Data\CartInterface
     */
    private $quoteMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $quoteDataBackupRepositoryMock;

    /**
     * @inheritdoc
     */
    public function setUp(): void
    {
        $objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);

        $this->quoteDataBackupRepositoryMock = $this->getMockBuilder(
            \MageWorx\OrderEditor\Api\QuoteDataBackupRepositoryInterface::class
        )
                                                    ->disableOriginalConstructor()
                                                    ->getMock();

        $this->restoreQuoteModel = $objectManager->getObject(
            \MageWorx\OrderEditor\Model\RestoreQuote::class,
            [
                'quoteDataBackupRepository' => $this->quoteDataBackupRepositoryMock
            ]
        );

        $this->quoteMock = $this->getMockBuilder(
            \Magento\Quote\Model\Quote::class
        )
                                ->disableOriginalConstructor()
                                ->getMock();
    }

    /**
     * Backup Quote must throw exception for empty quote.
     * The save method of repository must never be called.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testBackupEmptyQuote()
    {
        $this->quoteMock->expects($this->atLeastOnce())->method('getId')->willReturn(
            null
        );
        $this->quoteDataBackupRepositoryMock->expects($this->never())->method('save');
        $this->expectExceptionMessage((string)__('Unable to backup quote: empty quote id'));
        $this->restoreQuoteModel->backupInitialQuoteState($this->quoteMock);
    }
}
