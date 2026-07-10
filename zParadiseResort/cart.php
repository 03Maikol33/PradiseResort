<?php

require_once __DIR__ . '/include/bootstrap.inc.php';

// Protezione pagina: richiede login ed esclude lo staff
require_login();
block_staff();

$action = $_GET['action'] ?? '';
$error = $_GET['error'] ?? '';
$message = '';
$today = date('Y-m-d');
$userId = $_SESSION['user']['id'];

// 1. Azione: Rimuovi prenotazione "In Cart" dal carrello
if ($action === 'remove') {
    $bookingId = (int)($_GET['id'] ?? 0);
    if ($bookingId > 0) {
        try {
            db()->beginTransaction();

            // Verifica che la prenotazione appartenga all'utente loggato ed sia "In Cart"
            $stmt = db()->prepare('SELECT 1 FROM bookings WHERE id = ? AND user_id = ? AND status_id = 1');
            $stmt->execute([$bookingId, $userId]);
            if ($stmt->fetch()) {
                // Elimina fattura
                $delInvoice = db()->prepare('DELETE FROM invoices WHERE booking_id = ?');
                $delInvoice->execute([$bookingId]);

                // Elimina associazione amenities
                $delAmenities = db()->prepare('DELETE FROM booking_amenities WHERE booking_id = ?');
                $delAmenities->execute([$bookingId]);

                // Elimina prenotazione
                $delBooking = db()->prepare('DELETE FROM bookings WHERE id = ?');
                $delBooking->execute([$bookingId]);

                db()->commit();
            } else {
                db()->rollBack();
            }
        } catch (Exception $e) {
            db()->rollBack();
            $error = 'Errore durante la rimozione della camera: ' . $e->getMessage();
        }
    }
    header('Location: ' . $config['base'] . '/cart.php' . (!empty($error) ? '?error=' . urlencode($error) : ''));
    exit;
}

// 2. Azione AJAX: Toggle dei servizi extra
if ($action === 'toggle_amenity') {
    header('Content-Type: application/json');
    $bookingId = (int)($_GET['booking_id'] ?? 0);
    $amenityId = (int)($_GET['amenity_id'] ?? 0);

    if ($bookingId <= 0 || $amenityId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Dati non validi']);
        exit;
    }

    try {
        db()->beginTransaction();

        // Verifica proprietà e stato 'In Cart'
        $stmtChk = db()->prepare('SELECT b.*, rc.base_price FROM bookings b JOIN rooms r ON r.id = b.room_id JOIN room_categories rc ON rc.id = r.category_id WHERE b.id = ? AND b.user_id = ? AND b.status_id = 1 FOR UPDATE');
        $stmtChk->execute([$bookingId, $userId]);
        $booking = $stmtChk->fetch();

        if (!$booking) {
            echo json_encode(['success' => false, 'error' => 'Prenotazione non trovata o non autorizzata']);
            db()->rollBack();
            exit;
        }

        // Controlla se l'amenity è già associata
        $stmtAmenity = db()->prepare('SELECT 1 FROM booking_amenities WHERE booking_id = ? AND amenity_id = ?');
        $stmtAmenity->execute([$bookingId, $amenityId]);
        $hasAmenity = (bool)$stmtAmenity->fetch();

        if ($hasAmenity) {
            $del = db()->prepare('DELETE FROM booking_amenities WHERE booking_id = ? AND amenity_id = ?');
            $del->execute([$bookingId, $amenityId]);
        } else {
            $ins = db()->prepare('INSERT INTO booking_amenities (booking_id, amenity_id, quantity) VALUES (?, ?, 1)');
            $ins->execute([$bookingId, $amenityId]);
        }

        // Ricalcola il totale della singola prenotazione
        $start = new DateTime($booking['check_in_date']);
        $end = new DateTime($booking['check_out_date']);
        $nights = $start->diff($end)->days;
        if ($nights <= 0) $nights = 1;

        $roomCost = $nights * (float)$booking['base_price'];

        $stmtSum = db()->prepare('SELECT SUM(a.price) as total FROM booking_amenities ba JOIN amenities a ON a.id = ba.amenity_id WHERE ba.booking_id = ?');
        $stmtSum->execute([$bookingId]);
        $amenitiesTotal = (float)($stmtSum->fetch()['total'] ?? 0);

        $newTotalPrice = $roomCost + $amenitiesTotal;

        // Aggiorna tabella bookings ed invoices
        $update = db()->prepare('UPDATE bookings SET total_price = ? WHERE id = ?');
        $update->execute([$newTotalPrice, $bookingId]);

        $updateInvoice = db()->prepare('UPDATE invoices SET total_amount = ? WHERE booking_id = ?');
        $updateInvoice->execute([$newTotalPrice, $bookingId]);

        db()->commit();

        // Ricalcola il totale complessivo di tutto il carrello
        $stmtGrandTotal = db()->prepare('SELECT SUM(total_price) as grand FROM bookings WHERE user_id = ? AND status_id = 1');
        $stmtGrandTotal->execute([$userId]);
        $cartGrandTotal = (float)($stmtGrandTotal->fetch()['grand'] ?? 0);

        echo json_encode([
            'success' => true,
            'booking_id' => $bookingId,
            'booking_total_price' => number_format($newTotalPrice, 2, ',', '.'),
            'cart_grand_total' => number_format($cartGrandTotal, 2, ',', '.')
        ]);
        exit;

    } catch (Exception $e) {
        db()->rollBack();
        echo json_encode(['success' => false, 'error' => 'Errore server: ' . $e->getMessage()]);
        exit;
    }
}

// 3. Azione: Conferma tutte le prenotazioni del carrello
if ($action === 'confirm') {
    try {
        db()->beginTransaction();

        // Seleziona tutte le prenotazioni correntemente "In Cart" dell'utente
        $stmt = db()->prepare('SELECT * FROM bookings WHERE user_id = ? AND status_id = 1 FOR UPDATE');
        $stmt->execute([$userId]);
        $cartBookings = $stmt->fetchAll();

        if (empty($cartBookings)) {
            header('Location: ' . $config['base'] . '/cart.php');
            exit;
        }

        // Ricontrolla sovrapposizioni reali con prenotazioni confermate/completate
        foreach ($cartBookings as $cb) {
            $chk = db()->prepare(
                'SELECT 1 FROM bookings b
                 WHERE b.room_id = ? AND b.id <> ? AND b.status_id NOT IN (1, 4)
                   AND b.check_in_date < ? AND b.check_out_date > ?
                 FOR UPDATE'
            );
            $chk->execute([
                $cb['room_id'],
                $cb['id'],
                $cb['check_out_date'],
                $cb['check_in_date']
            ]);
            if ($chk->fetch()) {
                throw new Exception('Una delle camere scelte è stata prenotata da un altro utente durante la tua sessione. Rimuovila per procedere.');
            }
        }

        // Aggiorna lo stato in "Confirmed" (status_id = 3)
        $update = db()->prepare('UPDATE bookings SET status_id = 3 WHERE user_id = ? AND status_id = 1');
        $update->execute([$userId]);

        db()->commit();

        header('Location: ' . $config['base'] . '/profile.php?msg=' . urlencode('Prenotazioni confermate con successo!'));
        exit;

    } catch (Exception $e) {
        db()->rollBack();
        $error = $e->getMessage();
    }
}

// 4. Caricamento ordinario (GET)
// Conta quante stanze ci sono nel carrello per l'utente corrente
$stmtCount = db()->prepare('SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND status_id = 1');
$stmtCount->execute([$userId]);
$cartCountVal = (int)($stmtCount->fetch()['count'] ?? 0);

// Carica tutte le prenotazioni in carrello
$stmt = db()->prepare(
    'SELECT b.*, r.room_number, rc.name AS category_name, rc.base_price
     FROM bookings b
     JOIN rooms r ON r.id = b.room_id
     JOIN room_categories rc ON rc.id = r.category_id
     WHERE b.user_id = ? AND b.status_id = 1
     ORDER BY b.id ASC'
);
$stmt->execute([$userId]);
$cartBookings = $stmt->fetchAll();

$isCartEmpty = empty($cartBookings) ? '1' : '';

$skin = new_page($config['skin']);
$skin->setContent('title',      'Carrello Prenotazioni');
$skin->setContent('year',       date('Y'));
$skin->setContent('base',       $config['base']);
$skin->setContent('skin',       $config['skin']);
$skin->setContent('is_logged',  !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name',  $_SESSION['user']['name'] ?? '');
$skin->setContent('cart_count', $cartCountVal > 0 ? '1' : '');
$skin->setContent('cart_badge', $cartCountVal > 0 ? " ($cartCountVal)" : '');

$block = new_block('cart');
$block->setContent('error',         $error);
$block->setContent('is_cart_empty', $isCartEmpty);

$grandTotal = 0.0;

if (!$isCartEmpty) {
    // Carica tutti i servizi extra
    $stmtAmenities = db()->query("SELECT id, name, description, price FROM amenities ORDER BY price ASC");
    $allAmenities = $stmtAmenities->fetchAll();

    foreach ($cartBookings as $b) {
        $grandTotal += (float)$b['total_price'];

        $start = new DateTime($b['check_in_date']);
        $end = new DateTime($b['check_out_date']);
        $nights = $start->diff($end)->days;
        if ($nights <= 0) $nights = 1;

        // Recupera gli ID delle amenities scelte per questa prenotazione
        $stmtSel = db()->prepare('SELECT amenity_id FROM booking_amenities WHERE booking_id = ?');
        $stmtSel->execute([$b['id']]);
        $selectedAmenityIds = $stmtSel->fetchAll(PDO::FETCH_COLUMN);

        // Genera l'HTML dei servizi extra per questa prenotazione in PHP per evitare loop DTML annidati
        $amenitiesHtml = '';
        foreach ($allAmenities as $a) {
            $isChecked = in_array($a['id'], $selectedAmenityIds);
            $selectedClass = $isChecked ? 'selected' : '';
            $checkedAttr = $isChecked ? 'checked' : '';
            $priceFormatted = number_format($a['price'], 2, ',', '.');
            
            $amenitiesHtml .= '
            <label class="amenity-card-item ' . $selectedClass . '" for="amenity_' . $b['id'] . '_' . $a['id'] . '" id="card_amenity_' . $b['id'] . '_' . $a['id'] . '">
                <div style="display: flex; align-items: center; gap: 15px; flex: 1;">
                    <div class="tiffany-checkbox-wrapper">
                        <input type="checkbox" id="amenity_' . $b['id'] . '_' . $a['id'] . '" class="amenity-checkbox" data-booking-id="' . $b['id'] . '" data-amenity-id="' . $a['id'] . '" data-price="' . $a['price'] . '" ' . $checkedAttr . ' />
                    </div>
                    <div>
                        <span style="font-weight: 700; color: #1f2b37; margin: 0; display: block; font-size: 0.95rem;">' . htmlspecialchars($a['name']) . '</span>
                        <span style="font-size: 0.85rem; color: #718096; display: block;">' . htmlspecialchars($a['description']) . '</span>
                    </div>
                </div>
                <div style="font-weight: 800; color: #0abab5; font-size: 1.05rem;">
                    +&euro;' . $priceFormatted . '
                </div>
            </label>';
        }

        $block->setContent('booking_id',              $b['id']);
        $block->setContent('booking_category_name',   htmlspecialchars($b['category_name']));
        $block->setContent('booking_room_number',     htmlspecialchars($b['room_number']));
        $block->setContent('booking_check_in',         $start->format('d/m/Y'));
        $block->setContent('booking_check_out',        $end->format('d/m/Y'));
        $block->setContent('booking_nights',           $nights);
        $block->setContent('booking_base_price',       number_format($b['base_price'], 2, ',', '.'));
        $block->setContent('booking_total_price',      number_format($b['total_price'], 2, ',', '.'));
        $block->setContent('booking_amenities_html',   $amenitiesHtml);
        $block->setContent('booking_remove_url',       $config['base'] . '/cart.php?action=remove&id=' . $b['id']);
    }
}

$block->setContent('grand_total_price', number_format($grandTotal, 2, ',', '.'));
$block->setContent('confirm_url',       $config['base'] . '/cart.php?action=confirm');

$skin->setContent('body', $block->get());
$skin->close();
