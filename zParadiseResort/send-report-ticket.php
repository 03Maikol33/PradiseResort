<?php
require_once __DIR__ . '/include/bootstrap.inc.php';

require_login();
block_staff();
require_service();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: report-ticket.php');
    exit;
}

$room_id_raw = trim($_POST['room_id'] ?? '');
$descrizione = trim($_POST['descrizione'] ?? '');

if (empty($descrizione)) {
    $_SESSION['report_error'] = 'La descrizione del problema è obbligatoria.';
    header('Location: report-ticket.php');
    exit;
}

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
    $_SESSION['report_error'] = 'Non hai camere prenotate attive in questo momento. Non puoi inviare segnalazioni.';
    header('Location: report-ticket.php');
    exit;
}

$room_to_save = null;

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
        $_SESSION['report_error'] = 'La camera selezionata non corrisponde a nessuna delle tue prenotazioni attive.';
        header('Location: report-ticket.php');
        exit;
    }
    $room_to_save = $selected_room_id;
}

try {
    $stmtInsert = db()->prepare('
        INSERT INTO maintenance_tickets (room_id, reported_by_user_id, status_id, issue_description)
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
