<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_admin();

$db = db();
$successMsg = $_SESSION['success_msg'] ?? '';
$errorMsg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Gestione Eliminazione Recensione via POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_review') {
        $reviewId = (int)($_POST['review_id'] ?? 0);
        if ($reviewId > 0) {
            try {
                $checkStmt = $db->prepare("SELECT 1 FROM reviews WHERE id = ?");
                $checkStmt->execute([$reviewId]);
                if ($checkStmt->fetch()) {
                    $deleteStmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
                    $deleteStmt->execute([$reviewId]);
                    $_SESSION['success_msg'] = "Recensione #{$reviewId} eliminata con successo dal database.";
                } else {
                    $_SESSION['error_msg'] = "Recensione non trovata o già eliminata.";
                }
            } catch (Exception $e) {
                $_SESSION['error_msg'] = "Errore durante l'eliminazione della recensione: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_msg'] = "ID recensione non valido.";
        }
    } else {
        $_SESSION['error_msg'] = "Azione non consentita.";
    }

    header("Location: reviews.php");
    exit;
}

$page = new_page('administration', 'frame-private');
$block = new_block('reviews');

$block->setContent('base', $config['base']);
$block->setContent('role_path', 'admin');
$block->setContent('success_msg', htmlspecialchars($successMsg));
$block->setContent('error_msg', htmlspecialchars($errorMsg));
$block->setContent('show_success', $successMsg ? '1' : '');
$block->setContent('show_error', $errorMsg ? '1' : '');

// Filtri e Parametri di Ricerca da GET
$search = trim($_GET['search'] ?? '');
$ratingFilter = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$categoryFilter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

$block->setContent('search_query', htmlspecialchars($search));

// 1. Popola le opzioni per il filtro delle valutazioni (Stelle)
for ($r = 5; $r >= 1; $r--) {
    $block->setContent('filter_rating_val', $r);
    $block->setContent('filter_rating_label', $r . ' ' . ($r == 1 ? 'Stella' : 'Stelle'));
    $block->setContent('filter_rating_selected', ($r === $ratingFilter) ? 'selected' : '');
}

// 2. Popola le opzioni per il filtro Tipologia Camera
$stmtCategories = $db->query("SELECT id, name FROM room_categories ORDER BY name ASC");
$categories = $stmtCategories->fetchAll();
foreach ($categories as $cat) {
    $block->setContent('filter_category_id', $cat['id']);
    $block->setContent('filter_category_name', htmlspecialchars($cat['name']));
    $block->setContent('filter_category_selected', ($cat['id'] == $categoryFilter) ? 'selected' : '');
}

// 3. Calcolo Statistiche / KPI totali
$stmtStats = $db->query("
    SELECT 
        COUNT(*) as total_cnt,
        ROUND(AVG(rating), 1) as avg_rating,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star_cnt,
        SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as low_rating_cnt
    FROM reviews
");
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

$block->setContent('total_reviews', $stats['total_cnt'] ?? 0);
$block->setContent('avg_rating', ($stats['avg_rating'] !== null) ? number_format($stats['avg_rating'], 1) : 'N/A');
$block->setContent('five_star_reviews', $stats['five_star_cnt'] ?? 0);
$block->setContent('low_rating_reviews', $stats['low_rating_cnt'] ?? 0);

// 4. Costruzione Query Principale Filtrata
$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(r.comment LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR rc.name LIKE ?)";
    $searchWildcard = "%{$search}%";
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

if ($ratingFilter >= 1 && $ratingFilter <= 5) {
    $whereClauses[] = "r.rating = ?";
    $params[] = $ratingFilter;
}

if ($categoryFilter > 0) {
    $whereClauses[] = "r.room_category_id = ?";
    $params[] = $categoryFilter;
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
}

$queryReviews = "
    SELECT r.*, u.first_name, u.last_name, u.email, rc.name AS category_name
    FROM reviews r
    JOIN users u ON u.id = r.user_id
    JOIN room_categories rc ON rc.id = r.room_category_id
    {$whereSql}
    ORDER BY r.created_at DESC
";

$stmtReviews = $db->prepare($queryReviews);
$stmtReviews->execute($params);
$reviewsList = $stmtReviews->fetchAll(PDO::FETCH_ASSOC);

if (!empty($reviewsList)) {
    $block->setContent('reviews_list', '1');
    foreach ($reviewsList as $rev) {
        $block->setContent('review_id', $rev['id']);
        $block->setContent('user_id', $rev['user_id']);
        $block->setContent('user_name', htmlspecialchars(trim($rev['first_name'] . ' ' . $rev['last_name'])));
        $block->setContent('user_initials', strtoupper($rev['first_name'][0] . $rev['last_name'][0]));
        $block->setContent('user_email', htmlspecialchars($rev['email']));
        $block->setContent('room_category_id', $rev['room_category_id']);
        $block->setContent('category_name', htmlspecialchars($rev['category_name']));
        $block->setContent('rating', $rev['rating']);
        
        // Genera stelle HTML
        $starsHtml = '';
        for ($s = 1; $s <= 5; $s++) {
            if ($s <= $rev['rating']) {
                $starsHtml .= '<i class="bi bi-star-fill text-warning"></i> ';
            } else {
                $starsHtml .= '<i class="bi bi-star text-muted"></i> ';
            }
        }
        $block->setContent('rating_stars', $starsHtml);
        
        // Badge colore per valutazione
        $badgeClass = 'bg-success';
        if ($rev['rating'] == 3) $badgeClass = 'bg-info text-dark';
        if ($rev['rating'] <= 2) $badgeClass = 'bg-danger';
        $block->setContent('rating_badge_class', $badgeClass);
        
        $block->setContent('comment', htmlspecialchars($rev['comment']));
        $block->setContent('created_at', date('d/m/Y H:i', strtotime($rev['created_at'])));
    }
} else {
    $block->setContent('reviews_list', '');
}

setup_backoffice_page($page, 'Amministratore', 'admin');

$page->setContent('body', $block->get());
$page->close();
