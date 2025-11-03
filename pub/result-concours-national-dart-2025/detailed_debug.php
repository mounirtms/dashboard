<?php
// Detailed test to see what's happening with the photo field
$host = '127.0.0.1';
$port = '3307';
$dbname = 'technadminy7_dBT8x12y22';
$username = 'root';
$password = 'YourNewStrongPassword';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fetch the problematic entry
    $stmt = $pdo->prepare("SELECT * FROM amasty_customform_answer WHERE answer_id = 1722");
    $stmt->execute();
    $answer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($answer) {
        echo "Answer ID: " . $answer['answer_id'] . "\n";
        echo "Response JSON:\n" . $answer['response_json'] . "\n\n";
        
        // Parse the JSON
        $data = json_decode($answer['response_json'], true);
        if ($data) {
            echo "Parsed data:\n";
            print_r($data);
            
            echo "\nPhoto field:\n";
            if (isset($data['file-photo-oeuvre'])) {
                print_r($data['file-photo-oeuvre']);
                if (isset($data['file-photo-oeuvre']['value'])) {
                    echo "\nPhoto value:\n";
                    print_r($data['file-photo-oeuvre']['value']);
                }
            }
        }
    }
    
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>