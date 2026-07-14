<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

if (empty($_SESSION['user'])) {
    header("Location: {$config['base']}/login.php");
    exit;
}

if (!is_receptionist()) {
    if (is_admin()) {
        header("Location: {$config['base']}/admin/bookings.php");
        exit;
    }
    header("Location: {$config['base']}/index.php");
    exit;
}

$db = db();
$successMsg = $_SESSION['success_msg'] ?? '';
$errorMsg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $statusId = (int)($_POST['status_id'] ?? 0);

        if ($bookingId > 0 && $statusId > 0) {
            try {
                $checkStmt = $db->prepare("SELECT 1 FROM bookings WHERE id = ?");
                $checkStmt->execute([$bookingId]);
                if ($checkStmt->fetch()) {
                    if ($statusId === 4) {
                        $db->prepare("DELETE FROM booking_amenities WHERE booking_id = ?")->execute([$bookingId]);
                        $db->prepare("DELETE FROM invoices WHERE booking_id = ?")->execute([$bookingId]);
                        $deleteStmt = $db->prepare("DELETE FROM bookings WHERE id = ?");
                        $deleteStmt->execute([$bookingId]);
                        $_SESSION['success_msg'] = "Prenotazione #{$bookingId} eliminata con successo.";
                    } else {
                        $updateStmt = $db->prepare("UPDATE bookings SET status_id = ? WHERE id = ?");
                        $updateStmt->execute([$statusId, $bookingId]);
                        $_SESSION['success_msg'] = "Stato della prenotazione #{$bookingId} aggiornato con successo.";
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
    } elseif ($action === 'update_notes') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $staffNotes = trim($_POST['staff_notes'] ?? '');

        if ($bookingId > 0) {
            try {
                $updateStmt = $db->prepare("UPDATE bookings SET staff_notes = ? WHERE id = ?");
                $updateStmt->execute([$staffNotes === '' ? null : $staffNotes, $bookingId]);
                $_SESSION['success_msg'] = "Avviso per la prenotazione #{$bookingId} salvato con successo.";
            } catch (Exception $e) {
                $_SESSION['error_msg'] = "Errore durante il salvataggio dell'avviso.";
            }
        } else {
            $_SESSION['error_msg'] = "Parametri non validi.";
        }
    } elseif ($action === 'remove_amenity') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $amenityId = (int)($_POST['amenity_id'] ?? 0);

        if ($bookingId > 0 && $amenityId > 0) {
            try {
                $db->beginTransaction();

                $delStmt = $db->prepare("DELETE FROM booking_amenities WHERE booking_id = ? AND amenity_id = ?");
                $delStmt->execute([$bookingId, $amenityId]);

                $stmtDetails = $db->prepare("
                    SELECT b.check_in_date, b.check_out_date, rc.base_price
                    FROM bookings b
                    JOIN rooms r ON b.room_id = r.id
                    JOIN room_categories rc ON r.category_id = rc.id
                    WHERE b.id = ?
                ");
                $stmtDetails->execute([$bookingId]);
                $bDetails = $stmtDetails->fetch();

                if ($bDetails) {
                    $start = new DateTime($bDetails['check_in_date']);
                    $end = new DateTime($bDetails['check_out_date']);
                    $nights = $start->diff($end)->days;
                    if ($nights <= 0) $nights = 1;

                    $roomTotal = (float)$bDetails['base_price'] * $nights;

                    $stmtSum = $db->prepare("
                        SELECT SUM(a.price * ba.quantity) as total
                        FROM booking_amenities ba
                        JOIN amenities a ON a.id = ba.amenity_id
                        WHERE ba.booking_id = ?
                    ");
                    $stmtSum->execute([$bookingId]);
                    $amenitiesTotal = (float)($stmtSum->fetch(PDO::FETCH_COLUMN) ?? 0.00);

                    $newTotalPrice = $roomTotal + $amenitiesTotal;

                    $updBook = $db->prepare("UPDATE bookings SET total_price = ? WHERE id = ?");
                    $updBook->execute([$newTotalPrice, $bookingId]);

                    $updInv = $db->prepare("UPDATE invoices SET total_amount = ? WHERE booking_id = ?");
                    $updInv->execute([$newTotalPrice, $bookingId]);

                    $db->commit();
                    $_SESSION['success_msg'] = "Servizio extra rimosso e prezzo ricalcolato con successo.";
                } else {
                    $db->rollBack();
                    $_SESSION['error_msg'] = "Errore durante il ricalcolo del prezzo.";
                }
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['error_msg'] = "Errore durante la rimozione del servizio: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_msg'] = "Parametri non validi.";
        }
    }

    $queryString = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: " . $_SERVER['PHP_SELF'] . $queryString);
    exit;
}

$page = new_page('administration', 'receptionist-frame-private');
$block = new_block('bookings');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? (int)$_GET['status'] : 0;

$block->setContent('search_query', htmlspecialchars($search));

$statusTranslations = [
    'In Cart' => 'Nel Carrello',
    'Pending' => 'In attesa',
    'Confirmed' => 'Confermata',
    'Cancelled' => 'Cancellata',
    'Completed' => 'Completata'
];

$stmtStatuses = $db->query("SELECT id, name FROM booking_statuses WHERE name != 'Cancelled' ORDER BY id ASC");
$statuses = $stmtStatuses->fetchAll();
foreach ($statuses as $st) {
    $stName = $statusTranslations[$st['name']] ?? $st['name'];
    $block->setContent('filter_status_id', $st['id']);
    $block->setContent('filter_status_name', htmlspecialchars($stName));
    $block->setContent('filter_status_selected', ($st['id'] == $statusFilter) ? 'selected' : '');
}

$query = "SELECT b.id, b.check_in_date, b.check_out_date, b.total_price, b.created_at, b.status_id, b.staff_notes,
                 u.first_name, u.last_name, u.email, u.phone,
                 r.room_number, rc.name AS category_name, bs.name AS status_name
          FROM bookings b
          JOIN users u ON b.user_id = u.id
          JOIN rooms r ON b.room_id = r.id
          JOIN room_categories rc ON r.category_id = rc.id
          JOIN booking_statuses bs ON b.status_id = bs.id
          WHERE 1=1";

$params = [];

if ($search !== '') {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR r.room_number LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($statusFilter > 0) {
    $query .= " AND b.status_id = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY b.check_in_date ASC";

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

        $phoneHtml = '';
        if (!empty($b['phone'])) {
            $phoneHtml = '<div class="text-muted small"><i class="bi bi-telephone me-1"></i>' . htmlspecialchars($b['phone']) . '</div>';
        }
        $block->setContent('guest_phone_html', $phoneHtml);
        $block->setContent('room_number', htmlspecialchars($b['room_number']));
        $block->setContent('room_category', htmlspecialchars($b['category_name']));
        $block->setContent('check_in', date('d/m/Y', strtotime($b['check_in_date'])));
        $block->setContent('check_out', date('d/m/Y', strtotime($b['check_out_date'])));
        $block->setContent('total_price', number_format($b['total_price'], 2, ',', '.'));
        $block->setContent('booking_staff_notes', htmlspecialchars($b['staff_notes'] ?? ''));

        $translatedStatus = $statusTranslations[$b['status_name']] ?? $b['status_name'];
        $block->setContent('status_name', htmlspecialchars($translatedStatus));

        $amenitiesStmt = $db->prepare("
            SELECT a.id, a.name, ba.quantity
            FROM booking_amenities ba
            JOIN amenities a ON ba.amenity_id = a.id
            WHERE ba.booking_id = ?
        ");
        $amenitiesStmt->execute([$b['id']]);
        $amenities = $amenitiesStmt->fetchAll();

        $amenitiesHtml = '';
        if (empty($amenities)) {
            $amenitiesHtml = '<span class="text-muted small" style="font-style: italic;">Nessuno</span>';
        } else {
            foreach ($amenities as $am) {
                $qtyText = $am['quantity'] > 1 ? ' (x' . $am['quantity'] . ')' : '';
                $amenitiesHtml .= '
                    <span class="badge p-1 d-inline-flex align-items-center gap-1 mb-1" style="font-size: 0.75rem; border: 1px solid #d1d5db; color: #374151; background-color: #f3f4f6 !important;">
                      ' . htmlspecialchars($am['name']) . $qtyText . '
                      <form action="" method="POST" style="display:inline; margin:0;" onsubmit="return confirm(\'Rimuovere ' . htmlspecialchars($am['name']) . '?\');">
                        <input type="hidden" name="action" value="remove_amenity">
                        <input type="hidden" name="booking_id" value="' . $b['id'] . '">
                        <input type="hidden" name="amenity_id" value="' . $am['id'] . '">
                        <button type="submit" class="btn btn-xs text-danger p-0 border-0 bg-transparent" style="line-height:1; font-size:0.85rem;" title="Rimuovi servizio"><i class="bi bi-x-circle-fill"></i></button>
                      </form>
                    </span> ';
            }
        }
        $block->setContent('booking_amenities', $amenitiesHtml);

        $badgeClass = 'text-bg-secondary';
        $statusId = (int)$b['status_id'];
        if ($statusId === 2) {
            $badgeClass = 'text-bg-warning';
        } elseif ($statusId === 3) {
            $badgeClass = 'text-bg-success';
        } elseif ($statusId === 4) {
            $badgeClass = 'text-bg-danger';
        } elseif ($statusId === 5) {
            $badgeClass = 'text-bg-info text-white';
        }
        $block->setContent('status_badge_class', $badgeClass);

        $canConfirm = ($statusId === 2);
        $canComplete = ($statusId === 3);
        $canCancel = ($statusId === 2 || $statusId === 3);

        $actionsHtml = '<div class="d-flex gap-1 justify-content-end">';
        if ($canConfirm) {
            $actionsHtml .= '
                <form action="" method="POST" style="display:inline-block; margin-bottom:0;">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="booking_id" value="' . $b['id'] . '">
                  <input type="hidden" name="status_id" value="3">
                  <button type="submit" class="btn btn-sm btn-success" title="Conferma Prenotazione">
                    <i class="bi bi-check-lg"></i> Conferma
                  </button>
                </form>';
        }
        if ($canComplete) {
            $actionsHtml .= '
                <form action="" method="POST" style="display:inline-block; margin-bottom:0;">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="booking_id" value="' . $b['id'] . '">
                  <input type="hidden" name="status_id" value="5">
                  <button type="submit" class="btn btn-sm btn-info text-white" title="Segna come Completata">
                    <i class="bi bi-box-arrow-in-right"></i> Completa
                  </button>
                </form>';
        }
        if ($canCancel) {
            $actionsHtml .= '
                <form action="" method="POST" style="display:inline-block; margin-bottom:0;" onsubmit="return confirm(\'Sei sicuro di voler cancellare questa prenotazione?\');">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="booking_id" value="' . $b['id'] . '">
                  <input type="hidden" name="status_id" value="4">
                  <button type="submit" class="btn btn-sm btn-danger" title="Cancella Prenotazione">
                    <i class="bi bi-x-circle"></i> Cancella
                  </button>
                </form>';
        }
        if (!$canConfirm && !$canComplete && !$canCancel) {
            $actionsHtml .= '<span class="text-muted small">Nessuna azione</span>';
        }
        $actionsHtml .= '</div>';

        $block->setContent('booking_actions', $actionsHtml);
    }
} else {
    $block->setContent('bookings_list', '');
}

$block->setContent('success_msg', htmlspecialchars($successMsg));
$block->setContent('error_msg', htmlspecialchars($errorMsg));

setup_backoffice_page($page, 'Receptionist', 'receptionist');

$page->setContent('body', $block->get());
$page->close();
