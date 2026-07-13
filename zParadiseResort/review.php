<?php
require_once __DIR__ . '/include/bootstrap.inc.php';

// Protezione pagina: richiede login ed esclude lo staff
require_login();
block_staff();
require_service();


$message = '';
$error = '';

// Gestione dei messaggi in sessione (Pattern Post-Redirect-Get)
if (isset($_SESSION['review_success'])) {
    $message = $_SESSION['review_success'];
    unset($_SESSION['review_success']);
} elseif (isset($_SESSION['report_success'])) {
    $message = $_SESSION['report_success'];
    unset($_SESSION['report_success']);
}

if (isset($_SESSION['review_error'])) {
    $error = $_SESSION['review_error'];
    unset($_SESSION['review_error']);
} elseif (isset($_SESSION['report_error'])) {
    $error = $_SESSION['report_error'];
    unset($_SESSION['report_error']);
}

// Recupera le tipologie di camera dei soggiorni attivi o passati per l'utente loggato
$today = date('Y-m-d');
$stmt = db()->prepare('
    SELECT DISTINCT rc.id as room_category_id, rc.name as category_name
    FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    JOIN room_categories rc ON rc.id = r.category_id
    WHERE b.user_id = ?
      AND b.status_id IN (3, 5)
      AND b.check_in_date <= ?
    ORDER BY rc.name ASC
');
$stmt->execute([$_SESSION['user']['id'], $today]);
$eligible_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$skin = new_page($config['skin']);
$skin->setContent('title',      'Invia Recensione');
$skin->setContent('year',       date('Y'));
$skin->setContent('base',       $config['base']);
$skin->setContent('skin',       $config['skin']);
$skin->setContent('is_logged',  !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name',  $_SESSION['user']['name'] ?? '');
$cartCountVal = get_cart_count();
$skin->setContent('cart_count', $cartCountVal > 0 ? '1' : '');
$skin->setContent('cart_badge', $cartCountVal > 0 ? " ($cartCountVal)" : '');

$block = new_block('review');
$block->setContent('base', $config['base']);
$block->setContent('skin', $config['skin']);
$block->setContent('message', $message);
$block->setContent('error', $error);
$block->setContent('dateNow', date('Y-m-d\TH:i'));

if (!empty($eligible_categories)) {
    $block->setContent('has_active_bookings', '1');
    foreach ($eligible_categories as $cat) {
        $block->setContent('room_category_id', $cat['room_category_id']);
        $block->setContent('category_name', htmlspecialchars($cat['category_name']));
    }
} else {
    $block->setContent('has_active_bookings', '');
}

$skin->setContent('body', $block->get());
$skin->close();
