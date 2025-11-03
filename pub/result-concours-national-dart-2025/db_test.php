<?php
// Database connection test
$host = '127.0.0.1';
$port = '3307';
$dbname = 'technadminy7_dBT8x12y22';
$username = 'root';
$password = 'YourNewStrongPassword';

echo "<h1>Database Connection Test</h1>";

try {
    echo "<p>Attempting to connect to database...</p>";
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>SUCCESS: Connected to database successfully!</p>";
    
    // Test query
    echo "<p>Running test query...</p>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '$dbname'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Total tables in database: " . $result['count'] . "</p>";
    
    // Check for amasty tables
    echo "<p>Checking for amasty tables...</p>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'amasty_customform_answer'");
    $tables = $stmt->fetchAll();
    if (count($tables) > 0) {
        echo "<p style='color: green;'>SUCCESS: Found amasty_customform_answer table</p>";
        
        // Check how many entries we have
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM amasty_customform_answer WHERE form_id = 9 AND status = 0");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Entries in amasty_customform_answer (form_id=9, status=0): " . $result['count'] . "</p>";
    } else {
        echo "<p style='color: red;'>ERROR: amasty_customform_answer table not found</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>ERROR: " . $e->getMessage() . "</p>";
}

echo "<h2>PHP Info:</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>PDO MySQL extension: " . (extension_loaded('pdo_mysql') ? 'Available' : 'Not available') . "</p>";
?>