<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Setup;

use Magento\Framework\App\Bootstrap;
use Magento\Framework\ObjectManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Application class for Magento Setup
 */
class Application extends \Symfony\Component\Console\Application
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @param string $name
     * @param string $version
     */
    public function __construct($name = 'Magento', $version = '2.4.0')
    {
        parent::__construct($name, $version);
    }

    /**
     * Retrieve object manager
     *
     * @return ObjectManagerInterface
     */
    public function getObjectManager()
    {
        if (!$this->objectManager) {
            $bootstrap = Bootstrap::create(BP, $_SERVER);
            $this->objectManager = $bootstrap->getObjectManager();
        }
        return $this->objectManager;
    }

    /**
     * Bootstrap the application
     *
     * @param InputInterface|array $input
     * @param OutputInterface $output
     * @return void
     */
    public function bootstrap($input, $output = null)
    {
        // Minimal bootstrap - just initialize object manager if needed
        $this->getObjectManager();
    }
}
