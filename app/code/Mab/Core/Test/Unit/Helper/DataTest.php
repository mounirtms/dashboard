<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Mab\Core\Test\Unit\Helper;

use Mab\Core\Helper\Data;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for Mab\Core\Helper\Data
 */
class DataTest extends TestCase
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * Setup method
     */
    protected function setUp(): void
    {
        $this->objectManager = new ObjectManager($this);
        $this->helper = $this->objectManager->getObject(Data::class);
    }

    /**
     * Test checkout customization enabled method
     */
    public function testIsCheckoutCustomizationEnabled()
    {
        // This is a basic test - in a real scenario, you would mock the configuration
        $this->assertIsBool($this->helper->isCheckoutCustomizationEnabled());
    }

    /**
     * Test visual effects enabled method
     */
    public function testIsVisualEffectsEnabled()
    {
        // This is a basic test - in a real scenario, you would mock the configuration
        $this->assertIsBool($this->helper->isVisualEffectsEnabled());
    }

    /**
     * Test is debug mode enabled method
     */
    public function testIsDebugModeEnabled()
    {
        // This is a basic test - in a real scenario, you would mock the configuration
        $this->assertIsBool($this->helper->isDebugModeEnabled());
    }
}