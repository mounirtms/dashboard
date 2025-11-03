<?php
// Test script to verify data fetching
$host = '127.0.0.1';
$port = '3307';
$dbname = 'technadminy7_dBT8x12y22';
$username = 'root';
$password = 'YourNewStrongPassword';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch form data
    $formId = 9;
    $stmt = $pdo->prepare("SELECT * FROM amasty_customform_answer WHERE form_id = :form_id AND status = 0 ORDER BY answer_id DESC LIMIT 1");
    $stmt->bindParam(':form_id', $formId);
    $stmt->execute();
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($answers) > 0) {
        echo "<h1>Data Fetch Test - SUCCESS</h1>";
        echo "<p>Found " . count($answers) . " entries</p>";
        echo "<h2>Sample Entry:</h2>";
        echo "<pre>";
        print_r($answers[0]);
        echo "</pre>";
        
        // Try to decode the JSON
        $data = json_decode($answers[0]['response_json'], true);
        if ($data) {
            echo "<h2>Decoded JSON Data:</h2>";
            echo "<pre>";
            print_r($data);
            echo "</pre>";
        } else {
            echo "<p>ERROR: Could not decode JSON data</p>";
        }
    } else {
        echo "<h1>Data Fetch Test - NO DATA FOUND</h1>";
        echo "<p>No entries found for form_id = 9 with status = 0</p>";
    }
    
} catch (PDOException $e) {
    echo "<h1>Database Error</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>