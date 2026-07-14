<?php
require_once __DIR__ . '/include/bootstrap.inc.php';

require_login();
block_staff();
require_service();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: review.php');
    exit;
}

$room_category_id_raw = trim($_POST['room_category_id'] ?? '');
$rating = trim($_POST['rating'] ?? '');
$comment = trim($_POST['comment'] ?? '');

if (empty($comment)) {
    $_SESSION['review_error'] = 'Il commento è obbligatorio.';
    header('Location: review.php');
    exit;
}

if (empty($rating) || !in_array($rating, ['1', '2', '3', '4', '5'])) {
    $_SESSION['review_error'] = 'La valutazione da 1 a 5 stelle è obbligatoria.';
    header('Location: review.php');
    exit;
}

if (empty($room_category_id_raw) || !is_numeric($room_category_id_raw)) {
    $_SESSION['review_error'] = 'Seleziona la tipologia di camera per cui vuoi lasciare la recensione.';
    header('Location: review.php');
    exit;
}

$today = date('Y-m-d');
$stmtCheck = db()->prepare('
    SELECT DISTINCT rc.id as room_category_id
    FROM bookings b
    JOIN rooms r ON r.id = b.room_id
    JOIN room_categories rc ON rc.id = r.category_id
    WHERE b.user_id = ?
      AND b.status_id IN (3, 5) -- Confirmed o Completed
      AND b.check_in_date <= ?
');
$stmtCheck->execute([$_SESSION['user']['id'], $today]);
$valid_categories = $stmtCheck->fetchAll(PDO::FETCH_COLUMN);

if (empty($valid_categories)) {
    $_SESSION['review_error'] = 'Non hai soggiorni attivi o recenti per poter inviare una recensione.';
    header('Location: review.php');
    exit;
}

$selected_category_id = (int)$room_category_id_raw;
if (!in_array($selected_category_id, $valid_categories)) {
    $_SESSION['review_error'] = 'La tipologia di camera selezionata non corrisponde a nessuno dei tuoi soggiorni presso il nostro resort.';
    header('Location: review.php');
    exit;
}

try {
    $stmtInsert = db()->prepare('
        INSERT INTO reviews (user_id, room_category_id, rating, comment, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ');
    $stmtInsert->execute([
        $_SESSION['user']['id'],
        $selected_category_id,
        (int)$rating,
        $comment
    ]);

    $_SESSION['review_success'] = 'Grazie per la tua recensione! La tua opinione è stata inviata e pubblicata con successo.';
} catch (Exception $e) {
    $_SESSION['review_error'] = 'Si è verificato un errore durante l\'invio della recensione. Riprova più tardi.';
}

header('Location: review.php');
exit;
