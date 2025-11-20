<?php
/**
 * Data Processor Class
 * Handles extraction and processing of form data
 */

class DataProcessor {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Fetch form data with proper sorting
     */
    public function fetchFormData($formId = 9, $sortOrder = 'newest') {
        // Build query based on sort order
        $orderBy = "ORDER BY answer_id DESC"; // Default newest first
        switch ($sortOrder) {
            case 'oldest':
                $orderBy = "ORDER BY answer_id ASC";
                break;
            case 'rating_high':
                $orderBy = "ORDER BY rating DESC, answer_id DESC";
                break;
            case 'rating_low':
                $orderBy = "ORDER BY rating ASC, answer_id DESC";
                break;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT a.*, 
                   COALESCE(r.rating, 0) as rating 
            FROM amasty_customform_answer a 
            LEFT JOIN amasty_customform_ratings r ON a.answer_id = r.answer_id 
            WHERE a.form_id = :form_id AND a.status = 0 
            {$orderBy}
        ");
        $stmt->bindParam(':form_id', $formId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Process raw form data into structured format
     */
    public function processAnswers($answers) {
        $processedAnswers = [];
        $dimensionStats = []; // For dimension filtering
        
        foreach ($answers as $answer) {
            $data = json_decode($answer['response_json'], true);
            if ($data) {
                $dimension = isset($data['textinput-dimension']['value']) ? $data['textinput-dimension']['value'] : '';
                
                // Collect dimension data for filtering
                if (!empty($dimension)) {
                    if (!isset($dimensionStats[$dimension])) {
                        $dimensionStats[$dimension] = 0;
                    }
                    $dimensionStats[$dimension]++;
                }
                
                $photoValue = isset($data['file-photo-oeuvre']['value']) ? 
                             (is_array($data['file-photo-oeuvre']['value']) ? 
                              $data['file-photo-oeuvre']['value']['filename'] : 
                              $data['file-photo-oeuvre']['value']) : '';
                
                $rulesValue = isset($data['checkbox-rules']['value']) ? 
                             (is_array($data['checkbox-rules']['value']) ? 
                              implode(', ', $data['checkbox-rules']['value']) : 
                              $data['checkbox-rules']['value']) : '';
                
                $processedAnswer = [
                    'id' => $answer['answer_id'],
                    'created_at' => $answer['created_at'],
                    'lastname' => isset($data['lastname']['value']) ? $data['lastname']['value'] : '',
                    'firstname' => isset($data['firstname']['value']) ? $data['firstname']['value'] : '',
                    'age' => isset($data['textinput-age']['value']) ? $data['textinput-age']['value'] : '',
                    'wilaya' => isset($data['dropdown-1693638713000']['value']) ? $data['dropdown-1693638713000']['value'] : '',
                    'email' => isset($data['textinput-e-mail']['value']) ? $data['textinput-e-mail']['value'] : '',
                    'phone1' => isset($data['textinput-mobile']['value']) ? $data['textinput-mobile']['value'] : '',
                    'phone2' => isset($data['textinput-1758452360450']['value']) ? $data['textinput-1758452360450']['value'] : '',
                    'photo' => $photoValue,
                    'title' => isset($data['textinput-titre-oeuvre']['value']) ? $data['textinput-titre-oeuvre']['value'] : '',
                    'dimension' => $dimension,
                    'techniques' => isset($data['textarea-techniques-utiliser']['value']) ? $data['textarea-techniques-utiliser']['value'] : '',
                    'source' => isset($data['textarea-source']['value']) ? $data['textarea-source']['value'] : '',
                    'source_concours' => isset($data['dropdown-1654516257917']['value']) ? $data['dropdown-1654516257917']['value'] : '',
                    'rules' => $rulesValue,
                    // Add rating field
                    'rating' => $answer['rating']
                ];
                $processedAnswers[] = $processedAnswer;
            }
        }
        
        return [
            'processedAnswers' => $processedAnswers,
            'dimensionStats' => $dimensionStats
        ];
    }
    
    /**
     * Export data to CSV format
     */
    public function exportToCSV($answers, $filename = 'export.csv') {
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
        $fp = fopen($filename, 'w');
        
        // Add BOM for UTF-8
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
        
        foreach ($csvData as $row) {
            fputcsv($fp, $row);
        }
        
        fclose($fp);
        
        return $filename;
    }
}