<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrdersBase\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use MageWorx\OrdersBase\Model\AreaCodeParser;
use MageWorx\OrdersBase\Model\DeviceData;
use MageWorx\OrdersBase\Model\DeviceTypeParser;

class ConvertDeviceCodesToNames implements DataPatchInterface, PatchVersionInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();

        // Area
        $areas = AreaCodeParser::getAllAreas();
        foreach ($areas as $code => $name) {
            $where = ['area_code = ?' => $code];
            $connection->update(
                $this->moduleDataSetup->getTable(DeviceData::TABLE_NAME),
                [
                    'area_name' => $name
                ],
                $where
            );
        }

        // Device
        $devices = DeviceTypeParser::getAllDevices();
        foreach ($devices as $name => $code) {
            $name = ucwords($name);
            $where = ['device_code = ?' => $code];
            $connection->update(
                $this->moduleDataSetup->getTable(DeviceData::TABLE_NAME),
                [
                    'device_name' => $name
                ],
                $where
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getVersion()
    {
        return '2.0.0';
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
