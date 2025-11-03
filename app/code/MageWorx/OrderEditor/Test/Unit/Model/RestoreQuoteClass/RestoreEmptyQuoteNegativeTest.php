<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Test\Unit\Model\RestoreQuoteClass;

/**
 * Class RestoreEmptyQuoteNegativeTest
 *
 * Restore method for empty quote must throw an exception.
 */
class RestoreEmptyQuoteNegativeTest extends \PHPUnit\Framework\TestCase
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

        $this->restoreQuoteModel = $objectManager->getObject(
            \MageWorx\OrderEditor\Model\RestoreQuote::class
        );

        $this->quoteMock         = $this->getMockBuilder(
            \Magento\Quote\Model\Quote::class
        )
                                        ->disableOriginalConstructor()
                                        ->getMock();
    }

    /**
     * Restore Quote must throw exception for empty quote
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function testRestoreEmptyQuote()
    {
        $this->quoteMock->expects($this->atLeastOnce())->method('getId')->willReturn(
            null
        );
        $this->expectExceptionMessage((string)__('Unable to restore quote: empty quote id'));
        $this->restoreQuoteModel->restore($this->quoteMock);
    }
}
