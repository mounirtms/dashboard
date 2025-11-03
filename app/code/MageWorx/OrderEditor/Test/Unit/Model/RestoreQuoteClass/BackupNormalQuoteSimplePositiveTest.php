<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Test\Unit\Model\RestoreQuoteClass;

/**
 * Class BackupNormalQuoteSimplePositiveTest
 *
 * Backup method for a normal quote must not throw any exception.
 * + Necessary methods call check.
 */
class BackupNormalQuoteSimplePositiveTest extends \PHPUnit\Framework\TestCase
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
     * @var object
     */
    private $quoteDataBackupModel;

    /**
     * @var object
     */
    private $serializer;

    /**
     * @inheritdoc
     */
    public function setUp(): void
    {
        $objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);

        $this->quoteDataBackupRepositoryMock = $this->getMockBuilder(
            \MageWorx\OrderEditor\Api\QuoteDataBackupRepositoryInterface::class
        )->disableOriginalConstructor()
                                                    ->getMock();

        $this->quoteDataBackupModel = $objectManager->getObject(
            \MageWorx\OrderEditor\Model\QuoteDataBackup::class
        );
        $this->serializer           = $objectManager->getObject(
            \Magento\Framework\Serialize\Serializer\Json::class
        );
        $this->restoreQuoteModel    = $objectManager->getObject(
            \MageWorx\OrderEditor\Model\RestoreQuote::class,
            [
                'quoteDataBackupRepository' => $this->quoteDataBackupRepositoryMock,
                'serializer'                => $this->serializer
            ]
        );

        $this->quoteMock = $this->getMockBuilder(
            \Magento\Quote\Model\Quote::class
        )
                                ->disableOriginalConstructor()
                                ->getMock();
    }

    /**
     * Backup Quote must not throw exception
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testBackupQuoteSimple()
    {
        $dummyQuoteId = 1;
        $dummyOrderId = 3;

        $this->quoteMock->expects($this->atLeastOnce())
                        ->method('getId')
                        ->willReturn($dummyQuoteId);

        $this->quoteMock->expects($this->atLeastOnce())->method('getOrigOrderId')
                        ->willReturn($dummyOrderId);

        $quoteData = [
            'quote' => [
                'anyJsonDataForTestNotEmpty' => true
            ]
        ];
        $this->quoteMock->expects($this->atLeastOnce())
                        ->method('getData')
                        ->willReturn($quoteData);

        $this->quoteMock->expects($this->atLeastOnce())
                        ->method('getAllItems')
                        ->willReturn([]);

        $this->quoteDataBackupRepositoryMock->expects($this->once())
                                            ->method('save')
                                            ->will($this->returnValue($this->quoteDataBackupModel));

        $this->assertNull($this->restoreQuoteModel->backupInitialQuoteState($this->quoteMock));
    }
}
