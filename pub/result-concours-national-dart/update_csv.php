<?php
// Database connection
$host = '127.0.0.1';
$port = '3307';
$dbname = 'technadminy7_dBT8x12y22';
$username = 'root';
$password = 'YourNewStrongPassword';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch form data
$formId = 9;
$stmt = $pdo->prepare("
    SELECT a.*, 
           COALESCE(r.rating, 0) as rating 
    FROM amasty_customform_answer a 
    LEFT JOIN amasty_customform_ratings r ON a.answer_id = r.answer_id 
    WHERE a.form_id = :form_id AND a.status = 0 
    ORDER BY a.answer_id DESC
");
$stmt->bindParam(':form_id', $formId);
$stmt->execute();
$answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process data for CSV
$csvData = [];
$csvData[] = [
    'Created At',
    'Nom / اللقب',
    'Prénom / الإسم',
    'Age / العمر',
    'Wilaya / الولاية *',
    'E-mail /البريد الإلكتروني',
    'Téléphone رقم الهاتف',
    'Photo de l\'œuvre /صورة العمل الفني',
    'Titre de l\'œuvre /عنوان العمل الفني',
    'Dimension / مقاس اللوحة',
    'Techniques utilisées / التقنيات المستعملة',
    'Source d\'inspiration / مصدر الإلهام',
    'Comment avez-vous pris connaissance du Concours ?',
    'Column 1',
    'Téléphone 2 رقم الهاتف'
];

foreach ($answers as $answer) {
    $data = json_decode($answer['response_json'], true);
    if ($data) {
        $photoFilename = isset($data['file-photo-oeuvre']['value']) ? 
            (is_array($data['file-photo-oeuvre']['value']) ? 
             $data['file-photo-oeuvre']['value']['filename'] : 
             $data['file-photo-oeuvre']['value']) : '';
        
        $csvRow = [
            'created_at' => $answer['created_at'],
            'lastname' => isset($data['lastname']['value']) ? $data['lastname']['value'] : '',
            'firstname' => isset($data['firstname']['value']) ? $data['firstname']['value'] : '',
            'age' => isset($data['textinput-age']['value']) ? $data['textinput-age']['value'] : '',
            'wilaya' => isset($data['dropdown-1693638713000']['value']) ? $data['dropdown-1693638713000']['value'] : '',
            'email' => isset($data['textinput-e-mail']['value']) ? $data['textinput-e-mail']['value'] : '',
            'phone1' => isset($data['textinput-mobile']['value']) ? $data['textinput-mobile']['value'] : '',
            'photo' => $photoFilename,
            'title' => isset($data['textinput-titre-oeuvre']['value']) ? $data['textinput-titre-oeuvre']['value'] : '',
            'dimension' => isset($data['textinput-dimension']['value']) ? $data['textinput-dimension']['value'] : '',
            'techniques' => isset($data['textarea-techniques-utiliser']['value']) ? $data['textarea-techniques-utiliser']['value'] : '',
            'source' => isset($data['textarea-source']['value']) ? $data['textarea-source']['value'] : '',
            'source_concours' => isset($data['dropdown-1654516257917']['value']) ? $data['dropdown-1654516257917']['value'] : '',
            'rules' => isset($data['checkbox-rules']['value']) ? 
                      (is_array($data['checkbox-rules']['value']) ? 
                       implode(', ', $data['checkbox-rules']['value']) : 
                       $data['checkbox-rules']['value']) : '',
            'phone2' => isset($data['textinput-1758452360450']['value']) ? $data['textinput-1758452360450']['value'] : ''
        ];
        
        // Convert to CSV format
        $csvData[] = [
            $csvRow['created_at'],
            $csvRow['lastname'],
            $csvRow['firstname'],
            $csvRow['age'],
            $csvRow['wilaya'],
            $csvRow['email'],
            $csvRow['phone1'],
            'https://technostationery.com/pub/media/amasty/amcustomform/' . $csvRow['photo'],
            $csvRow['title'],
            $csvRow['dimension'],
            $csvRow['techniques'],
            $csvRow['source'],
            $csvRow['source_concours'],
            $csvRow['rules'],
            $csvRow['phone2']
        ];
    }
}

// Write to CSV file
$csvFile = 'csv.csv';
$fp = fopen($csvFile, 'w');

// Add BOM for UTF-8
fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));

foreach ($csvData as $row) {
    fputcsv($fp, $row);
}

fclose($fp);

echo "CSV file updated successfully with " . (count($csvData) - 1) . " entries.\n";
?>