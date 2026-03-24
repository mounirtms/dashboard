<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Setup;

use Magento\Framework\App\Bootstrap;
use Magento\Framework\ObjectManagerInterface;

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
     * @var mixed
     */
    private $serviceManager;

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
            // Define magento-init-params if needed
            if (!defined('MAGENTO_INIT_PARAMS')) {
                define('MAGENTO_INIT_PARAMS', '');
            }
            
            $bootstrap = Bootstrap::create(BP, $_SERVER);
            $this->objectManager = $bootstrap->getObjectManager();
        }
        return $this->objectManager;
    }

    /**
     * Bootstrap the application
     *
     * @param array $configuration
     * @return $this
     */
    public function bootstrap(array $configuration = [])
    {
        // Initialize object manager
        $objectManager = $this->getObjectManager();
        
        // Set service manager - use object manager itself as the service manager
        $this->serviceManager = $objectManager;
        
        return $this;
    }

    /**
     * Get service manager
     *
     * @return mixed
     */
    public function getServiceManager()
    {
        return $this->serviceManager;
    }
}
