<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Mab\Core\Test\Integration;

use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for MAB Core module configuration
 */
class ModuleConfigTest extends TestCase
{
    /**
     * @var ModuleListInterface
     */
    private $moduleList;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Setup method
     */
    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->moduleList = $objectManager->get(ModuleListInterface::class);
        $this->scopeConfig = $objectManager->get(ScopeConfigInterface::class);
    }

    /**
     * Test that MAB Core module is properly registered
     */
    public function testModuleIsRegistered()
    {
        $this->assertTrue($this->moduleList->has('Mab_Core'), 'Mab_Core module should be registered');
    }

    /**
     * Test that MAB Core module version is defined
     */
    public function testModuleVersionIsDefined()
    {
        $moduleInfo = $this->moduleList->getOne('Mab_Core');
        $this->assertArrayHasKey('setup_version', $moduleInfo, 'Module should have a setup version');
        $this->assertNotEmpty($moduleInfo['setup_version'], 'Module version should not be empty');
    }

    /**
     * Test core configuration values
     */
    public function testCoreConfiguration()
    {
        // Test that the core configuration section exists
        $coreConfigValue = $this->scopeConfig->getValue('mab_core/general_settings/debug_mode');
        $this->assertNotNull($coreConfigValue, 'Core debug mode configuration should exist');
    }

    /**
     * Test module management configuration
     */
    public function testModuleManagementConfig()
    {
        // Test that module management configuration exists
        $deliveryEnabled = $this->scopeConfig->getValue('mab_core/module_management/delivery_options_enabled');
        $this->assertNotNull($deliveryEnabled, 'Delivery options enabled configuration should exist');
    }
}