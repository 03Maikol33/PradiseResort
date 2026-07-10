<?php

require_once __DIR__ . '/include/bootstrap.inc.php';

// Protezione pagina: richiede login ed esclude lo staff
require_login();
block_staff();

$bookingId = (int)($_GET['id'] ?? $_POST['booking_id'] ?? 0);
$today = date('Y-m-d');
$message = '';
$error = '';

if ($bookingId <= 0) {
    header('Location: ' . $config['base'] . '/profile.php');
    exit;
}

// 1. Carica la prenotazione e controlla proprietà e fattibilità modifica
$stmt = db()->prepare(
    'SELECT b.*, r.room_number, rc.name AS category_name, rc.base_price
     FROM bookings b
     JOIN rooms r ON r.id = b.room_id
     JOIN room_categories rc ON rc.id = r.category_id
     WHERE b.id = ? AND b.user_id = ?'
);
$stmt->execute([$bookingId, $_SESSION['user']['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: ' . $config['base'] . '/profile.php');
    exit;
}

// Controllo: non è possibile modificare prenotazioni passate, completate o cancellate
if ($booking['status_id'] == 4 || $booking['status_id'] == 5 || $booking['check_in_date'] <= $today) {
    header('Location: ' . $config['base'] . '/profile.php');
    exit;
}

// 2. Gestione POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checkIn = $_POST['check_in'] ?? '';
    $checkOut = $_POST['check_out'] ?? '';
    $selectedAmenities = $_POST['amenities'] ?? []; // Array di IDs

    // Validazione base date
    if (empty($checkIn) || empty($checkOut)) {
        $error = 'Le date di check-in e check-out sono obbligatorie.';
    } elseif ($checkIn <= $today) {
        $error = 'La data di check-in deve essere successiva a oggi.';
    } elseif ($checkOut <= $checkIn) {
        $error = 'La data di check-out deve essere successiva a quella di check-in.';
    } else {
        try {
            db()->beginTransaction();

            // Controllo disponibilità camera con blocco FOR UPDATE (escludendo questa prenotazione)
            $chk = db()->prepare(
                'SELECT 1 FROM bookings b
                 WHERE b.room_id = ? AND b.id <> ? AND b.status_id <> 4
                   AND b.check_in_date < ? AND b.check_out_date > ?
                 FOR UPDATE'
            );
            $chk->execute([
                $booking['room_id'],
                $bookingId,
                $checkOut,
                $checkIn
            ]);
            $isBooked = (bool)$chk->fetch();

            if ($isBooked) {
                $error = 'La camera non è disponibile per le date selezionate.';
                db()->rollBack();
            } else {
                // Calcola il prezzo totale
                $start = new DateTime($checkIn);
                $end = new DateTime($checkOut);
                $nights = $start->diff($end)->days;
                if ($nights <= 0) $nights = 1;

                $roomTotal = $nights * (float)$booking['base_price'];
                $amenitiesTotal = 0;

                if (!empty($selectedAmenities)) {
                    // Recupera i prezzi delle amenities scelte
                    $inPlaceholders = implode(',', array_fill(0, count($selectedAmenities), '?'));
                    $stmtPrices = db()->prepare("SELECT price FROM amenities WHERE id IN ($inPlaceholders)");
                    $stmtPrices->execute($selectedAmenities);
                    $prices = $stmtPrices->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($prices as $p) {
                        $amenitiesTotal += (float)$p;
                    }
                }

                $newTotalPrice = $roomTotal + $amenitiesTotal;

                // Aggiorna la prenotazione
                $update = db()->prepare(
                    'UPDATE bookings 
                     SET check_in_date = ?, check_out_date = ?, total_price = ? 
                     WHERE id = ?'
                );
                $update->execute([$checkIn, $checkOut, $newTotalPrice, $bookingId]);

                // Sincronizza i servizi extra in booking_amenities
                $delete = db()->prepare('DELETE FROM booking_amenities WHERE booking_id = ?');
                $delete->execute([$bookingId]);

                if (!empty($selectedAmenities)) {
                    $ins = db()->prepare('INSERT INTO booking_amenities (booking_id, amenity_id, quantity) VALUES (?, ?, 1)');
                    foreach ($selectedAmenities as $amenityId) {
                        $ins->execute([$bookingId, (int)$amenityId]);
                    }
                }

                // Aggiorna l'importo della fattura associata
                $updateInvoice = db()->prepare('UPDATE invoices SET total_amount = ? WHERE booking_id = ?');
                $updateInvoice->execute([$newTotalPrice, $bookingId]);

                db()->commit();

                // Reindirizza con messaggio di successo
                header('Location: ' . $config['base'] . '/profile.php?action=update_profile&msg=' . urlencode('Soggiorno modificato con successo.'));
                exit;
            }

        } catch (Exception $e) {
            db()->rollBack();
            $error = 'Errore durante la modifica della prenotazione: ' . $e->getMessage();
        }
    }
}

// 3. Gestione GET (Popolamento del form)
// Recupera tutti i servizi extra e indica quelli attualmente scelti
$stmtSelected = db()->prepare('SELECT amenity_id FROM booking_amenities WHERE booking_id = ?');
$stmtSelected->execute([$bookingId]);
$selectedAmenityIds = $stmtSelected->fetchAll(PDO::FETCH_COLUMN);

$stmtAmenities = db()->query("SELECT id, name, description, price FROM amenities ORDER BY price ASC");
$allAmenities = $stmtAmenities->fetchAll();

$skin = new_page($config['skin']);
$skin->setContent('title',      'Modifica Soggiorno');
$skin->setContent('year',       date('Y'));
$skin->setContent('base',       $config['base']);
$skin->setContent('skin',       $config['skin']);
$skin->setContent('is_logged',  !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name',  $_SESSION['user']['name'] ?? '');
$cartCountVal = get_cart_count();
$skin->setContent('cart_count', $cartCountVal > 0 ? '1' : '');
$skin->setContent('cart_badge', $cartCountVal > 0 ? " ($cartCountVal)" : '');

$block = new_block('edit-booking');
$block->setContent('booking_id',              $bookingId);
$block->setContent('category_name',           htmlspecialchars($booking['category_name']));
$block->setContent('room_number',             htmlspecialchars($booking['room_number']));
$block->setContent('base_price',              $booking['base_price']);
$block->setContent('base_price_formatted',    number_format($booking['base_price'], 2, ',', '.'));
$block->setContent('check_in',                $booking['check_in_date']);
$block->setContent('check_out',               $booking['check_out_date']);
$block->setContent('total_price',             number_format($booking['total_price'], 2, ',', '.'));
$block->setContent('error',                   $error);

// Loop dei servizi aggiuntivi
foreach ($allAmenities as $a) {
    $isChecked = in_array($a['id'], $selectedAmenityIds);
    $block->setContent('amenity_id',             $a['id']);
    $block->setContent('amenity_name',           htmlspecialchars($a['name']));
    $block->setContent('amenity_description',    htmlspecialchars($a['description']));
    $block->setContent('amenity_price',          number_format($a['price'], 2, ',', '.'));
    $block->setContent('amenity_raw_price',      $a['price']);
    $block->setContent('amenity_is_checked',     $isChecked ? 'checked' : '');
    $block->setContent('amenity_selected_class', $isChecked ? 'selected' : '');
}

$skin->setContent('body', $block->get());
$skin->close();
