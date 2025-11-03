<?php
// Test to see what's happening with the display script
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
    $stmt = $pdo->prepare("SELECT * FROM amasty_customform_answer WHERE form_id = :form_id AND status = 0 ORDER BY answer_id DESC LIMIT 3");
    $stmt->bindParam(':form_id', $formId);
    $stmt->execute();
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($answers) . " answers\n";
    
    // Process data for display
    $processedAnswers = [];
    foreach ($answers as $answer) {
        $data = json_decode($answer['response_json'], true);
        if ($data) {
            echo "Processing answer ID: " . $answer['answer_id'] . "\n";
            
            $processedAnswer = [
                'id' => $answer['answer_id'],
                'created_at' => $answer['created_at'],
                'lastname' => isset($data['lastname']['value']) ? $data['lastname']['value'] : '',
                'firstname' => isset($data['firstname']['value']) ? $data['firstname']['value'] : '',
                'photo' => isset($data['file-photo-oeuvre']['value']) ? $data['file-photo-oeuvre']['value'] : '',
                'title' => isset($data['textinput-titre-oeuvre']['value']) ? $data['textinput-titre-oeuvre']['value'] : ''
            ];
            
            echo "Photo: " . $processedAnswer['photo'] . "\n";
            echo "Title: " . $processedAnswer['title'] . "\n";
            echo "---\n";
            
            $processedAnswers[] = $processedAnswer;
        }
    }
    
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>