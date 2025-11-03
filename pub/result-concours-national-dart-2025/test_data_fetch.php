<?php
// Test script to check if data is being fetched correctly
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
    $stmt = $pdo->prepare("SELECT * FROM amasty_customform_answer WHERE form_id = :form_id AND status = 0 ORDER BY answer_id DESC LIMIT 5");
    $stmt->bindParam(':form_id', $formId);
    $stmt->execute();
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h1>Database Connection Test</h1>";
    echo "<p>Connected successfully to the database.</p>";
    
    echo "<h2>Sample Data (First 5 entries):</h2>";
    echo "<p>Total entries found: " . count($answers) . "</p>";
    
    if (count($answers) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Answer ID</th><th>Created At</th><th>Response JSON Length</th><th>Status</th></tr>";
        
        foreach ($answers as $answer) {
            echo "<tr>";
            echo "<td>" . $answer['answer_id'] . "</td>";
            echo "<td>" . $answer['created_at'] . "</td>";
            echo "<td>" . strlen($answer['response_json']) . " characters</td>";
            echo "<td>" . $answer['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h2>First Entry Details:</h2>";
        echo "<pre>";
        print_r(json_decode($answers[0]['response_json'], true));
        echo "</pre>";
    } else {
        echo "<p>No entries found for form ID 9 with status 0.</p>";
    }
    
} catch (PDOException $e) {
    echo "<h1>Database Connection Error</h1>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check the database connection parameters.</p>";
}
?>