<?php

use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';
$bootstrap = Bootstrap::create(BP, $_SERVER);

$objectManager = $bootstrap->getObjectManager();
$sessionManager = $objectManager->get('Magento\Framework\Session\SessionManagerInterface');

// Get the session lifetime in seconds
$sessionLifetime = $sessionManager->getLifetime();

// Calculate the timestamp for expired sessions
$expirationTimestamp = time() - $sessionLifetime;

// Delete expired sessions from the database
$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
$connection = $resource->getConnection();
$tableName = $resource->getTableName('session');

// Use the correct column names for the session table
// session_expires is the timestamp column in Magento's session table
$sql = "DELETE FROM `{$tableName}` WHERE `session_expires` < {$expirationTimestamp}";
$connection->query($sql);

echo "Expired sessions cleaned successfully.\n";

?>