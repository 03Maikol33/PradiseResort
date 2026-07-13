<?php
require_once __DIR__ . '/include/bootstrap.inc.php';

// Protezione pagina: richiede login ed esclude lo staff
require_login();
block_staff();
require_service();


$message = '';
$error = '';

// Gestione dei messaggi in sessione (Pattern Post-Redirect-Get)
if (isset($_SESSION['report_success'])) {
    $message = $_SESSION['report_success'];
    unset($_SESSION['report_success']);
}
if (isset($_SESSION['report_error'])) {
    $error = $_SESSION['report_error'];
    unset($_SESSION['report_error']);
}

// Recupera le prenotazioni attive per l'utente loggato nel giorno corrente
$today = date('Y-m-d');
$stmt = db()->prepare('
    SELECT b.id as booking_id, b.room_id, r.room_number
    FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    WHERE b.user_id = ?
      AND b.status_id = 3
      AND b.check_in_date <= ?
      AND b.check_out_date >= ?
');
$stmt->execute([$_SESSION['user']['id'], $today, $today]);
$active_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$skin = new_page($config['skin']);
$skin->setContent('title',      'Invia Segnalazione');
$skin->setContent('year',       date('Y'));
$skin->setContent('base',       $config['base']);
$skin->setContent('skin',       $config['skin']);
$skin->setContent('is_logged',  !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name', !empty($_SESSION['user']['name']) ? explode(' ', $_SESSION['user']['name'])[0] : '');
$cartCountVal = get_cart_count();
$skin->setContent('cart_count', $cartCountVal > 0 ? '1' : '');
$skin->setContent('cart_badge', $cartCountVal > 0 ? " ($cartCountVal)" : '');

$block = new_block('report-ticket');
$block->setContent('base', $config['base']);
$block->setContent('skin', $config['skin']);
$block->setContent('message', $message);
$block->setContent('error', $error);
$block->setContent('dateNow', date('Y-m-d\TH:i'));

if (!empty($active_bookings)) {
    $block->setContent('has_active_bookings', '1');
    foreach ($active_bookings as $booking) {
        $block->setContent('room_id', $booking['room_id']);
        $block->setContent('room_number', htmlspecialchars($booking['room_number']));
    }
} else {
    $block->setContent('has_active_bookings', '');
}

$skin->setContent('body', $block->get());
$skin->close();
