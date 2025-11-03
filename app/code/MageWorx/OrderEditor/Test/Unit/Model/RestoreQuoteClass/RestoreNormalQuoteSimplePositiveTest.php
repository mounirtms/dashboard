<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Test\Unit\Model\RestoreQuoteClass;

/**
 * Class RestoreNormalQuoteSimplePositiveTest
 *
 * Restore quote must not throw any exception.
 * Necessary methods must be called expected number of times.
 */
class RestoreNormalQuoteSimplePositiveTest extends \PHPUnit\Framework\TestCase
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
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $quoteRepositoryMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $quoteDataBackupModelMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $orderEditorQuoteMock;

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
        )
                                                    ->disableOriginalConstructor()
                                                    ->getMock();

        $this->quoteRepositoryMock = $this->getMockBuilder(
            \MageWorx\OrderEditor\Api\QuoteRepositoryInterface::class
        )
                                          ->disableOriginalConstructor()
                                          ->getMock();

        $this->quoteDataBackupModelMock = $this->getMockBuilder(
            \MageWorx\OrderEditor\Model\QuoteDataBackup::class
        )
                                               ->disableOriginalConstructor()
                                               ->getMock();

        $this->serializer = $objectManager->getObject(
            \Magento\Framework\Serialize\Serializer\Json::class
        );

        $this->restoreQuoteModel = $objectManager->getObject(
            \MageWorx\OrderEditor\Model\RestoreQuote::class,
            [
                'quoteDataBackupRepository' => $this->quoteDataBackupRepositoryMock,
                'quoteRepository'           => $this->quoteRepositoryMock,
                'serializer'                => $this->serializer
            ]
        );

        $this->quoteMock = $this->getMockBuilder(
            \Magento\Quote\Model\Quote::class
        )
                                ->disableOriginalConstructor()
                                ->getMock();

        $this->orderEditorQuoteMock = $this->getMockBuilder(
            \MageWorx\OrderEditor\Model\Quote::class
        )
                                           ->disableOriginalConstructor()
                                           ->getMock();
    }

    /**
     * Restore Quote must not throw exception
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testRestoreQuoteSimple()
    {
        $this->quoteMock->expects($this->atLeastOnce())
                        ->method('getId')
                        ->willReturn(
                            1
                        );

        $quoteData     = [
            'quote' => [
                'anyJsonDataForTestNotEmpty' => true
            ]
        ];
        $quoteDataJson = json_encode($quoteData);
        $this->quoteDataBackupModelMock->expects($this->once())
                                       ->method('getDataSerialized')
                                       ->willReturn($quoteDataJson);

        $this->quoteDataBackupRepositoryMock->expects($this->once())
                                            ->method('getByQuoteId')
                                            ->willReturn($this->quoteDataBackupModelMock);

        $this->quoteDataBackupRepositoryMock->expects($this->once())
                                            ->method('delete');

        $this->quoteRepositoryMock->expects($this->atLeastOnce())
                                  ->method('getById')
                                  ->willReturn($this->orderEditorQuoteMock);

        $this->quoteRepositoryMock->expects($this->once())
                                  ->method('save')
                                  ->with($this->orderEditorQuoteMock)
                                  ->willReturn($this->orderEditorQuoteMock);

        $this->assertNull($this->restoreQuoteModel->restore($this->quoteMock));
    }
}
