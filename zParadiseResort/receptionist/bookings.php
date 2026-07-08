<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

// Controlliamo che l'utente sia loggato e sia Receptionist
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

// Gestione dell'aggiornamento dello stato
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $statusId = (int)($_POST['status_id'] ?? 0);

    if ($bookingId > 0 && $statusId > 0) {
        try {
            // Verifichiamo se la prenotazione esiste
            $checkStmt = $db->prepare("SELECT 1 FROM bookings WHERE id = ?");
            $checkStmt->execute([$bookingId]);
            if ($checkStmt->fetch()) {
                $updateStmt = $db->prepare("UPDATE bookings SET status_id = ? WHERE id = ?");
                $updateStmt->execute([$statusId, $bookingId]);
                $_SESSION['success_msg'] = "Stato della prenotazione #{$bookingId} aggiornato con successo.";
            } else {
                $_SESSION['error_msg'] = "Prenotazione non trovata.";
            }
        } catch (Exception $e) {
            $_SESSION['error_msg'] = "Errore durante l'aggiornamento dello stato.";
        }
    } else {
        $_SESSION['error_msg'] = "Parametri non validi.";
    }

    // Reindirizzamento per evitare reinvio form
    $queryString = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: " . $_SERVER['PHP_SELF'] . $queryString);
    exit;
}

// Inizializza la pagina usando il frame privato dell'amministrazione
$page = new_page('administration', 'frame-private');
$block = new_block('bookings');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? (int)$_GET['status'] : 0;

$block->setContent('search_query', htmlspecialchars($search));

// Popola il menu dei filtri di stato
$stmtStatuses = $db->query("SELECT id, name FROM booking_statuses ORDER BY id ASC");
$statuses = $stmtStatuses->fetchAll();
foreach ($statuses as $st) {
    $block->setContent('filter_status_id', $st['id']);
    $block->setContent('filter_status_name', htmlspecialchars($st['name']));
    $block->setContent('filter_status_selected', ($st['id'] == $statusFilter) ? 'selected' : '');
}

// Costruiamo la query di elenco prenotazioni
$query = "SELECT b.id, b.check_in_date, b.check_out_date, b.total_price, b.created_at, b.status_id,
                 u.first_name, u.last_name, u.email,
                 r.room_number, rc.name AS category_name, bs.name AS status_name
          FROM bookings b
          JOIN users u ON b.user_id = u.id
          JOIN rooms r ON b.room_id = r.id
          JOIN room_categories rc ON r.category_id = rc.id
          JOIN booking_statuses bs ON b.status_id = bs.id
          WHERE 1=1";

$params = [];

if ($search !== '') {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR r.room_number LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($statusFilter > 0) {
    $query .= " AND b.status_id = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY b.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

if (count($bookings) > 0) {
    $block->setContent('bookings_list', '1'); // For ifempty logic
    foreach ($bookings as $b) {
        $block->setContent('booking_id', $b['id']);
        $block->setContent('booking_created', date('d/m/Y H:i', strtotime($b['created_at'])));
        $block->setContent('guest_name', htmlspecialchars($b['first_name'] . ' ' . $b['last_name']));
        $block->setContent('guest_email', htmlspecialchars($b['email']));
        $block->setContent('room_number', htmlspecialchars($b['room_number']));
        $block->setContent('room_category', htmlspecialchars($b['category_name']));
        $block->setContent('check_in', date('d/m/Y', strtotime($b['check_in_date'])));
        $block->setContent('check_out', date('d/m/Y', strtotime($b['check_out_date'])));
        $block->setContent('total_price', number_format($b['total_price'], 2, ',', '.'));
        $block->setContent('status_name', htmlspecialchars($b['status_name']));

        // Badge class in base allo stato
        $badgeClass = 'text-bg-secondary';
        $statusId = (int)$b['status_id'];
        if ($statusId === 2) {
            $badgeClass = 'text-bg-warning'; // Pending
        } elseif ($statusId === 3) {
            $badgeClass = 'text-bg-success'; // Confirmed
        } elseif ($statusId === 4) {
            $badgeClass = 'text-bg-danger'; // Cancelled
        } elseif ($statusId === 5) {
            $badgeClass = 'text-bg-info text-white'; // Completed
        }
        $block->setContent('status_badge_class', $badgeClass);

        // Determinazione azioni disponibili
        $canConfirm = ($statusId === 2) ? '1' : '';
        $canComplete = ($statusId === 3) ? '1' : '';
        $canCancel = ($statusId === 2 || $statusId === 3) ? '1' : '';
        $hasActions = ($canConfirm || $canComplete || $canCancel) ? '1' : '';

        $block->setContent('can_confirm', $canConfirm);
        $block->setContent('can_complete', $canComplete);
        $block->setContent('can_cancel', $canCancel);
        $block->setContent('has_actions', $hasActions);
    }
} else {
    $block->setContent('bookings_list', ''); 
}

$block->setContent('success_msg', htmlspecialchars($successMsg));
$block->setContent('error_msg', htmlspecialchars($errorMsg));

// Popoliamo le variabili comuni del frame privato
$page->setContent('base', $config['base']);
$page->setContent('skin', 'administration');
$page->setContent('user_name', htmlspecialchars($_SESSION['user']['name']));
$page->setContent('user_role', 'Receptionist');
$page->setContent('role_path', 'receptionist');
$page->setContent('is_admin_role', ''); // Vuoto per nascondere i link admin-only

$page->setContent('body', $block->get());
$page->close();
