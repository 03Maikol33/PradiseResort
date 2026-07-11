<?php

require_once __DIR__ . '/include/bootstrap.inc.php';

// Protezione pagina: richiede login ed esclude lo staff
require_login();
block_staff();

// Migrazione autogestita (Self-Healing) del database
try {
    db()->query("SELECT phone FROM users LIMIT 1");
} catch (Exception $e) {
    db()->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL");
}
try {
    db()->query("SELECT image_url FROM users LIMIT 1");
} catch (Exception $e) {
    db()->query("ALTER TABLE users ADD COLUMN image_url VARCHAR(255) DEFAULT NULL");
}

$action = $_GET['action'] ?? '';
$message = $_GET['msg'] ?? '';
$error = '';
$today = date('Y-m-d');

// Gestione Aggiornamento Profilo
if ($action === 'update_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Recupera i dati correnti dal DB per fallback
    try {
        $stmtUser = db()->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
        $stmtUser->execute([$_SESSION['user']['id']]);
        $currUser = $stmtUser->fetch();
    } catch (Exception $e) {
        $currUser = null;
    }
    
    if (empty($firstName) && $currUser) {
        $firstName = $currUser['first_name'];
    }
    if (empty($lastName) && $currUser) {
        $lastName = $currUser['last_name'];
    }
    
    try {
        $stmtUpdateUser = db()->prepare('UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?');
        $stmtUpdateUser->execute([$firstName, $lastName, $phone, $_SESSION['user']['id']]);
        $message = 'Profilo aggiornato con successo.';
    } catch (Exception $e) {
        $error = 'Errore durante l\'aggiornamento del profilo: ' . $e->getMessage();
    }
}

// Sincronizza sessione utente con database
try {
    $stmtUser = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmtUser->execute([$_SESSION['user']['id']]);
    $userData = $stmtUser->fetch();
    if ($userData) {
        $_SESSION['user']['first_name'] = $userData['first_name'];
        $_SESSION['user']['last_name'] = $userData['last_name'];
        $_SESSION['user']['name'] = $userData['first_name'];
        $_SESSION['user']['surname'] = $userData['last_name'];
        $_SESSION['user']['phone'] = $userData['phone'];
        $_SESSION['user']['image_url'] = $userData['image_url'];
        $_SESSION['user']['email'] = $userData['email'];
    }
} catch (Exception $e) {
    // Silente
}

// Gestione Annullamento Prenotazione Futura
if ($action === 'cancel') {
    $bookingId = (int)($_GET['id'] ?? 0);
    if ($bookingId > 0) {
        try {
            db()->beginTransaction();

            // Recupera la prenotazione per verificarne proprietario e data
            $stmt = db()->prepare(
                'SELECT user_id, check_in_date, status_id FROM bookings WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch();

            if (!$booking) {
                $error = 'Prenotazione non trovata.';
                db()->rollBack();
            } elseif ($booking['user_id'] != $_SESSION['user']['id']) {
                $error = 'Non sei autorizzato ad annullare questa prenotazione.';
                db()->rollBack();
            } elseif ($booking['status_id'] == 4) {
                $error = 'Questa prenotazione è già stata annullata.';
                db()->rollBack();
            } elseif ($booking['check_in_date'] <= $today) {
                $error = 'Non è possibile annullare prenotazioni passate o in corso.';
                db()->rollBack();
            } else {
                // Esegui la cancellazione (status_id = 4 per 'Cancelled')
                $update = db()->prepare('UPDATE bookings SET status_id = 4 WHERE id = ?');
                $update->execute([$bookingId]);
                
                db()->commit();
                $message = 'Prenotazione annullata con successo.';
            }
        } catch (Exception $e) {
            db()->rollBack();
            $error = 'Errore imprevisto durante l\'annullamento: ' . $e->getMessage();
        }
    } else {
        $error = 'ID prenotazione non valido.';
    }
}

// Caricamento storico delle prenotazioni dell'utente (escludendo quelle in stato 'In Cart')
$stmt = db()->prepare(
    'SELECT b.id, b.check_in_date, b.check_out_date, b.total_price, b.status_id, b.staff_notes,
            bs.name AS status_name, rc.name AS category_name, r.room_number
     FROM bookings b
     JOIN rooms r ON r.id = b.room_id
     JOIN room_categories rc ON rc.id = r.category_id
     JOIN booking_statuses bs ON bs.id = b.status_id
     WHERE b.user_id = ? AND b.status_id <> 1
     ORDER BY b.check_in_date DESC'
);
$stmt->execute([$_SESSION['user']['id']]);
$bookings = $stmt->fetchAll();

$skin = new_page($config['skin']);
$skin->setContent('title',      'Area Personale - Storico Prenotazioni');
$skin->setContent('year',       date('Y'));
$skin->setContent('base',       $config['base']);
$skin->setContent('skin',       $config['skin']);
$skin->setContent('is_logged',  !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name',  $_SESSION['user']['name'] ?? '');
$cartCountVal = get_cart_count();
$skin->setContent('cart_count', $cartCountVal > 0 ? '1' : '');
$skin->setContent('cart_badge', $cartCountVal > 0 ? " ($cartCountVal)" : '');

$block = new_block('profile');
$block->setContent('message', $message);
$block->setContent('error',   $error);

// Informazioni Utente (caricate direttamente dal DB per massima robustezza)
try {
    $stmtUser = db()->prepare('SELECT first_name, last_name, email, phone FROM users WHERE id = ?');
    $stmtUser->execute([$_SESSION['user']['id']]);
    $userData = $stmtUser->fetch();
} catch (Exception $e) {
    $userData = null;
}

$firstName = $userData['first_name'] ?? $_SESSION['user']['name'] ?? '';
$lastName = $userData['last_name'] ?? $_SESSION['user']['surname'] ?? '';
$phone = $userData['phone'] ?? '';
$email = $userData['email'] ?? $_SESSION['user']['email'] ?? '';

$initial = mb_substr($firstName, 0, 1);
$block->setContent('profile_first_name', htmlspecialchars($firstName));
$block->setContent('profile_last_name',  htmlspecialchars($lastName));
$block->setContent('profile_email',      htmlspecialchars($email));
$block->setContent('profile_initial',    htmlspecialchars($initial));
$block->setContent('profile_phone',      htmlspecialchars($phone));

$activeBookingsHtml = '';
$pastBookingsHtml = '';

foreach ($bookings as $b) {
    $start = new DateTime($b['check_in_date']);
    $end = new DateTime($b['check_out_date']);
    $nights = $start->diff($end)->days;
    if ($nights <= 0) $nights = 1;

    // Recupera servizi extra associati a questa specifica prenotazione
    $stmtAmenities = db()->prepare(
        'SELECT a.name
         FROM booking_amenities ba
         JOIN amenities a ON a.id = ba.amenity_id
         WHERE ba.booking_id = ?'
    );
    $stmtAmenities->execute([$b['id']]);
    $amenities = $stmtAmenities->fetchAll(PDO::FETCH_COLUMN);
    
    $amenitiesText = !empty($amenities) ? implode(', ', $amenities) : 'Nessuno';

    // Calcola se la prenotazione è annullabile (futura e non già annullata)
    $isCancellable = ($b['check_in_date'] > $today && $b['status_id'] != 4 && $b['status_id'] != 5);

    // Genera il badge di stato con classe CSS elegante
    $badgeClass = '';
    switch ($b['status_id']) {
        case 3: // Confirmed
            $badgeClass = 'badge-confirmed';
            break;
        case 4: // Cancelled
            $badgeClass = 'badge-cancelled';
            break;
        case 5: // Completed
            $badgeClass = 'badge-completed';
            break;
        default:
            $badgeClass = 'badge-pending';
    }
    
    $statusHtml = '<span class="premium-status-badge ' . $badgeClass . '">' . htmlspecialchars($b['status_name']) . '</span>';

    // Genera i pulsanti di azione HTML
    $actionsHtml = '';
    if ($isCancellable) {
        $editUrl = $config['base'] . '/edit-booking.php?id=' . $b['id'];
        $cancelUrl = $config['base'] . '/profile.php?action=cancel&id=' . $b['id'];
        $actionsHtml = '<div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">' .
                       '<a href="' . $editUrl . '" class="btn-elite-edit"><i class="fas fa-edit mr-1"></i> Modifica</a>' .
                       '<a href="' . $cancelUrl . '" class="btn-elite-cancel" onclick="return confirm(\'Sei sicuro di voler annullare questa prenotazione? L\\\'operazione è irreversibile.\');"><i class="fas fa-times-circle mr-1"></i> Annulla</a>' .
                       '</div>';
    }

    $notesHtml = '';
    if (!empty($b['staff_notes'])) {
        $notesHtml = '
        <div style="background-color: #fffbeb; color: #b45309; border: 1px solid #fef3c7; border-left: 4px solid #f59e0b; padding: 10px 15px; border-radius: 6px; margin: 15px 0 10px; font-size: 0.9rem; display: flex; align-items: start; gap: 8px;">
            <i class="fas fa-exclamation-triangle" style="margin-top: 3px; flex-shrink: 0;"></i>
            <div><strong>Avviso dello Staff:</strong> ' . htmlspecialchars($b['staff_notes']) . '</div>
        </div>';
    }

    $cardHtml = '
    <div class="booking-history-card">
        <div class="booking-header">
            <h5 class="booking-title">Prenotazione #' . $b['id'] . '</h5>
            <div>' . $statusHtml . '</div>
        </div>
        ' . $notesHtml . '
        
        <div class="booking-grid">
            <div class="booking-meta-item">
                <span class="booking-meta-label">Sistemazione</span>
                <span class="booking-meta-value"><i class="fas fa-bed"></i> ' . htmlspecialchars($b['category_name']) . ' (Camera ' . htmlspecialchars($b['room_number']) . ')</span>
            </div>
            <div class="booking-meta-item">
                <span class="booking-meta-label">Periodo</span>
                <span class="booking-meta-value"><i class="far fa-calendar-alt"></i> ' . $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y') . '</span>
            </div>
            <div class="booking-meta-item">
                <span class="booking-meta-label">Durata</span>
                <span class="booking-meta-value"><i class="fas fa-moon"></i> ' . $nights . ' Notti</span>
            </div>
        </div>
        
        <div class="booking-grid border-top pt-3 mt-3">
            <div class="booking-meta-item" style="grid-column: span 2;">
                <span class="booking-meta-label">Servizi Extra Inclusi</span>
                <span class="booking-meta-value" style="font-weight: 600; color: #4a5568;"><i class="fas fa-concierge-bell"></i> ' . htmlspecialchars($amenitiesText) . '</span>
            </div>
            <div class="booking-meta-item text-md-right d-flex flex-column justify-content-between align-items-md-end">
                <div class="mb-2">
                    <span class="booking-meta-label">Totale</span>
                    <span style="font-size: 1.3rem; color: #0abab5; font-weight: 900; display: block;">&euro;' . number_format($b['total_price'], 2, ',', '.') . '</span>
                </div>
                <div>
                    ' . $actionsHtml . '
                </div>
            </div>
        </div>
    </div>';

    // Seleziona la colonna corretta (active = 2, 3; past = 4, 5)
    if ($b['status_id'] == 3 || $b['status_id'] == 2) {
        $activeBookingsHtml .= $cardHtml;
    } else {
        $pastBookingsHtml .= $cardHtml;
    }
}

// Messaggi di fallback se vuoti
if (empty($activeBookingsHtml)) {
    $activeBookingsHtml = '
    <div class="text-center p-4" style="background: rgba(255, 255, 255, 0.4); border: 1px dashed #cbd5e0; border-radius: 16px;">
        <span style="font-size: 2.5rem; color: #cbd5e0; margin-bottom: 10px; display: block;"><i class="far fa-calendar-times text-muted"></i></span>
        <h6 style="color: #718096; font-weight: 600; margin-bottom: 5px;">Nessuna prenotazione attiva</h6>
        <p style="color: #a0aec0; font-size: 0.85rem; margin-bottom: 15px;">Pianifica subito il tuo prossimo soggiorno da sogno!</p>
        <a href="' . $config['base'] . '/rooms.php" class="btn-elite-edit" style="display: inline-block; padding: 6px 16px; font-size: 0.8rem; text-decoration: none;">Esplora Camere</a>
    </div>';
}
if (empty($pastBookingsHtml)) {
    $pastBookingsHtml = '
    <div class="text-center p-4" style="background: rgba(255, 255, 255, 0.4); border: 1px dashed #cbd5e0; border-radius: 16px;">
        <h6 style="color: #a0aec0; font-weight: 600; font-size: 0.9rem; margin: 0;">Nessuna prenotazione passata o annullata.</h6>
    </div>';
}

$block->setContent('active_bookings_html', $activeBookingsHtml);
$block->setContent('past_bookings_html',   $pastBookingsHtml);

// Caricamento prenotazioni Ristorante
$stmtRest = db()->prepare(
    'SELECT id, reservation_date, meal_type, reservation_time, guests, status
     FROM restaurant_reservations
     WHERE user_id = ?
     ORDER BY reservation_date DESC, reservation_time DESC'
);
$stmtRest->execute([$_SESSION['user']['id']]);
$restBookings = $stmtRest->fetchAll();

$restBookingsHtml = '';
foreach ($restBookings as $rb) {
    $rDate = new DateTime($rb['reservation_date']);
    $statusClass = '';
    $statusName = $rb['status'];
    
    if ($statusName === 'Confirmed') {
        $statusClass = 'badge-confirmed';
        $statusName = 'Confermata';
    } elseif ($statusName === 'Cancelled') {
        $statusClass = 'badge-cancelled';
        $statusName = 'Cancellata';
    } else {
        $statusClass = 'badge-pending';
        $statusName = 'In Attesa';
    }
    
    $statusHtml = '<span class="premium-status-badge ' . $statusClass . '">' . htmlspecialchars($statusName) . '</span>';
    
    $restBookingsHtml .= '
    <div class="booking-history-card">
        <div class="booking-header">
            <h5 class="booking-title">Tavolo #' . $rb['id'] . '</h5>
            <div>' . $statusHtml . '</div>
        </div>
        
        <div class="booking-grid">
            <div class="booking-meta-item">
                <span class="booking-meta-label">Servizio</span>
                <span class="booking-meta-value"><i class="fas fa-utensils"></i> ' . htmlspecialchars($rb['meal_type']) . '</span>
            </div>
            <div class="booking-meta-item">
                <span class="booking-meta-label">Data e Ora</span>
                <span class="booking-meta-value"><i class="far fa-calendar-alt"></i> ' . $rDate->format('d/m/Y') . ' - ' . substr($rb['reservation_time'], 0, 5) . '</span>
            </div>
            <div class="booking-meta-item">
                <span class="booking-meta-label">Ospiti</span>
                <span class="booking-meta-value"><i class="fas fa-user-friends"></i> ' . (int)$rb['guests'] . '</span>
            </div>
        </div>
    </div>';
}

if (empty($restBookingsHtml)) {
    $restBookingsHtml = '
    <div class="text-center p-4" style="background: rgba(255, 255, 255, 0.4); border: 1px dashed #cbd5e0; border-radius: 16px;">
        <span style="font-size: 2.5rem; color: #cbd5e0; margin-bottom: 10px; display: block;"><i class="fas fa-utensils text-muted"></i></span>
        <h6 style="color: #718096; font-weight: 600; margin-bottom: 5px;">Nessuna prenotazione ristorante</h6>
        <p style="color: #a0aec0; font-size: 0.85rem; margin-bottom: 15px;">Riserva un tavolo e gusta le nostre specialità!</p>
        <a href="' . $config['base'] . '/restaurant_menu.php" class="btn-elite-edit" style="display: inline-block; padding: 6px 16px; font-size: 0.8rem; text-decoration: none;">Scopri il Menù</a>
    </div>';
}

$block->setContent('restaurant_bookings_html', $restBookingsHtml);

$skin->setContent('body', $block->get());
$skin->close();
