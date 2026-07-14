<?php
require_once __DIR__ . '/include/bootstrap.inc.php';

// Protezione pagina: richiede login ed esclude lo staff
require_login();
block_staff();
require_service();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: review.php');
    exit;
}

$room_category_id_raw = trim($_POST['room_category_id'] ?? '');
$rating_raw = trim($_POST['rating'] ?? '');
$comment = trim($_POST['comment'] ?? '');

if (empty($room_category_id_raw) || !is_numeric($room_category_id_raw)) {
    $_SESSION['review_error'] = 'La tipologia di camera è obbligatoria.';
    header('Location: review.php');
    exit;
}

if (empty($rating_raw) || !is_numeric($rating_raw)) {
    $_SESSION['review_error'] = 'La valutazione è obbligatoria.';
    header('Location: review.php');
    exit;
}

$rating = (int)$rating_raw;
if ($rating < 1 || $rating > 5) {
    $_SESSION['review_error'] = 'La valutazione deve essere compresa tra 1 e 5 stelle.';
    header('Location: review.php');
    exit;
}

if (empty($comment)) {
    $_SESSION['review_error'] = 'Il commento è obbligatorio.';
    header('Location: review.php');
    exit;
}

$room_category_id = (int)$room_category_id_raw;

// Verifica che l'utente abbia almeno un soggiorno attivo o concluso (status 3=Confirmed o 5=Completed) per la tipologia selezionata
$today = date('Y-m-d');
$stmtCheck = db()->prepare('
    SELECT 1
    FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    WHERE b.user_id = ?
      AND b.status_id IN (3, 5)
      AND b.check_in_date <= ?
      AND r.category_id = ?
    LIMIT 1
');
$stmtCheck->execute([$_SESSION['user']['id'], $today, $room_category_id]);

if (!$stmtCheck->fetch()) {
    $_SESSION['review_error'] = 'Non puoi inviare una recensione per una tipologia di camera per cui non hai soggiorni attivi o passati.';
    header('Location: review.php');
    exit;
}

try {
    $stmtInsert = db()->prepare('
        INSERT INTO reviews (user_id, room_category_id, rating, comment)
        VALUES (?, ?, ?, ?)
    ');
    $stmtInsert->execute([
        $_SESSION['user']['id'],
        $room_category_id,
        $rating,
        $comment
    ]);

    $_SESSION['review_success'] = 'Recensione inviata con successo. Grazie per aver condiviso la tua esperienza!';
} catch (Exception $e) {
    $_SESSION['review_error'] = 'Si è verificato un errore durante l\'invio della recensione. Riprova più tardi.';
}

header('Location: review.php');
exit;
