<?php

require_once __DIR__ . '/include/bootstrap.inc.php';

// Protezione pagina: richiede login
require_login();

$action = $_GET['action'] ?? '';
$error = '';

if ($action === 'remove') {
    // Svuota il carrello
    unset($_SESSION['cart']);
    header('Location: ' . $config['base'] . '/cart.php');
    exit;
}

if ($action === 'confirm' && !empty($_SESSION['cart'])) {
    // Conferma la prenotazione
    $cart = $_SESSION['cart'];

    // Recupera l'id dello stato 'Confirmed'
    $statusRow = db()->query("SELECT id FROM booking_statuses WHERE name = 'Confirmed'")->fetch();
    $statusId = $statusRow ? $statusRow['id'] : 3;

    try {
        db()->beginTransaction();

        // 1. Ricontrolla disponibilità camera con blocco FOR UPDATE
        $chk = db()->prepare(
            'SELECT 1 FROM bookings b
             WHERE b.room_id = ? AND b.status_id <> 4
               AND b.check_in_date < ? AND b.check_out_date > ?
             FOR UPDATE'
        );
        $chk->execute([
            $cart['room_id'],
            $cart['check_out'],
            $cart['check_in']
        ]);
        $isBooked = (bool)$chk->fetch();

        if ($isBooked) {
            db()->rollBack();
            $error = 'La camera selezionata è già stata prenotata per questo periodo da un altro utente.';
        } else {
            // 2. Inserimento booking
            $ins = db()->prepare(
                'INSERT INTO bookings (user_id, room_id, status_id, check_in_date, check_out_date, total_price)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([
                $_SESSION['user']['id'],
                $cart['room_id'],
                $statusId,
                $cart['check_in'],
                $cart['check_out'],
                $cart['total_price']
            ]);
            $bookingId = db()->lastInsertId();

            // 3. Creazione fattura (invoice) associata in stato unpaid
            $insInvoice = db()->prepare(
                'INSERT INTO invoices (booking_id, total_amount, payment_status) VALUES (?, ?, \'unpaid\')'
            );
            $insInvoice->execute([$bookingId, $cart['total_price']]);

            db()->commit();

            // Svuota carrello in sessione
            unset($_SESSION['cart']);

            // Reindirizza alla pagina di successo
            header('Location: ' . $config['base'] . '/booking-success.php?id=' . $bookingId);
            exit;
        }
    } catch (Exception $e) {
        db()->rollBack();
        $error = 'Errore imprevisto durante il salvataggio della prenotazione: ' . $e->getMessage();
    }
}

$skin = new_page($config['skin']);
$skin->setContent('title',      'Carrello Prenotazioni');
$skin->setContent('year',       date('Y'));
$skin->setContent('base',       $config['base']);
$skin->setContent('skin',       $config['skin']);
$skin->setContent('is_logged',  !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name',  $_SESSION['user']['name'] ?? '');
$skin->setContent('cart_count', !empty($_SESSION['cart']) ? '1' : '');
$skin->setContent('cart_badge', !empty($_SESSION['cart']) ? ' (1)' : '');

$block = new_block('cart');
$block->setContent('error', $error);

$isCartEmpty = empty($_SESSION['cart']) ? '1' : '';
$block->setContent('is_cart_empty', $isCartEmpty);

if (!$isCartEmpty) {
    $cart = $_SESSION['cart'];
    $block->setContent('category_name', htmlspecialchars($cart['category_name']));
    $block->setContent('room_number',   htmlspecialchars($cart['room_number']));
    $block->setContent('check_in',      date('d/m/Y', strtotime($cart['check_in'])));
    $block->setContent('check_out',     date('d/m/Y', strtotime($cart['check_out'])));
    $block->setContent('nights',        $cart['nights']);
    $block->setContent('base_price',    number_format($cart['base_price'], 2, ',', '.'));
    $block->setContent('total_price',   number_format($cart['total_price'], 2, ',', '.'));
    
    $confirmUrl = $config['base'] . '/cart.php?action=confirm';
    $removeUrl = $config['base'] . '/cart.php?action=remove';
    $block->setContent('confirm_url', $confirmUrl);
    $block->setContent('remove_url',  $removeUrl);
} else {
    $block->setContent('category_name', '');
    $block->setContent('room_number',   '');
    $block->setContent('check_in',      '');
    $block->setContent('check_out',     '');
    $block->setContent('nights',        '');
    $block->setContent('base_price',    '');
    $block->setContent('total_price',   '');
    $block->setContent('confirm_url',   '');
    $block->setContent('remove_url',    '');
}

$skin->setContent('body', $block->get());
$skin->close();
