<?php
require_once __DIR__ . '/include/bootstrap.inc.php';

require_login();
block_staff();
require_service();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservation_date = trim($_POST['reservation_date'] ?? '');
    $meal_type = trim($_POST['meal_type'] ?? '');
    $reservation_time = trim($_POST['reservation_time'] ?? '');
    $guests = (int)($_POST['guests'] ?? 1);

    $today = date('Y-m-d');
    $minBookingTime = date('H:i', strtotime('+2 hours'));

    if (empty($reservation_date) || empty($meal_type) || empty($reservation_time) || $guests < 1) {
        $error = 'Compila tutti i campi correttamente.';
    } elseif ($reservation_date < $today) {
        $error = 'Non è possibile prenotare per una data passata.';
    } elseif ($reservation_date === $today && $reservation_time < $minBookingTime) {
        $error = 'Devi prenotare con almeno 2 ore di preavviso per la giornata di oggi.';
    } else {
        try {
            $stmt = db()->prepare('INSERT INTO restaurant_reservations (user_id, reservation_date, meal_type, reservation_time, guests, status) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $_SESSION['user']['id'],
                $reservation_date,
                $meal_type,
                $reservation_time,
                $guests,
                'Pending'
            ]);
            $message = 'Prenotazione effettuata con successo! Ti aspettiamo.';
        } catch (Exception $e) {
            $error = 'Errore durante la prenotazione: Riprova più tardi.';
        }
    }
}

$skin = new_page($config['skin']);
$skin->setContent('title',      'Prenota Tavolo');
$skin->setContent('year',       date('Y'));
$skin->setContent('base',       $config['base']);
$skin->setContent('skin',       $config['skin']);
$skin->setContent('is_logged',  !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name',  $_SESSION['user']['name'] ?? '');
$cartCountVal = get_cart_count();
$skin->setContent('cart_count', $cartCountVal > 0 ? '1' : '');
$skin->setContent('cart_badge', $cartCountVal > 0 ? " ($cartCountVal)" : '');

$block = new_block('restaurant_book');
$block->setContent('base', $config['base']);
$block->setContent('skin', $config['skin']);
$block->setContent('message', $message);
$block->setContent('error', $error);
$block->setContent('min_date', date('Y-m-d'));
$block->setContent('min_time_today', date('H:i', strtotime('+2 hours')));

$skin->setContent('body', $block->get());
$skin->close();
