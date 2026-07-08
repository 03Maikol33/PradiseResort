<?php

require_once __DIR__ . '/include/bootstrap.inc.php';

// Protezione pagina: richiede login
require_login();

$bookingId = (int)($_GET['id'] ?? 0);

// Carica la prenotazione confermata legata all'utente corrente
$stmt = db()->prepare(
    'SELECT b.id, b.check_in_date, b.check_out_date, b.total_price,
            c.name AS category_name, r.room_number
     FROM bookings b
     JOIN rooms r ON r.id = b.room_id
     JOIN room_categories c ON c.id = r.category_id
     WHERE b.id = ? AND b.user_id = ?'
);
$stmt->execute([$bookingId, $_SESSION['user']['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: ' . $config['base'] . '/rooms.php');
    exit;
}

$start = new DateTime($booking['check_in_date']);
$end = new DateTime($booking['check_out_date']);
$nights = $start->diff($end)->days;

$block = new_block('booking-success');

// Recupera i servizi extra associati alla prenotazione
$stmtAmenities = db()->prepare(
    'SELECT a.name, a.price
     FROM booking_amenities ba
     JOIN amenities a ON a.id = ba.amenity_id
     WHERE ba.booking_id = ?'
);
$stmtAmenities->execute([$booking['id']]);
$bookingAmenities = $stmtAmenities->fetchAll();

foreach ($bookingAmenities as $ba) {
    $block->setContent('booking_amenity_name',  htmlspecialchars($ba['name']));
    $block->setContent('booking_amenity_price', number_format($ba['price'], 2, ',', '.'));
}

$skin = new_page($config['skin']);
$skin->setContent('title',      'Prenotazione Confermata!');
$skin->setContent('year',       date('Y'));
$skin->setContent('base',       $config['base']);
$skin->setContent('skin',       $config['skin']);
$skin->setContent('is_logged',  !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name',  $_SESSION['user']['name'] ?? '');
$cartCountVal = get_cart_count();
$skin->setContent('cart_count', $cartCountVal > 0 ? '1' : '');
$skin->setContent('cart_badge', $cartCountVal > 0 ? " ($cartCountVal)" : '');
$block->setContent('booking_id',    $booking['id']);
$block->setContent('category_name', htmlspecialchars($booking['category_name']));
$block->setContent('room_number',   htmlspecialchars($booking['room_number']));
$block->setContent('check_in',      $start->format('d/m/Y'));
$block->setContent('check_out',     $end->format('d/m/Y'));
$block->setContent('nights',        $nights);
$block->setContent('total_price',   number_format($booking['total_price'], 2, ',', '.'));
$block->setContent('has_booking_amenities', !empty($bookingAmenities) ? '1' : '');

$skin->setContent('body', $block->get());
$skin->close();
