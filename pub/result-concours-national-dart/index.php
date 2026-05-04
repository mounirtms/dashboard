<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

session_start();

// Ensure a simple CSRF/login token exists (for future forms)
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(16));
}
// Database connection
$host = '127.0.0.1';
$port = '3307';
$dbname = 'technadminy7_dBT8x12y22';
$username = 'root';
$password = 'YourNewStrongPassword';

$pdo = null;
$db_error = null;

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $db_error = $e->getMessage();
    error_log("DB Connection Error: " . $db_error);
}

$isAuthenticated = !empty($_SESSION['user_id']);
$userEmail = $_SESSION['user_email'] ?? null;

// AJAX Handlers
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    $action = $_POST['action'] ?? '';
    // Require CSRF token for state-changing actions (all except initial login)
    if ($action !== 'login') {
        $csrf = $_POST['csrf_token'] ?? '';
        if (empty($csrf) || empty($_SESSION['token']) || !hash_equals($_SESSION['token'], $csrf)) {
            $response['message'] = 'Invalid CSRF token';
            echo json_encode($response);
            exit;
        }
    }
    
    // Login action: verify Google id_token server-side and set session
    if ($_POST['action'] === 'login' && !empty($_POST['id_token'])) {
        $idToken = $_POST['id_token'];
        $tokenInfo = @file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken));
        if ($tokenInfo === false) {
            error_log('Login: tokeninfo request failed');
            $response['message'] = 'Unable to verify token';
            echo json_encode($response);
            exit;
        }
        $info = json_decode($tokenInfo, true);
        // Basic stricter validation: must contain email, valid issuer, and not expired
        $valid = true;
        if (empty($info['email'])) $valid = false;
        // issuer check
        if (!empty($info['iss'])) {
            $iss = $info['iss'];
            if (!in_array($iss, ['accounts.google.com', 'https://accounts.google.com'])) {
                $valid = false;
                error_log('Login: invalid issuer ' . $iss);
            }
        }
        // expiry check if present
        if (!empty($info['exp'])) {
            $exp = (int)$info['exp'];
            if ($exp < time()) {
                $valid = false;
                error_log('Login: token expired ' . $exp . ' now=' . time());
            }
        }

        if ($valid) {
            $_SESSION['user_email'] = $info['email'];
            $_SESSION['user_id'] = $info['sub'] ?? $info['email'];
            $response['success'] = true;
            $response['message'] = 'Authenticated';
            $response['email'] = $info['email'];
        } else {
            $response['message'] = 'Invalid token';
        }
        echo json_encode($response);
        exit;
    }

    // Logout action: clear session
    if ($_POST['action'] === 'logout') {
        // clear session server-side
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        $response['success'] = true;
        $response['message'] = 'Logged out';
        echo json_encode($response);
        exit;
    }

    if ($_POST['action'] === 'rate' && $isAuthenticated) {
        $answerId = (int)($_POST['answer_id'] ?? 0);
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 0)));
        
        if (!$pdo) {
            $response['message'] = 'Database not available';
            echo json_encode($response);
            exit;
        }
        try {
            $stmt = $pdo->prepare("SELECT id FROM amasty_customform_ratings WHERE answer_id = ?");
            $stmt->execute([$answerId]);
            
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->prepare("UPDATE amasty_customform_ratings SET rating = ? WHERE answer_id = ?");
                $stmt->execute([$rating, $answerId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO amasty_customform_ratings (answer_id, rating) VALUES (?, ?)");
                $stmt->execute([$answerId, $rating]);
            }
            
            $response['success'] = true;
            $response['message'] = 'Rating saved';
            $response['rating'] = $rating;
        } catch (Exception $e) {
            error_log('Rating error: ' . $e->getMessage());
            $response['message'] = 'Error saving rating';
        }
    } elseif ($_POST['action'] === 'delete' && $isAuthenticated) {
        $answerId = (int)($_POST['answer_id'] ?? 0);
        
        try {
            if (!$pdo) {
                $response['message'] = 'Database not available';
                echo json_encode($response);
                exit;
            }

            $stmt = $pdo->prepare("SELECT answer_id FROM amasty_customform_answer WHERE answer_id = ?");
            $stmt->execute([$answerId]);
            if ($stmt->rowCount() === 0) {
                $response['message'] = 'Entry not found';
                echo json_encode($response);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM amasty_customform_ratings WHERE answer_id = ?");
            $stmt->execute([$answerId]);

            $stmt = $pdo->prepare("DELETE FROM amasty_customform_answer WHERE answer_id = ?");
            $stmt->execute([$answerId]);

            $response['success'] = true;
            $response['message'] = 'Entry deleted successfully';
        } catch (Exception $e) {
            error_log('Delete error: ' . $e->getMessage());
            $response['message'] = 'Error deleting entry';
        }
    }
    
    // allow updating category for an entry
    if ($_POST['action'] === 'set_category' && $isAuthenticated) {
        $answerId = (int)($_POST['answer_id'] ?? 0);
        $category = trim((string)($_POST['category'] ?? ''));
        if (!$pdo) {
            $response['message'] = 'Database not available';
            echo json_encode($response);
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE amasty_customform_answer SET category = ? WHERE answer_id = ?");
            $stmt->execute([$category, $answerId]);
            $response['success'] = true;
            $response['message'] = 'Category updated';
        } catch (Exception $e) {
            error_log('Set category error: ' . $e->getMessage());
            $response['message'] = 'Error updating category';
        }
        echo json_encode($response);
        exit;
    }
    
    echo json_encode($response);
    exit;
}

// Export Handlers
if (isset($_GET['export'])) {
    if (!$isAuthenticated) {
        http_response_code(403);
        exit('Unauthorized');
    }
    
    $type = $_GET['export'];
    $sortOrder = $_GET['sort'] ?? 'newest';
    $orderBy = "ORDER BY a.answer_id DESC";
    
    switch ($sortOrder) {
        case 'oldest': $orderBy = "ORDER BY a.answer_id ASC"; break;
        case 'rating_high': $orderBy = "ORDER BY COALESCE(r.rating, 0) DESC, a.answer_id DESC"; break;
        case 'rating_low': $orderBy = "ORDER BY COALESCE(r.rating, 0) ASC, a.answer_id DESC"; break;
    }
    
    if (!$pdo) {
        http_response_code(500);
        exit('Database not available');
    }

    $formId = 7;
    if (!empty($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT a.*, COALESCE(r.rating, 0) as rating FROM amasty_customform_answer a LEFT JOIN amasty_customform_ratings r ON a.answer_id = r.answer_id WHERE a.answer_id = :id AND a.status = 0 {$orderBy}");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare("SELECT a.*, COALESCE(r.rating, 0) as rating FROM amasty_customform_answer a LEFT JOIN amasty_customform_ratings r ON a.answer_id = r.answer_id WHERE a.form_id = :form_id AND a.status = 0 {$orderBy}");
        $stmt->bindParam(':form_id', $formId);
    }
    $stmt->execute();
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data = [];
    foreach ($answers as $answer) {
        $json = json_decode($answer['response_json'], true);
        $data[] = [
            'ID' => $answer['answer_id'],
            'Title' => $json['textinput-titre-oeuvre']['value'] ?? '',
            'Artist' => ($json['firstname']['value'] ?? '') . ' ' . ($json['lastname']['value'] ?? ''),
            'Wilaya' => $json['dropdown-1693638713000']['value'] ?? '',
            'Dimension' => $json['textinput-dimension']['value'] ?? '',
            'Techniques' => $json['textarea-techniques-utiliser']['value'] ?? '',
            'Inspiration' => $json['textarea-source']['value'] ?? '',
            'Email' => $json['textinput-e-mail']['value'] ?? '',
            'Phone' => $json['textinput-mobile']['value'] ?? '',
            'Rating' => $answer['rating'],
            'Date' => $answer['created_at']
        ];
    }
    
    if ($type === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="concours-art-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($data[0] ?? []));
        foreach ($data as $row) fputcsv($out, $row);
        fclose($out);
        exit;
    } elseif ($type === 'xlsx') {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><Worksheet><SheetData>';
        $headers = array_keys($data[0] ?? []);
        $xml .= '<Row>';
        foreach ($headers as $h) $xml .= '<Cell><Value>' . htmlspecialchars($h) . '</Value></Cell>';
        $xml .= '</Row>';
        foreach ($data as $row) {
            $xml .= '<Row>';
            foreach ($row as $v) $xml .= '<Cell><Value>' . htmlspecialchars($v) . '</Value></Cell>';
            $xml .= '</Row>';
        }
        $xml .= '</SheetData></Worksheet></Workbook>';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="concours-art-' . date('Y-m-d') . '.xlsx"');
        echo $xml;
        exit;
    }
}

// Fetch Data
$sortOrder = $_GET['sort'] ?? 'newest';
$orderBy = "ORDER BY a.answer_id DESC";
switch ($sortOrder) {
    case 'oldest': $orderBy = "ORDER BY a.answer_id ASC"; break;
    case 'rating_high': $orderBy = "ORDER BY COALESCE(r.rating, 0) DESC, a.answer_id DESC"; break;
    case 'rating_low': $orderBy = "ORDER BY COALESCE(r.rating, 0) ASC, a.answer_id DESC"; break;
}

$formId = 9;
$stmt = $pdo->prepare("SELECT a.*, COALESCE(r.rating, 0) as rating FROM amasty_customform_answer a LEFT JOIN amasty_customform_ratings r ON a.answer_id = r.answer_id WHERE a.form_id = :form_id AND a.status = 0 {$orderBy}");
$stmt->bindParam(':form_id', $formId);
$stmt->execute();
$answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$processedAnswers = [];
$categories = [];
$wilayaStats = [];
$dimensionStats = [];
$ratingStats = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

foreach ($answers as $answer) {
    $data = json_decode($answer['response_json'], true);
    if ($data) {
        $category = $answer['category'] ?? 'Uncategorized';
        $wilaya = $data['dropdown-1693638713000']['value'] ?? '';
        $dimension = $data['textinput-dimension']['value'] ?? '';
        $rating = (int)$answer['rating'];
        
        $categories[$category] = ($categories[$category] ?? 0) + 1;
        $wilayaStats[$wilaya] = ($wilayaStats[$wilaya] ?? 0) + 1;
        $dimensionStats[$dimension] = ($dimensionStats[$dimension] ?? 0) + 1;
        if ($rating > 0) $ratingStats[$rating]++;
        
        $processedAnswers[] = [
            'id' => $answer['answer_id'],
            'created_at' => $answer['created_at'],
            'lastname' => $data['lastname']['value'] ?? '',
            'firstname' => $data['firstname']['value'] ?? '',
            'age' => $data['textinput-age']['value'] ?? '',
            'wilaya' => $wilaya,
            'email' => $data['textinput-e-mail']['value'] ?? '',
            'phone1' => $data['textinput-mobile']['value'] ?? '',
            'phone2' => $data['textinput-1758452360450']['value'] ?? '',
            'photo' => $data['file-photo-oeuvre']['value'] ?? '',
            'title' => $data['textinput-titre-oeuvre']['value'] ?? '',
            'dimension' => $dimension,
            'techniques' => $data['textarea-techniques-utiliser']['value'] ?? '',
            'source' => $data['textarea-source']['value'] ?? '',
            'source_concours' => $data['dropdown-1654516257917']['value'] ?? '',
            'rating' => $rating,
            'category' => $category
        ];
    }
}

arsort($wilayaStats);
arsort($categories);
arsort($dimensionStats);

// --- Server-side filtering and pagination ---
$overallTotal = count($processedAnswers); // before filters

// Read filters from query string
$search = trim((string)($_GET['search'] ?? ''));
$filter_wilaya = trim((string)($_GET['filter_wilaya'] ?? ''));
$filter_dimension = trim((string)($_GET['filter_dimension'] ?? ''));
$filter_category = trim((string)($_GET['filter_category'] ?? ''));
$min_rating = (int)($_GET['min_rating'] ?? 0);

$filtered = array_filter($processedAnswers, function($a) use ($search, $filter_wilaya, $filter_dimension, $filter_category, $min_rating) {
    if ($search !== '') {
        $needle = mb_strtolower($search);
        $hay = mb_strtolower($a['title'] . ' ' . $a['firstname'] . ' ' . $a['lastname']);
        if (mb_strpos($hay, $needle) === false) return false;
    }
    if ($filter_wilaya !== '' && $a['wilaya'] !== $filter_wilaya) return false;
    if ($filter_dimension !== '' && $a['dimension'] !== $filter_dimension) return false;
    if ($filter_category !== '' && $a['category'] !== $filter_category) return false;
    if ($min_rating > 0 && $a['rating'] < $min_rating) return false;
    return true;
});

$totalEntries = count($filtered);
$avgRating = $totalEntries > 0 ? array_sum(array_map(fn($a) => $a['rating'], $filtered)) / $totalEntries : 0;
$ratedEntries = count(array_filter($filtered, fn($a) => $a['rating'] > 0));

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(10, min(100, (int)($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;
$pagedAnswers = array_slice(array_values($filtered), $offset, $perPage);

// For the UI we will use $pagedAnswers for display
// Keep $processedAnswers untouched for any other use

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Concours National d'Art 2025</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/optimizations.css">
        <!-- Firebase SDK (compat) -->
        <script src="https://www.gstatic.com/firebasejs/9.22.2/firebase-app-compat.js"></script>
        <script src="https://www.gstatic.com/firebasejs/9.22.2/firebase-auth-compat.js"></script>
        <script>
                // Firebase configuration injected by developer
                window.firebaseConfig = {
                    apiKey: "AIzaSyAAd5DSy43vi9AsBKsUKcL7baf3w7blIOM",
                    authDomain: "techno-webapp.firebaseapp.com",
                    databaseURL: "https://techno-webapp-default-rtdb.europe-west1.firebasedatabase.app",
                    projectId: "techno-webapp",
                    storageBucket: "techno-webapp.firebasestorage.app",
                    messagingSenderId: "193747640510",
                    appId: "1:193747640510:web:71ab9709cbbc9d1f077eef",
                    measurementId: "G-X5EH85VVTJ"
                };
        </script>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-top">
                <div>
                    <h1>🎨 Concours National d'Art 2025</h1>
                    <p class="subtitle">Galerie des œuvres soumises</p>
                </div>
                <div class="auth-section">
                    <div id="googleSignInBtn" style="min-width: 200px;<?php echo $isAuthenticated ? 'display:none;' : ''; ?>">
                        <button type="button" id="fbSignBtn" class="btn-small fb-btn" aria-label="Se connecter avec Google" onclick="(typeof doFirebaseSignIn==='function')?doFirebaseSignIn():console.warn('doFirebaseSignIn not available');" tabindex="0" onkeyup="if(event.key==='Enter') { if(typeof doFirebaseSignIn==='function') doFirebaseSignIn(); }"><i class="fab fa-google"></i> Se connecter</button>
                    </div>
                    <div class="user-info" id="userInfo" style="<?php echo $isAuthenticated ? 'display:block;' : 'display:none;'; ?>"><?php echo $isAuthenticated ? '✓ ' . h($userEmail) : ''; ?></div>
                    <button class="logout-btn" id="logoutBtn" onclick="logout()" style="<?php echo $isAuthenticated ? 'display:block;' : 'display:none;'; ?>">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                    <div class="admin-toolbar" id="adminToolbar" style="<?php echo $isAuthenticated ? 'display:flex;' : 'display:none;'; ?>">
                        <button class="btn-small" id="btnExportAll" title="Exporter tous les résultats en CSV"><i class="fas fa-file-csv"></i> Export CSV</button>
                        <button class="btn-small" id="btnExportRated" title="Exporter les résultats notés"><i class="fas fa-star"></i> Export Rated</button>
                        <select id="perPageSelect" style="padding:8px;border-radius:6px;border:1px solid #e2e8f0;" title="Entrées par page">
                            <option value="10">10 / page</option>
                            <option value="20" selected>20 / page</option>
                            <option value="50">50 / page</option>
                            <option value="100">100 / page</option>
                        </select>
                        <select id="batchCategorySelect" style="padding:8px;border-radius:6px;border:1px solid #e2e8f0;margin-left:8px;">
                            <option value="">-- Appliquer la catégorie --</option>
                            <?php foreach (array_keys($categories) as $cat): ?>
                                <option value="<?php echo h($cat); ?>"><?php echo h($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn-small" id="btnApplyCategory" style="margin-left:6px;" title="Appliquer la catégorie aux éléments sélectionnés">Appliquer</button>
                    </div>
                </div>
            </div>
        </header>
        
        <div class="controls">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Rechercher par artiste ou titre..." value="<?php echo h($_GET['search'] ?? ''); ?>">
            </div>
            <div class="filter-box">
                <select id="wilayaFilter">
                    <option value="">Toutes les wilayas</option>
                    <?php foreach (array_keys($wilayaStats) as $wilaya): ?>
                        <option value="<?php echo h($wilaya); ?>" <?php echo ($filter_wilaya === $wilaya) ? 'selected' : ''; ?>><?php echo h($wilaya); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-box">
                <select id="dimensionFilter">
                    <option value="">Toutes les dimensions</option>
                    <?php foreach (array_keys($dimensionStats) as $dim): ?>
                        <option value="<?php echo h($dim); ?>" <?php echo ($filter_dimension === $dim) ? 'selected' : ''; ?>><?php echo h($dim); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-box">
                <select id="categoryFilter">
                    <option value="">Toutes les catégories</option>
                    <?php foreach (array_keys($categories) as $cat): ?>
                        <option value="<?php echo h($cat); ?>" <?php echo ($filter_category === $cat) ? 'selected' : ''; ?>><?php echo h($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-box">
                <select id="ratingFilter">
                    <option value="">Toutes les notes</option>
                    <option value="5" <?php echo ($min_rating == 5) ? 'selected' : ''; ?>>⭐⭐⭐⭐⭐ 5 étoiles</option>
                    <option value="4" <?php echo ($min_rating == 4) ? 'selected' : ''; ?>>⭐⭐⭐⭐ 4+ étoiles</option>
                    <option value="3" <?php echo ($min_rating == 3) ? 'selected' : ''; ?>>⭐⭐⭐ 3+ étoiles</option>
                    <option value="2" <?php echo ($min_rating == 2) ? 'selected' : ''; ?>>⭐⭐ 2+ étoiles</option>
                    <option value="1" <?php echo ($min_rating == 1) ? 'selected' : ''; ?>>⭐ 1+ étoile</option>
                </select>
            </div>
            <div class="sort-box">
                <select id="sortBy">
                    <option value="newest" <?php echo ($sortOrder === 'newest') ? 'selected' : ''; ?>>Plus récents</option>
                    <option value="oldest" <?php echo ($sortOrder === 'oldest') ? 'selected' : ''; ?>>Plus anciens</option>
                    <option value="rating_high" <?php echo ($sortOrder === 'rating_high') ? 'selected' : ''; ?>>Note: Décroissant</option>
                    <option value="rating_low" <?php echo ($sortOrder === 'rating_low') ? 'selected' : ''; ?>>Note: Croissant</option>
                </select>
            </div>
            <div class="view-toggle">
                <button class="view-btn active" id="viewCards" title="Vue grille"><i class="fas fa-th-large"></i></button>
                <button class="view-btn" id="viewTable" title="Vue tableau"><i class="fas fa-table"></i></button>
            </div>
        </div>
        
        <div class="main-content">
            <div class="gallery" id="cardsView">
                <?php foreach ($pagedAnswers as $answer): ?>
                    <div class="card" data-id="<?php echo $answer['id']; ?>" data-artist="<?php echo h(strtolower($answer['firstname'] . ' ' . $answer['lastname'])); ?>" data-title="<?php echo h(strtolower($answer['title'])); ?>" data-wilaya="<?php echo h($answer['wilaya']); ?>" data-dimension="<?php echo h($answer['dimension']); ?>" data-category="<?php echo h($answer['category']); ?>" data-rating="<?php echo $answer['rating']; ?>" ondblclick="openDetailModal(<?php echo $answer['id']; ?>)">
                    <div class="card-image">
                        <?php if (!empty($answer['photo'])): ?>
                            <img src="/pub/media/amasty/amcustomform/<?php echo h($answer['photo']); ?>" alt="<?php echo h($answer['title']); ?>" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22300%22%3E%3Crect fill=%22%23f0f0f0%22 width=%22400%22 height=%22300%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-size=%2220%22 fill=%22%23999%22 text-anchor=%22middle%22 dy=%22.3em%22%3EImage not found%3C/text%3E%3C/svg%3E'" loading="lazy">
                        <?php else: ?>
                            <img src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22300%22%3E%3Crect fill=%22%23f0f0f0%22 width=%22400%22 height=%22300%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-size=%2220%22 fill=%22%23999%22 text-anchor=%22middle%22 dy=%22.3em%22%3ENo Image%3C/text%3E%3C/svg%3E" alt="No image">
                        <?php endif; ?>
                        <div class="card-badge"><?php echo h($answer['category']); ?></div>
                        <div class="card-rating">
                            <span><?php echo $answer['rating'] > 0 ? number_format($answer['rating'], 1) : '-'; ?></span>
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $answer['rating'] ? 'filled' : ''; ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title"><?php echo h($answer['title']); ?></h3>
                        <div class="card-artist"><?php echo h($answer['firstname'] . ' ' . $answer['lastname']); ?></div>
                        <div class="card-meta">
                            <span><i class="fas fa-map-marker-alt"></i> <?php echo h(substr($answer['wilaya'], 0, 15)); ?></span>
                            <span><i class="fas fa-ruler-combined"></i> <?php echo h(substr($answer['dimension'], 0, 12)); ?></span>
                        </div>
                        <div class="card-actions">
                            <button class="btn-small btn-rate" onclick="openRatingModal(<?php echo $answer['id']; ?>, <?php echo $answer['rating']; ?>)"><i class="fas fa-star"></i> Noter</button>
                            <button class="btn-small btn-download" onclick="downloadEntry(<?php echo $answer['id']; ?>)"><i class="fas fa-download"></i> DL</button>
                            <button class="btn-small btn-delete" onclick="deleteEntry(<?php echo $answer['id']; ?>)"><i class="fas fa-trash"></i> Del</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
                <div class="table-view" id="tableView">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="<?php echo $isAuthenticated ? '' : 'display:none;'; ?>"><input type="checkbox" id="selectAllCheckbox" title="Sélectionner tout"></th>
                            <th>#</th><th>Image</th><th>Titre</th><th>Artiste</th><th>Wilaya</th><th>Dimension</th><th>Catégorie</th><th>Note</th><th>Date</th><th style="<?php echo $isAuthenticated ? '' : 'display:none;'; ?>">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $idx = ($offset ?? 0) + 1; foreach ($pagedAnswers as $answer): ?>
                        <tr data-id="<?php echo $answer['id']; ?>" data-artist="<?php echo h(strtolower($answer['firstname'] . ' ' . $answer['lastname'])); ?>" data-title="<?php echo h(strtolower($answer['title'])); ?>" data-wilaya="<?php echo h($answer['wilaya']); ?>" data-dimension="<?php echo h($answer['dimension']); ?>" data-category="<?php echo h($answer['category']); ?>" data-rating="<?php echo $answer['rating']; ?>" ondblclick="openDetailModal(<?php echo $answer['id']; ?>)" style="cursor:pointer;">
                                    <td style="<?php echo $isAuthenticated ? '' : 'display:none;'; ?>"><input type="checkbox" class="row-checkbox" value="<?php echo $answer['id']; ?>" title="Sélectionner"></td>
                                    <td><?php echo $idx++; ?></td>
                            <td>
                                <?php if (!empty($answer['photo'])): ?>
                                    <img src="/pub/media/amasty/amcustomform/<?php echo h($answer['photo']); ?>" alt="<?php echo h($answer['title']); ?>" class="thumbnail" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2245%22 height=%2245%22%3E%3Crect fill=%22%23f0f0f0%22 width=%2245%22 height=%2245%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-size=%228%22 fill=%22%23999%22 text-anchor=%22middle%22 dy=%22.3em%22%3E-%3C/text%3E%3C/svg%3E'" loading="lazy">
                                <?php else: ?>
                                    <img src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2245%22 height=%2245%22%3E%3Crect fill=%22%23f0f0f0%22 width=%2245%22 height=%2245%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-size=%228%22 fill=%22%23999%22 text-anchor=%22middle%22 dy=%22.3em%22%3E-%3C/text%3E%3C/svg%3E" alt="No image" class="thumbnail">
                                <?php endif; ?>
                            </td>
                            <td><?php echo h(substr($answer['title'], 0, 30)); ?></td>
                            <td><?php echo h($answer['firstname'] . ' ' . $answer['lastname']); ?></td>
                            <td><?php echo h($answer['wilaya']); ?></td>
                            <td><?php echo h($answer['dimension']); ?></td>
                            <td><?php echo h($answer['category']); ?></td>
                            <td><?php echo $answer['rating'] > 0 ? number_format($answer['rating'], 1) : '-'; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($answer['created_at'])); ?></td>
                            <td>
                                <button class="btn-small btn-rate" onclick="openRatingModal(<?php echo $answer['id']; ?>, <?php echo $answer['rating']; ?>)"><i class="fas fa-star"></i></button>
                                <button class="btn-small btn-download" onclick="downloadEntry(<?php echo $answer['id']; ?>)"><i class="fas fa-download"></i></button>
                                <button class="btn-small btn-delete" onclick="deleteEntry(<?php echo $answer['id']; ?>)"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <footer>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $totalEntries; ?></div>
                    <div class="stat-label">Œuvres soumises</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($wilayaStats); ?></div>
                    <div class="stat-label">Wilayas participantes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($avgRating, 1); ?></div>
                    <div class="stat-label">Note moyenne</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $ratedEntries; ?></div>
                    <div class="stat-label">Œuvres notées</div>
                </div>
            </div>
        </footer>
    </div>

    <!-- Pagination controls -->
    <div class="container" style="margin-top:20px;">
        <?php if ($totalEntries > $perPage):
            $totalPages = (int)ceil($totalEntries / $perPage);
            $qs = $_GET; // keep existing filters
        ?>
        <div class="pagination" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <?php if ($page > 1):
                $qs['page'] = $page - 1; ?>
                <a href="?<?php echo http_build_query($qs); ?>" class="pagination-link">&laquo; Prev</a>
            <?php endif; ?>

            <?php for ($p = 1; $p <= $totalPages; $p++):
                $qs['page'] = $p; ?>
                <a href="?<?php echo http_build_query($qs); ?>" class="pagination-link <?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages):
                $qs['page'] = $page + 1; ?>
                <a href="?<?php echo http_build_query($qs); ?>" class="pagination-link">Next &raquo;</a>
            <?php endif; ?>

            <!-- Page size selector (quick control) -->
            <div style="margin-left:auto;display:flex;gap:12px;align-items:center;">
                <label style="color:#666;font-weight:600;font-size:0.95rem;">Afficher</label>
                <select id="paginationPerPage" onchange="(function(){ const qs=new URLSearchParams(window.location.search); qs.set('per_page', this.value); qs.set('page', 1); window.location.search = qs.toString(); })()">
                    <option value="10" <?php echo ($perPage==10)?'selected':''; ?>>10</option>
                    <option value="20" <?php echo ($perPage==20)?'selected':''; ?>>20</option>
                    <option value="50" <?php echo ($perPage==50)?'selected':''; ?>>50</option>
                    <option value="100" <?php echo ($perPage==100)?'selected':''; ?>>100</option>
                </select>
                <div class="page-info">Page <?php echo $page; ?> / <?php echo $totalPages; ?> • Total: <?php echo $totalEntries; ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="detailTitle">Détails de l'œuvre</h2>
                <button class="modal-close" onclick="closeDetailModal()">&times;</button>
            </div>
            <div class="modal-body" id="detailBody"></div>
        </div>
    </div>
    
    <div id="ratingModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h2>Noter cette œuvre</h2>
                <button class="modal-close" onclick="closeRatingModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="rating-modal-stars" id="modalStars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star" data-rating="<?php echo $i; ?>"></i>
                    <?php endfor; ?>
                </div>
                <div class="modal-actions">
                    <button class="btn-cancel" onclick="closeRatingModal()">Annuler</button>
                    <button class="btn-save" onclick="saveRating()">Enregistrer</button>
                </div>
            </div>
        </div>
    </div>
    
    <div id="notification"></div>
    
    <script>
        window.allAnswersData = <?php echo json_encode($processedAnswers); ?>;
        window.serverAuth = <?php echo json_encode($isAuthenticated); ?>;
        window.serverEmail = <?php echo json_encode($userEmail); ?>;
        window.chartData = {
            wilaya: <?php echo json_encode(array_slice($wilayaStats,0,10,true)); ?>,
            ratings: <?php echo json_encode($ratingStats); ?>,
            categories: <?php echo json_encode($categories); ?>,
            totals: { total: <?php echo $totalEntries; ?>, overall: <?php echo $overallTotal ?? count($processedAnswers); ?> }
        };
        window.csrfToken = <?php echo json_encode($_SESSION['token'] ?? ''); ?>;
    </script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/app-enhancements.js"></script>
    <script src="assets/js/ux-enhancements.js"></script>
</body>
</html>
