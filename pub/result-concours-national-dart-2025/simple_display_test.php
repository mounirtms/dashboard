<?php
// Simplified display script to test data fetching
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
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM amasty_customform_answer WHERE form_id = :form_id AND status = 0");
    $stmt->bindParam(':form_id', $formId);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h1>Concours National d'Art 2025 - Data Status</h1>";
    echo "<p>Total entries in database: " . $result['count'] . "</p>";
    
    if ($result['count'] > 0) {
        // Fetch a few sample entries
        $stmt = $pdo->prepare("SELECT answer_id, created_at, response_json FROM amasty_customform_answer WHERE form_id = :form_id AND status = 0 ORDER BY answer_id DESC LIMIT 3");
        $stmt->bindParam(':form_id', $formId);
        $stmt->execute();
        $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>Sample Entries:</h2>";
        foreach ($answers as $answer) {
            echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
            echo "<p><strong>ID:</strong> " . $answer['answer_id'] . "</p>";
            echo "<p><strong>Date:</strong> " . $answer['created_at'] . "</p>";
            echo "<p><strong>JSON Length:</strong> " . strlen($answer['response_json']) . " characters</p>";
            echo "</div>";
        }
    } else {
        echo "<p>No entries found. This might explain why the display is empty.</p>";
    }
    
} catch (PDOException $e) {
    echo "<h1>Error</h1>";
    echo "<p>Database connection failed: " . $e->getMessage() . "</p>";
}
?>