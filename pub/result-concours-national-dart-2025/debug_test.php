<?php
// Simple test to check if we can connect to the database and fetch data
$host = '127.0.0.1';
$port = '3307';
$dbname = 'technadminy7_dBT8x12y22';
$username = 'root';
$password = 'YourNewStrongPassword';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Database connection successful!\n";
    
    // Test query
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM amasty_customform_answer WHERE form_id = 9 AND status = 0");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total entries: " . $result['count'] . "\n";
    
    // Fetch one entry to test
    $stmt = $pdo->prepare("SELECT * FROM amasty_customform_answer WHERE form_id = 9 AND status = 0 LIMIT 1");
    $stmt->execute();
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($entry) {
        echo "Sample entry found:\n";
        echo "Answer ID: " . $entry['answer_id'] . "\n";
        echo "Created at: " . $entry['created_at'] . "\n";
        
        // Check if response_json is valid
        $data = json_decode($entry['response_json'], true);
        if ($data) {
            echo "JSON data parsed successfully\n";
            echo "Keys in response: " . implode(', ', array_keys($data)) . "\n";
        } else {
            echo "Failed to parse JSON data\n";
            echo "JSON error: " . json_last_error_msg() . "\n";
            echo "Response JSON: " . substr($entry['response_json'], 0, 200) . "...\n";
        }
    } else {
        echo "No entries found\n";
    }
    
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>