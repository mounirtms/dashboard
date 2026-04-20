<?php
/**
 * Script to enable the custom discount display module
 */

require_once __DIR__ . '/../app/bootstrap.php';

use Magento\Framework\App\Bootstrap;
use Magento\Framework\Console\Cli;

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

// Enable the module
$input = new \Symfony\Component\Console\Input\ArrayInput([
    'command' => 'module:enable',
    'modules' => ['Custom_DiscountDisplay'],
    '--clear-static-content' => true
]);
$output = new \Symfony\Component\Console\Output\NullOutput();

$app = $objectManager->get(\Magento\Framework\App\AreaList::class);
$state = $objectManager->get(\Magento\Framework\App\State::class);

// Run setup upgrade
$setupCommand = $objectManager->get(\Magento\Framework\Module\Manager::class);
$cli = $objectManager->get(Cli::class);

echo "Enabling Custom_DiscountDisplay module...\n";

// Execute module enable
$application = $objectManager->get(\Magento\Framework\Console\Application::class);
$application->init();

// Using shell exec to run the commands
exec('cd /home/technadminy7/public_html && php bin/magento module:enable Custom_DiscountDisplay', $output1, $return1);
if ($return1 === 0) {
    echo "Module enabled successfully.\n";
} else {
    echo "Error enabling module:\n";
    print_r($output1);
}

exec('cd /home/technadminy7/public_html && php bin/magento setup:upgrade', $output2, $return2);
if ($return2 === 0) {
    echo "Setup upgrade completed.\n";
} else {
    echo "Error during setup upgrade:\n";
    print_r($output2);
}

exec('cd /home/technadminy7/public_html && php bin/magento cache:flush', $output3, $return3);
if ($return3 === 0) {
    echo "Cache flushed successfully.\n";
} else {
    echo "Error flushing cache:\n";
    print_r($output3);
}

echo "Module setup completed!\n";