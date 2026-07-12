<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

// Controlliamo i permessi per il servizio
require_service('restaurant_bookings.php');

$db = db();
$successMsg = $_SESSION['success_msg'] ?? '';
$errorMsg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $status = $_POST['status'] ?? ''; // Pending, Confirmed, Cancelled

        if ($bookingId > 0 && in_array($status, ['Pending', 'Confirmed', 'Cancelled'])) {
            try {
                $checkStmt = $db->prepare("SELECT 1 FROM restaurant_reservations WHERE id = ?");
                $checkStmt->execute([$bookingId]);
                if ($checkStmt->fetch()) {
                    if ($status === 'Cancelled') {
                        $deleteStmt = $db->prepare("DELETE FROM restaurant_reservations WHERE id = ?");
                        $deleteStmt->execute([$bookingId]);
                        $_SESSION['success_msg'] = "Prenotazione #{$bookingId} eliminata con successo.";
                    } else {
                        $updateStmt = $db->prepare("UPDATE restaurant_reservations SET status = ? WHERE id = ?");
                        $updateStmt->execute([$status, $bookingId]);
                        $_SESSION['success_msg'] = "Stato della prenotazione #{$bookingId} aggiornato con successo a {$status}.";
                    }
                } else {
                    $_SESSION['error_msg'] = "Prenotazione non trovata.";
                }
            } catch (Exception $e) {
                $_SESSION['error_msg'] = "Errore durante l'aggiornamento dello stato.";
            }
        } else {
            $_SESSION['error_msg'] = "Parametri non validi.";
        }
    }

    $queryString = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: " . $_SERVER['PHP_SELF'] . $queryString);
    exit;
}

$page = new_page('administration', 'frame-private');
$block = new_block('restaurant_bookings');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$dateFilter = isset($_GET['date']) ? trim($_GET['date']) : '';

$block->setContent('search_query', htmlspecialchars($search));
$block->setContent('date_query', htmlspecialchars($dateFilter));
$block->setContent('today_date', date('Y-m-d'));

$query = "SELECT r.id, r.reservation_date, r.meal_type, r.reservation_time, r.guests, r.status, r.created_at,
                 u.first_name, u.last_name, u.email
          FROM restaurant_reservations r
          JOIN users u ON r.user_id = u.id
          WHERE 1=1";

$params = [];

if ($search !== '') {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($statusFilter !== '') {
    $query .= " AND r.status = ?";
    $params[] = $statusFilter;
}

if ($dateFilter !== '') {
    $query .= " AND r.reservation_date = ?";
    $params[] = $dateFilter;
}

$query .= " ORDER BY r.reservation_date ASC, r.reservation_time ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

if (count($bookings) > 0) {
    $block->setContent('bookings_list', '1');
    foreach ($bookings as $b) {
        $block->setContent('booking_id', $b['id']);
        $block->setContent('booking_created', date('d/m/Y H:i', strtotime($b['created_at'])));
        $block->setContent('guest_name', htmlspecialchars($b['first_name'] . ' ' . $b['last_name']));
        $block->setContent('guest_email', htmlspecialchars($b['email']));
        $block->setContent('reservation_date', date('d/m/Y', strtotime($b['reservation_date'])));
        $block->setContent('meal_type', htmlspecialchars($b['meal_type']));
        $block->setContent('reservation_time', htmlspecialchars(substr($b['reservation_time'], 0, 5)));
        $block->setContent('guests', (int)$b['guests']);
        $badgeClass = 'text-bg-secondary';
        $status = $b['status'];
        $status_it = $status;
        if ($status === 'Pending') {
            $badgeClass = 'text-bg-warning';
            $status_it = 'In Attesa';
        } elseif ($status === 'Confirmed') {
            $badgeClass = 'text-bg-success';
            $status_it = 'Confermata';
        } elseif ($status === 'Cancelled') {
            $badgeClass = 'text-bg-danger';
            $status_it = 'Cancellata';
        }
        $block->setContent('status', htmlspecialchars($status_it));
        $block->setContent('status_badge_class', $badgeClass);

        $actionsHtml = '<div class="d-flex gap-1 justify-content-end">';
        if ($status !== 'Confirmed') {
            $actionsHtml .= '
                <form action="" method="POST" style="display:inline-block; margin-bottom:0;">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="booking_id" value="' . $b['id'] . '">
                  <input type="hidden" name="status" value="Confirmed">
                  <button type="submit" class="btn btn-sm btn-outline-success" title="Conferma">
                    <i class="bi bi-check-lg"></i>
                  </button>
                </form>';
        }
        if ($status !== 'Cancelled') {
            $actionsHtml .= '
                <form action="" method="POST" style="display:inline-block; margin-bottom:0;" onsubmit="return confirm(\'Cancellare?\');">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="booking_id" value="' . $b['id'] . '">
                  <input type="hidden" name="status" value="Cancelled">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancella">
                    <i class="bi bi-x-circle"></i>
                  </button>
                </form>';
        }
        $actionsHtml .= '</div>';
        $block->setContent('booking_actions', $actionsHtml);
    }
} else {
    $block->setContent('bookings_list', ''); 
}

if ($successMsg !== '') {
    $block->setContent('success_msg', htmlspecialchars($successMsg));
}
if ($errorMsg !== '') {
    $block->setContent('error_msg', htmlspecialchars($errorMsg));
}

setup_backoffice_page($page, 'Gestione Ristorante', 'admin');

$page->setContent('body', $block->get());
$page->close();
