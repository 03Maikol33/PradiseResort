<?php
require_once __DIR__ . '/include/bootstrap.inc.php';

// Protezione pagina: richiede login ed esclude lo staff
require_login();
block_staff();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: review.php');
    exit;
}

$room_id_raw = trim($_POST['room_id'] ?? '');
$rating = trim($_POST['rating'] ?? '');
$comment = trim($_POST['comment'] ?? '');

if (empty($comment)) {
    $_SESSION['review_error'] = 'Il commento è obbligatorio.';
    header('Location: review.php');
    exit;
}

if (empty($rating)) {
    $_SESSION['review_error'] = 'La valutazione è obbligatoria.';
    header('Location: review.php');
    exit;
}

// Verifica data attuale / intervallo check-in e check-out per le prenotazioni attive
$today = date('Y-m-d');
$stmtCheck = db()->prepare('
    SELECT b.room_id
    FROM bookings b
    WHERE b.user_id = ?
      AND b.status_id = 3 -- Confirmed
      AND b.check_in_date <= ?
      AND b.check_out_date >= ?
');
$stmtCheck->execute([$_SESSION['user']['id'], $today, $today]);
$active_bookings = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);

if (empty($active_bookings)) {
    $_SESSION['review_error'] = 'Non hai camere prenotate attive in questo momento o soggiorni recenti. Non puoi inviare recensioni.';
    header('Location: review.php');
    exit;
}

$room_to_save = null;

// Se viene selezionata una camera specifica, verifica che appartenga alle prenotazioni attive
if ($room_id_raw !== '' && $room_id_raw !== 'nessuna') {
    $selected_room_id = (int)$room_id_raw;
    $is_valid_room = false;
    foreach ($active_bookings as $booking) {
        if ($booking['room_id'] == $selected_room_id) {
            $is_valid_room = true;
            break;
        }
    }
    
    if (!$is_valid_room) {
        $_SESSION['review_error'] = 'La camera selezionata non corrisponde a nessuna delle tue prenotazioni attive.';
        header('Location: review.php');
        exit;
    }
    $room_to_save = $selected_room_id;
}

try {
    // Inserisci la segnalazione nella tabella maintenance_tickets
    $stmtInsert = db()->prepare('
        INSERT INTO maintenance_tickets (room_id, reported_by_user_id, status_id, issue_description )
        VALUES (?, ?, ?, ?)
    ');
    $stmtInsert->execute([
        $room_to_save,
        $_SESSION['user']['id'],
        1, // Status 'Open'
        $descrizione
    ]);

    $_SESSION['report_success'] = 'Segnalazione inviata con successo. Lo staff prenderà in carico la richiesta al più presto.';
} catch (Exception $e) {
    $_SESSION['report_error'] = 'Si è verificato un errore durante l\'invio della segnalazione. Riprova più tardi.';
}

header('Location: report-ticket.php');
exit;
