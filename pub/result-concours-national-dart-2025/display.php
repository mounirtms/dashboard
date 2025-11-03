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

// Handle AJAX requests for rating and deletion
if (isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if ($_POST['action'] === 'rate' && isset($_POST['answer_id']) && isset($_POST['rating'])) {
        $answerId = (int)$_POST['answer_id'];
        $rating = (int)$_POST['rating'];
        
        if ($rating >= 1 && $rating <= 5) {
            try {
                // Check if rating already exists
                $stmt = $pdo->prepare("SELECT id FROM amasty_customform_ratings WHERE answer_id = ?");
                $stmt->execute([$answerId]);
                
                if ($stmt->rowCount() > 0) {
                    // Update existing rating
                    $stmt = $pdo->prepare("UPDATE amasty_customform_ratings SET rating = ? WHERE answer_id = ?");
                    $stmt->execute([$rating, $answerId]);
                } else {
                    // Insert new rating
                    $stmt = $pdo->prepare("INSERT INTO amasty_customform_ratings (answer_id, rating) VALUES (?, ?)");
                    $stmt->execute([$answerId, $rating]);
                }
                
                $response['success'] = true;
                $response['message'] = 'Rating saved successfully';
                $response['rating'] = $rating;
            } catch (Exception $e) {
                $response['message'] = 'Error saving rating: ' . $e->getMessage();
            }
        } else {
            $response['message'] = 'Invalid rating value';
        }
    } 
    elseif ($_POST['action'] === 'delete' && isset($_POST['answer_id'])) {
        $answerId = (int)$_POST['answer_id'];
        
        try {
            // Delete the answer (ratings will be deleted automatically due to foreign key constraint)
            $stmt = $pdo->prepare("DELETE FROM amasty_customform_answer WHERE answer_id = ?");
            $stmt->execute([$answerId]);
            
            $response['success'] = true;
            $response['message'] = 'Entry deleted successfully';
        } catch (Exception $e) {
            $response['message'] = 'Error deleting entry: ' . $e->getMessage();
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Fetch form data - ALWAYS fetch from database, bypass cache for debugging
$formId = 9;
$stmt = $pdo->prepare("SELECT * FROM amasty_customform_answer WHERE form_id = :form_id AND status = 0 ORDER BY answer_id DESC");
$stmt->bindParam(':form_id', $formId);
$stmt->execute();
$answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process data for display
$processedAnswers = [];
foreach ($answers as $answer) {
    $data = json_decode($answer['response_json'], true);
    if ($data) {
        // Get rating if exists
        $ratingStmt = $pdo->prepare("SELECT rating FROM amasty_customform_ratings WHERE answer_id = ?");
        $ratingStmt->execute([$answer['answer_id']]);
        $ratingRow = $ratingStmt->fetch(PDO::FETCH_ASSOC);
        $rating = $ratingRow ? $ratingRow['rating'] : 0;
        
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
            'photo' => isset($data['file-photo-oeuvre']['value']) ? 
                      (is_array($data['file-photo-oeuvre']['value']) ? 
                       $data['file-photo-oeuvre']['value']['filename'] : 
                       $data['file-photo-oeuvre']['value']) : '',
            'title' => isset($data['textinput-titre-oeuvre']['value']) ? $data['textinput-titre-oeuvre']['value'] : '',
            'dimension' => isset($data['textinput-dimension']['value']) ? $data['textinput-dimension']['value'] : '',
            'techniques' => isset($data['textarea-techniques-utiliser']['value']) ? $data['textarea-techniques-utiliser']['value'] : '',
            'source' => isset($data['textarea-source']['value']) ? $data['textarea-source']['value'] : '',
            'source_concours' => isset($data['dropdown-1654516257917']['value']) ? $data['dropdown-1654516257917']['value'] : '',
            'rules' => isset($data['checkbox-rules']['value']) ? 
                      (is_array($data['checkbox-rules']['value']) ? 
                       implode(', ', $data['checkbox-rules']['value']) : 
                       $data['checkbox-rules']['value']) : '',
            // Add rating field
            'rating' => $rating
        ];
        $processedAnswers[] = $processedAnswer;
    }
}

// Calculate statistics
$totalEntries = count($processedAnswers);
$wilayaStats = [];
$sourceStats = [];
$ratingStats = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

foreach ($processedAnswers as $answer) {
    // Count by wilaya
    $wilaya = $answer['wilaya'];
    if (!isset($wilayaStats[$wilaya])) {
        $wilayaStats[$wilaya] = 0;
    }
    $wilayaStats[$wilaya]++;
    
    // Count by source
    $source = $answer['source_concours'];
    if (!isset($sourceStats[$source])) {
        $sourceStats[$source] = 0;
    }
    $sourceStats[$source]++;
    
    // Count ratings
    $rating = $answer['rating'] > 0 ? $answer['rating'] : 0;
    if ($rating > 0) {
        $ratingStats[$rating]++;
    }
}

// Sort statistics
arsort($wilayaStats);
arsort($sourceStats);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Concours National d'Art 2025 - Galerie des Œuvres</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: #333;
            min-height: 100vh;
            padding-bottom: 50px;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        header {
            text-align: center;
            padding: 40px 20px;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        header::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #ff8a00, #e52e71, #1a2a6c);
            animation: headerLine 3s linear infinite;
        }

        @keyframes headerLine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            margin-bottom: 15px;
            background: linear-gradient(45deg, #ff8a00, #e52e71);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .subtitle {
            font-size: 1.3rem;
            max-width: 800px;
            margin: 0 auto 25px;
            color: #666;
            line-height: 1.6;
        }

        .stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .stat-box {
            background: white;
            padding: 15px 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
            border: 1px solid #eee;
        }

        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(45deg, #ff8a00, #e52e71);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 1rem;
            color: #666;
            margin-top: 5px;
        }

        .container {
            max-width: 100%;
            margin: 30px auto;
            padding: 0 20px;
        }

        .controls {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, );
        }
        
        .view-options {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .view-options label {
            font-weight: 600;
            color: #333;
        }

        .view-options button {
            background: white;
            border: 2px solid #ddd;
            border-radius: 8px;
            width: 40px;
            height: 40px;
            cursor: pointer;
            font-weight: bold;
            color: #999;
            transition: all 0.3s ease;
        }

        .view-options button.active {
            border-color: #ff8a00;
            color: #ff8a00;
            background: rgba(255, 138, 0, 0.1);
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border-radius: 30px;
            border: 1px solid #ddd;
            background: white;
            color: #333;
            font-size: 1rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .search-box input::placeholder {
            color: #999;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .filter-box select {
            padding: 12px 20px;
            border-radius: 30px;
            border: 1px solid #ddd;
            background: white;
            color: #333;
            font-size: 1rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            min-width: 180px;
        }

        .gallery {
            display: grid;
            gap: 30px;
        }

        .gallery.cols-4 {
            grid-template-columns: repeat(4, 1fr);
        }

        .gallery.cols-5 {
            grid-template-columns: repeat(5, 1fr);
        }

        @media (max-width: 1600px) {
            .gallery.cols-5 {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 1400px) {
            .gallery.cols-4 {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 1200px) {
            .gallery.cols-4, .gallery.cols-5 {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .container {
                max-width: 1200px;
            }
        }

        @media (max-width: 768px) {
            .gallery.cols-4, .gallery.cols-5 {
                grid-template-columns: 1fr;
            }
        }

        @media (min-width: 1600px) {
            .gallery.cols-4 {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .gallery.cols-5 {
                grid-template-columns: repeat(5, 1fr);
            }
        }

        .card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #eee;
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .card-image {
            height: 250px;
            overflow: hidden;
            position: relative;
        }

        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .card:hover .card-image img {
            transform: scale(1.05);
        }

        .card-rating {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.9);
            padding: 5px 10px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .card-content {
            padding: 20px;
        }

        .card-title {
            font-size: 1.4rem;
            margin-bottom: 10px;
            font-weight: 600;
            color: #333;
        }

        .card-artist {
            font-size: 1.1rem;
            margin-bottom: 15px;
            color: #ff8a00;
        }

        .card-details {
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: #666;
            line-height: 1.6;
        }

        .card-meta {
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #eee;
            padding-top: 15px;
            font-size: 0.9rem;
            color: #999;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 20px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-rate {
            background: linear-gradient(45deg, #ff8a00, #e52e71);
            color: white;
            flex: 1;
        }

        .btn-rate:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(229, 46, 113, 0.4);
        }

        .btn-delete {
            background: #f8f9fa;
            color: #e74c3c;
            border: 1px solid #eee;
        }

        .btn-delete:hover {
            background: #ffebee;
        }

        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .chart-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 30px 0;
        }

        .chart-box {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid #eee;
        }

        .chart-title {
            font-size: 1.3rem;
            margin-bottom: 20px;
            text-align: center;
            color: #ff8a00;
        }

        .chart-bar {
            margin-bottom: 15px;
        }

        .chart-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .chart-bar-inner {
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .chart-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #ff8a00, #e52e71);
            border-radius: 10px;
        }

        /* Rating stars */
        .rating-stars {
            display: flex;
            gap: 2px;
        }

        .rating-stars i {
            color: #ddd;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .rating-stars .filled {
            color: #ff8a00;
        }

        /* Statistics section */
        .statistics-section {
            margin-top: 50px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .statistics-title {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 30px;
            color: #333;
            font-family: 'Playfair Display', serif;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #eee;
        }

        .stat-card-title {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: #ff8a00;
            text-align: center;
        }

        .rating-distribution {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .rating-bar {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .rating-label {
            width: 50px;
            font-weight: 600;
        }

        .rating-bar-inner {
            flex: 1;
            height: 10px;
            background: #f0f0f0;
            border-radius: 5px;
            overflow: hidden;
        }

        .rating-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #ff8a00, #e52e71);
            border-radius: 5px;
        }

        .rating-count {
            width: 40px;
            text-align: right;
            font-size: 0.9rem;
        }

        /* Notification styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            transform: translateX(120%);
            transition: transform 0.3s ease;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            background: linear-gradient(45deg, #4CAF50, #8BC34A);
        }

        .notification.error {
            background: linear-gradient(45deg, #F44336, #E91E63);
        }

        @media (max-width: 1200px) {
            .gallery {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
            
            .container {
                max-width: 1200px;
            }
        }

        @media (max-width: 768px) {
            .gallery {
                grid-template-columns: 1fr;
            }
            
            h1 {
                font-size: 2.5rem;
            }
            
            .stats {
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }
            
            .controls {
                flex-direction: column;
            }
            
            .chart-container {
                grid-template-columns: 1fr;
            }
            
            .filter-box select {
                min-width: auto;
            }
        }

        @media (min-width: 1600px) {
            .gallery {
                grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            }
        }
    </style>
</head>
<body>
    <header>
        <h1>Concours National d'Art 2025</h1>
        <p class="subtitle">Découvrez les œuvres soumises par les artistes talentueux de toute l'Algérie</p>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?php echo $totalEntries; ?></div>
                <div class="stat-label">Œuvres soumises</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo count($wilayaStats); ?></div>
                <div class="stat-label">Wilayas participantes</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo max($wilayaStats); ?></div>
                <div class="stat-label">Wilaya la plus active</div>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="controls">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Rechercher par nom d'artiste, titre d'œuvre...">
            </div>
            <div class="filter-box">
                <select id="wilayaFilter">
                    <option value="">Toutes les wilayas</option>
                    <?php foreach (array_keys($wilayaStats) as $wilaya): ?>
                        <option value="<?php echo htmlspecialchars($wilaya); ?>"><?php echo htmlspecialchars($wilaya); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-box">
                <select id="ratingFilter">
                    <option value="">Toutes les notes</option>
                    <option value="5">5 étoiles</option>
                    <option value="4">4 étoiles et plus</option>
                    <option value="3">3 étoiles et plus</option>
                    <option value="2">2 étoiles et plus</option>
                    <option value="1">1 étoile et plus</option>
                </select>
            </div>
            <div class="filter-box">
                <select id="sortBy">
                    <option value="newest">Plus récents</option>
                    <option value="oldest">Plus anciens</option>
                    <option value="rating_high">Note: Décroissant</option>
                    <option value="rating_low">Note: Croissant</option>
                </select>
            </div>
            <div class="view-options">
                <label>Affichage:</label>
                <button id="view4" class="view-btn active" data-cols="4">4</button>
                <button id="view5" class="view-btn" data-cols="5">5</button>
            </div>
        </div>

        <div class="gallery cols-4" id="gallery">
            <?php foreach ($processedAnswers as $answer): ?>
            <div class="card" 
                 data-id="<?php echo $answer['id']; ?>"
                 data-name="<?php echo htmlspecialchars(strtolower($answer['firstname'] . ' ' . $answer['lastname'])); ?>" 
                 data-title="<?php echo htmlspecialchars(strtolower($answer['title'])); ?>" 
                 data-wilaya="<?php echo htmlspecialchars($answer['wilaya']); ?>"
                 data-rating="<?php echo $answer['rating']; ?>">
                <div class="card-image">
                    <?php if (!empty($answer['photo'])): ?>
                        <?php if (pathinfo($answer['photo'], PATHINFO_EXTENSION) === 'pdf'): ?>
                            <img src="https://via.placeholder.com/400x250/4a4a4a/ffffff?text=PDF+Document" alt="Document PDF">
                        <?php else: ?>
                            <img src="/pub/media/amasty/amcustomform/<?php echo htmlspecialchars($answer['photo']); ?>" 
                                 alt="<?php echo htmlspecialchars($answer['title']); ?>" 
                                 onerror="handleImageError(this, '<?php echo htmlspecialchars($answer['photo']); ?>')"
                                 loading="lazy">
                        <?php endif; ?>
                    <?php else: ?>
                        <img src="https://via.placeholder.com/400x250/4a4a4a/ffffff?text=Pas+d'image" alt="Pas d'image">
                    <?php endif; ?>
                    <div class="card-rating">
                        <div class="card-rating-value"><?php echo $answer['rating'] > 0 ? number_format($answer['rating'], 1) : '-'; ?></div>
                        <div class="rating-stars" data-answer-id="<?php echo $answer['id']; ?>">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= $answer['rating'] ? 'filled' : ''; ?>" data-rating="<?php echo $i; ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <div class="card-content">
                    <h3 class="card-title"><?php echo htmlspecialchars($answer['title'] ?: 'Sans titre'); ?></h3>
                    <div class="card-artist"><?php echo htmlspecialchars($answer['firstname'] . ' ' . $answer['lastname']); ?></div>
                    <div class="card-details">
                        <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($answer['wilaya']); ?></p>
                        <p><i class="fas fa-ruler-combined"></i> <?php echo htmlspecialchars($answer['dimension']); ?></p>
                        <p><i class="fas fa-paint-brush"></i> <?php echo htmlspecialchars(substr($answer['techniques'], 0, 100)) . (strlen($answer['techniques']) > 100 ? '...' : ''); ?></p>
                    </div>
                    <div class="card-meta">
                        <span><i class="far fa-calendar"></i> <?php echo date('d/m/Y', strtotime($answer['created_at'])); ?></span>
                        <span><i class="far fa-clock"></i> <?php echo date('H:i', strtotime($answer['created_at'])); ?></span>
                    </div>
                    <div class="action-buttons">
                        <button class="btn btn-rate" onclick="openRatingModal(<?php echo $answer['id']; ?>, <?php echo $answer['rating']; ?>)">
                            <i class="fas fa-star"></i> Noter
                        </button>
                        <button class="btn btn-delete" onclick="deleteEntry(<?php echo $answer['id']; ?>)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Statistics Section -->
        <div class="statistics-section">
            <h2 class="statistics-title">Statistiques détaillées</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3 class="stat-card-title">Distribution des notes</h3>
                    <div class="rating-distribution">
                        <?php foreach ($ratingStats as $rating => $count): ?>
                        <div class="rating-bar">
                            <div class="rating-label"><?php echo $rating; ?> étoiles</div>
                            <div class="rating-bar-inner">
                                <div class="rating-bar-fill" style="width: <?php echo ($totalEntries > 0) ? ($count / $totalEntries * 100) : 0; ?>%"></div>
                            </div>
                            <div class="rating-count"><?php echo $count; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3 class="stat-card-title">Wilayas les plus actives</h3>
                    <?php 
                    $topWilayasStats = array_slice($wilayaStats, 0, 5, true);
                    $maxWilayaCount = max($wilayaStats);
                    foreach ($topWilayasStats as $wilaya => $count): ?>
                    <div class="chart-bar">
                        <div class="chart-label">
                            <span><?php echo htmlspecialchars($wilaya); ?></span>
                            <span><?php echo $count; ?></span>
                        </div>
                        <div class="chart-bar-inner">
                            <div class="chart-bar-fill" style="width: <?php echo ($count / $maxWilayaCount) * 100; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="stat-card">
                    <h3 class="stat-card-title">Sources de connaissance</h3>
                    <?php 
                    arsort($sourceStats);
                    $topSources = array_slice($sourceStats, 0, 5, true);
                    $maxSourceCount = max($sourceStats);
                    foreach ($topSources as $source => $count): ?>
                    <div class="chart-bar">
                        <div class="chart-label">
                            <span><?php echo htmlspecialchars($source ?: 'Non spécifié'); ?></span>
                            <span><?php echo $count; ?></span>
                        </div>
                        <div class="chart-bar-inner">
                            <div class="chart-bar-fill" style="width: <?php echo ($count / $maxSourceCount) * 100; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Rating Modal -->
    <div id="ratingModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1001; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; max-width: 400px; width: 90%;">
            <h3 style="margin-top: 0; color: #333;">Noter cette œuvre</h3>
            <div id="modalStars" style="display: flex; justify-content: center; gap: 10px; margin: 20px 0; font-size: 2rem;">
                <i class="fas fa-star" style="color: #ddd; cursor: pointer;" data-rating="1"></i>
                <i class="fas fa-star" style="color: #ddd; cursor: pointer;" data-rating="2"></i>
                <i class="fas fa-star" style="color: #ddd; cursor: pointer;" data-rating="3"></i>
                <i class="fas fa-star" style="color: #ddd; cursor: pointer;" data-rating="4"></i>
                <i class="fas fa-star" style="color: #ddd; cursor: pointer;" data-rating="5"></i>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button id="cancelRating" style="padding: 10px 20px; border: 1px solid #ddd; border-radius: 5px; background: #f5f5f5; cursor: pointer;">Annuler</button>
                <button id="saveRating" style="padding: 10px 20px; border: none; border-radius: 5px; background: linear-gradient(45deg, #ff8a00, #e52e71); color: white; cursor: pointer;">Enregistrer</button>
            </div>
        </div>
    </div>

    <!-- Notification Container -->
    <div id="notificationContainer"></div>

    <script>
        // Image error handling function
        function handleImageError(imgElement, filename) {
            console.log('Image error for:', filename);
            
            // Try alternative loading methods
            const baseUrl = '/pub/media/amasty/amcustomform/';
            const fullPath = baseUrl + filename;
            
            // Try with a cache-busting parameter
            const cacheBustedUrl = fullPath + '?v=' + Date.now();
            
            // Set a timeout to try alternative URL
            setTimeout(() => {
                imgElement.src = cacheBustedUrl;
                imgElement.onerror = function() {
                    // Final fallback
                    this.src = 'https://via.placeholder.com/400x250/4a4a4a/ffffff?text=Image+non+disponible';
                };
            }, 100);
        }
        
        // Ensure deleteEntry function is globally available
        function deleteEntry(answerId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette entrée ?')) {
                // Send AJAX request to delete entry
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                // Remove the card from the UI
                                const card = document.querySelector(`.card[data-id="${answerId}"]`);
                                if (card) {
                                    card.remove();
                                }
                                
                                showNotification('Entrée supprimée avec succès!', 'success');
                            } else {
                                showNotification('Erreur: ' + response.message, 'error');
                            }
                        } catch (e) {
                            showNotification('Erreur lors de la suppression', 'error');
                        }
                    }
                };
                
                xhr.send('action=delete&answer_id=' + answerId);
            }
        }
        
        // Ensure openRatingModal function is globally available
        function openRatingModal(answerId, currentRating) {
            currentRatingAnswerId = answerId;
            currentRatingValue = currentRating || 0;
            
            // Update star display
            const stars = document.querySelectorAll('#modalStars .fa-star');
            stars.forEach((star, index) => {
                if (index < currentRatingValue) {
                    star.style.color = '#ff8a00';
                } else {
                    star.style.color = '#ddd';
                }
            });
            
            // Show modal
            document.getElementById('ratingModal').style.display = 'flex';
        }
        
        let currentRatingAnswerId = null;
        let currentRatingValue = 0;

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const cards = document.querySelectorAll('.card');
            
            cards.forEach(card => {
                const name = card.getAttribute('data-name');
                const title = card.getAttribute('data-title');
                
                if (searchTerm === '' || name.includes(searchTerm) || title.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Filter by wilaya
        document.getElementById('wilayaFilter').addEventListener('change', function() {
            applyAllFilters();
        });
        
        // Filter by rating
        document.getElementById('ratingFilter').addEventListener('change', function() {
            applyAllFilters();
        });
        
        // Sort functionality
        document.getElementById('sortBy').addEventListener('change', function() {
            const sortBy = this.value;
            const gallery = document.getElementById('gallery');
            const cards = Array.from(document.querySelectorAll('.card'));
            
            cards.sort((a, b) => {
                switch(sortBy) {
                    case 'newest':
                        return 0; // Default order from PHP
                    case 'oldest':
                        return 0; // Would need to reverse the default order
                    case 'rating_high':
                        const ratingA = parseInt(a.getAttribute('data-rating')) || 0;
                        const ratingB = parseInt(b.getAttribute('data-rating')) || 0;
                        return ratingB - ratingA;
                    case 'rating_low':
                        const ratingC = parseInt(a.getAttribute('data-rating')) || 0;
                        const ratingD = parseInt(b.getAttribute('data-rating')) || 0;
                        return ratingC - ratingD;
                    default:
                        return 0;
                }
            });
            
            // Re-append sorted cards to gallery
            cards.forEach(card => gallery.appendChild(card));
        });
        
        // View options
        document.querySelectorAll('.view-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Update active button
                document.querySelectorAll('.view-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');
                
                // Update gallery layout
                const cols = this.getAttribute('data-cols');
                const gallery = document.getElementById('gallery');
                gallery.className = 'gallery cols-' + cols;
            });
        });
        
        // Apply all filters function
        function applyAllFilters() {
            const selectedWilaya = document.getElementById('wilayaFilter').value;
            const minRating = document.getElementById('ratingFilter').value;
            const cards = document.querySelectorAll('.card');
            
            cards.forEach(card => {
                const wilaya = card.getAttribute('data-wilaya');
                const rating = parseInt(card.getAttribute('data-rating')) || 0;
                
                let show = true;
                
                // Apply wilaya filter
                if (selectedWilaya !== '' && wilaya !== selectedWilaya) {
                    show = false;
                }
                
                // Apply rating filter
                if (minRating !== '' && rating < parseInt(minRating)) {
                    show = false;
                }
                
                card.style.display = show ? 'block' : 'none';
            });
        }
        
        // Open rating modal
        function openRatingModal(answerId, currentRating) {
            currentRatingAnswerId = answerId;
            currentRatingValue = currentRating;
            
            // Update star display in modal
            const stars = document.querySelectorAll('#modalStars .fa-star');
            stars.forEach((star, index) => {
                if (index < currentRating) {
                    star.style.color = '#ff8a00';
                } else {
                    star.style.color = '#ddd';
                }
            });
            
            // Show modal
            document.getElementById('ratingModal').style.display = 'flex';
        }
        
        // Handle star clicks in modal
        document.querySelectorAll('#modalStars .fa-star').forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                currentRatingValue = rating;
                
                // Update star display
                const stars = document.querySelectorAll('#modalStars .fa-star');
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.style.color = '#ff8a00';
                    } else {
                        s.style.color = '#ddd';
                    }
                });
            });
        });
        
        // Save rating
        document.getElementById('saveRating').addEventListener('click', function() {
            if (currentRatingAnswerId && currentRatingValue > 0) {
                // Send AJAX request to save rating
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                // Update UI to reflect the new rating
                                const card = document.querySelector(`.card[data-id="${currentRatingAnswerId}"]`);
                                if (card) {
                                    // Update rating value
                                    const ratingValue = card.querySelector('.card-rating-value');
                                    ratingValue.textContent = currentRatingValue.toFixed(1);
                                    
                                    // Update stars
                                    const stars = card.querySelectorAll('.rating-stars i');
                                    stars.forEach((star, index) => {
                                        if (index < currentRatingValue) {
                                            star.classList.add('filled');
                                        } else {
                                            star.classList.remove('filled');
                                        }
                                    });
                                    
                                    card.setAttribute('data-rating', currentRatingValue);
                                }
                                
                                showNotification('Note enregistrée avec succès!', 'success');
                            } else {
                                showNotification('Erreur: ' + response.message, 'error');
                            }
                        } catch (e) {
                            showNotification('Erreur lors de l\'enregistrement de la note', 'error');
                        }
                        
                        // Close modal
                        document.getElementById('ratingModal').style.display = 'none';
                    }
                };
                
                xhr.send('action=rate&answer_id=' + currentRatingAnswerId + '&rating=' + currentRatingValue);
            }
        });
        
        // Cancel rating
        document.getElementById('cancelRating').addEventListener('click', function() {
            document.getElementById('ratingModal').style.display = 'none';
        });
        
        // Close modal when clicking outside
        document.getElementById('ratingModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
        
        // Handle star clicks directly on card
        document.querySelectorAll('.rating-stars').forEach(starContainer => {
            const stars = starContainer.querySelectorAll('.fa-star');
            stars.forEach(star => {
                star.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const rating = parseInt(this.getAttribute('data-rating'));
                    const answerId = parseInt(starContainer.getAttribute('data-answer-id'));
                    
                    // Send AJAX request to save rating
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', '', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.success) {
                                    // Update UI to reflect the new rating
                                    const card = document.querySelector(`.card[data-id="${answerId}"]`);
                                    if (card) {
                                        // Update rating value
                                        const ratingValue = card.querySelector('.card-rating-value');
                                        ratingValue.textContent = rating.toFixed(1);
                                        
                                        // Update stars
                                        const stars = card.querySelectorAll('.rating-stars i');
                                        stars.forEach((s, index) => {
                                            if (index < rating) {
                                                s.classList.add('filled');
                                            } else {
                                                s.classList.remove('filled');
                                            }
                                        });
                                        
                                        card.setAttribute('data-rating', rating);
                                    }
                                    
                                    showNotification('Note enregistrée avec succès!', 'success');
                                } else {
                                    showNotification('Erreur: ' + response.message, 'error');
                                }
                            } catch (e) {
                                showNotification('Erreur lors de l\'enregistrement de la note', 'error');
                            }
                        }
                    };
                    
                    xhr.send('action=rate&answer_id=' + answerId + '&rating=' + rating);
                });
            });
        });
        
        // Show notification
        function showNotification(message, type) {
            const container = document.getElementById('notificationContainer');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            
            container.appendChild(notification);
            
            // Show notification
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);
            
            // Hide notification after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    container.removeChild(notification);
                }, 300);
            }, 3000);
        }
    </script>
</body>
</html>