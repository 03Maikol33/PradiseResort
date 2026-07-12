<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_login();

// Assicura idempontenza e registrazione del servizio nel DB per i controlli ACL
try {
    $db = db();
    $db->exec("INSERT IGNORE INTO services (id, script_name, description) VALUES (11, 'segnalazioni.php', 'Gestione Segnalazioni')");
    $db->exec("INSERT IGNORE INTO group_services (group_id, service_id) VALUES (1, 11), (2, 11)");
    if (is_admin() || is_receptionist()) {
        $_SESSION['user']['services']['segnalazioni.php'] = true;
    }
} catch (Exception $e) {}

require_admin();

$db = db();
$successMsg = $_SESSION['success_msg'] ?? '';
$errorMsg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Gestione (solo chiusura -> eliminazione dal db, niente storico)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'close_ticket' || $action === 'delete_ticket') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        if ($ticketId > 0) {
            try {
                $checkStmt = $db->prepare("SELECT 1 FROM maintenance_tickets WHERE id = ?");
                $checkStmt->execute([$ticketId]);
                if ($checkStmt->fetch()) {
                    // Chiusura completata: eliminazione definitiva dal db, niente storico
                    $deleteStmt = $db->prepare("DELETE FROM maintenance_tickets WHERE id = ?");
                    $deleteStmt->execute([$ticketId]);
                    $_SESSION['success_msg'] = "Segnalazione #{$ticketId} chiusa con successo ed eliminata dal database (nessuno storico archiviato).";
                } else {
                    $_SESSION['error_msg'] = "Segnalazione non trovata o già chiusa ed eliminata dal database.";
                }
            } catch (Exception $e) {
                $_SESSION['error_msg'] = "Si è verificato un errore durante la chiusura ed eliminazione della segnalazione: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_msg'] = "ID segnalazione non valido.";
        }
    } elseif ($action === 'update_status') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $newStatusId = (int)($_POST['new_status_id'] ?? 0);

        if ($ticketId > 0 && in_array($newStatusId, [1, 2, 3])) {
            try {
                $checkStmt = $db->prepare("SELECT status_id FROM maintenance_tickets WHERE id = ?");
                $checkStmt->execute([$ticketId]);
                $row = $checkStmt->fetch();
                if ($row) {
                    $currStatus = (int)$row['status_id'];
                    // Verifica transizione che rispetti il flusso degli stati prestabiliti (1: Open -> 2: In Progress -> 3: Resolved)
                    if ($newStatusId > $currStatus && $newStatusId <= 3) {
                        $updateStmt = $db->prepare("UPDATE maintenance_tickets SET status_id = ? WHERE id = ?");
                        $updateStmt->execute([$newStatusId, $ticketId]);
                        $stMap = [1 => 'Aperta', 2 => 'In Lavorazione', 3 => 'Risolta'];
                        $_SESSION['success_msg'] = "Stato della segnalazione #{$ticketId} avanzato a '{$stMap[$newStatusId]}'.";
                    } else {
                        $_SESSION['error_msg'] = "Avanzamento di stato non valido. Assicurati di rispettare la sequenza prestabilita (Aperta -> In Lavorazione -> Risolta).";
                    }
                } else {
                    $_SESSION['error_msg'] = "Segnalazione non trovata.";
                }
            } catch (Exception $e) {
                $_SESSION['error_msg'] = "Errore durante l'aggiornamento dello stato: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_msg'] = "Dati per l'avanzamento di stato non validi.";
        }
    } elseif ($action === 'create_ticket') {
        $_SESSION['error_msg'] = "Operazione non consentita: come Admin puoi solo consultare, far avanzare di stato e gestire (solo chiusura) le segnalazioni.";
    } else {
        $_SESSION['error_msg'] = "Azione non consentita. L'Amministratore può gestire l'avanzamento di stato e la chiusura/rimozione delle segnalazioni completate.";
    }

    header("Location: segnalazioni.php");
    exit;
}

// Inizializza la pagina usando il frame privato dell'amministrazione (frame-private)
$page = new_page('administration', 'frame-private');
$block = new_block('segnalazioni');

$block->setContent('success_msg', htmlspecialchars($successMsg));
$block->setContent('error_msg', htmlspecialchars($errorMsg));
$block->setContent('can_create', ''); // Nessun tasto o modale di invio per Admin
$block->setContent('can_close', '1'); // Modale di conferma chiusura per Admin


$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? (int)$_GET['status'] : 0;
$roomFilter = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;

$block->setContent('search_query', htmlspecialchars($search));

// Traduzioni degli stati in italiano per l'interfaccia
$statusTranslations = [
    'Open' => 'Aperta',
    'In Progress' => 'In Lavorazione',
    'Resolved' => 'Risolta'
];

// 1. Popola i filtri per lo stato
$stmtStatuses = $db->query("SELECT id, name FROM ticket_statuses ORDER BY id ASC");
$statuses = $stmtStatuses->fetchAll();
foreach ($statuses as $st) {
    $stName = $statusTranslations[$st['name']] ?? $st['name'];
    $block->setContent('filter_status_id', $st['id']);
    $block->setContent('filter_status_name', htmlspecialchars($stName));
    $block->setContent('filter_status_selected', ($st['id'] == $statusFilter) ? 'selected' : '');
}

// 2. Popola i filtri per le camere (ricerca) e ottieni elenco completo stanze
$stmtRooms = $db->query("
    SELECT r.id, r.room_number, rc.name AS category_name 
    FROM rooms r 
    JOIN room_categories rc ON r.category_id = rc.id 
    ORDER BY r.room_number ASC
");
$allRooms = $stmtRooms->fetchAll();

foreach ($allRooms as $room) {
    $block->setContent('filter_room_id', $room['id']);
    $block->setContent('filter_room_number', htmlspecialchars($room['room_number']));
    $block->setContent('filter_room_selected', ($room['id'] == $roomFilter) ? 'selected' : '');
}

// 3. Popola le schede e metriche riepilogative in alto
$stmtStats = $db->query("
    SELECT 
        COUNT(*) as total_cnt,
        SUM(CASE WHEN status_id = 1 THEN 1 ELSE 0 END) as open_cnt,
        SUM(CASE WHEN status_id = 2 THEN 1 ELSE 0 END) as in_progress_cnt,
        SUM(CASE WHEN status_id = 3 THEN 1 ELSE 0 END) as resolved_cnt
    FROM maintenance_tickets
");
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
$block->setContent('total_tickets', $stats['total_cnt'] ?? 0);
$block->setContent('open_tickets', $stats['open_cnt'] ?? 0);
$block->setContent('in_progress_tickets', $stats['in_progress_cnt'] ?? 0);
$block->setContent('resolved_tickets', $stats['resolved_cnt'] ?? 0);

// 4. Query per consultare tutte le segnalazioni inviate da clienti, receptionist o staff
$query = "
    SELECT mt.id, mt.issue_description, mt.created_at, mt.status_id,
           r.room_number, rc.name AS category_name,
           u.first_name, u.last_name, u.email,
           ts.name AS status_name
    FROM maintenance_tickets mt
    LEFT JOIN rooms r ON mt.room_id = r.id
    LEFT JOIN room_categories rc ON r.category_id = rc.id
    JOIN users u ON mt.reported_by_user_id = u.id
    JOIN ticket_statuses ts ON mt.status_id = ts.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (mt.issue_description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR r.room_number LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($statusFilter > 0) {
    $query .= " AND mt.status_id = ?";
    $params[] = $statusFilter;
}

if ($roomFilter > 0) {
    $query .= " AND mt.room_id = ?";
    $params[] = $roomFilter;
}

$query .= " ORDER BY mt.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

if (count($tickets) > 0) {
    $block->setContent('has_tickets', '1');
    foreach ($tickets as $t) {
        $block->setContent('ticket_id', $t['id']);
        $block->setContent('ticket_created', date('d/m/Y H:i', strtotime($t['created_at'])));
        $block->setContent('issue_description', nl2br(htmlspecialchars($t['issue_description'])));

        // Dettagli Segnalatore (Ospite o Receptionist)
        $block->setContent('reporter_name', htmlspecialchars($t['first_name'] . ' ' . $t['last_name']));
        $block->setContent('reporter_email', htmlspecialchars($t['email']));

        // Camera
        if (!empty($t['room_number'])) {
            $block->setContent('room_label', 'Camera ' . htmlspecialchars($t['room_number']));
            $block->setContent('room_category_label', htmlspecialchars($t['category_name']));
            $block->setContent('has_room', '1');
        } else {
            $block->setContent('room_label', 'Nessuna');
            $block->setContent('room_category_label', 'Generico');
            $block->setContent('has_room', '');
        }

        // Badge stato
        $statusId = (int)$t['status_id'];
        $statusNameIt = $statusTranslations[$t['status_name']] ?? $t['status_name'];
        $badgeClass = 'bg-secondary text-white';

        if ($statusId === 1) { // Open
            $badgeClass = 'bg-warning text-dark';
        } elseif ($statusId === 2) { // In Progress
            $badgeClass = 'bg-info text-white';
        } elseif ($statusId === 3) { // Resolved
            $badgeClass = 'bg-success text-white';
        }

        $block->setContent('status_badge_class', $badgeClass);
        $block->setContent('status_name_it', htmlspecialchars($statusNameIt));

        $advanceFormHtml = '';
        if ($statusId === 1) {
            $advanceFormHtml = '
              <form action="segnalazioni.php" method="POST" style="margin:0;">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="ticket_id" value="' . $t['id'] . '">
                <input type="hidden" name="new_status_id" value="2">
                <button type="submit" class="btn btn-sm btn-outline-info fw-bold shadow-sm d-flex align-items-center gap-1 px-2.5 py-1" style="border-radius: 6px;" title="Avanza a In Lavorazione">
                  <i class="bi bi-play-circle-fill"></i> Prendi in Carico
                </button>
              </form>';
        } elseif ($statusId === 2) {
            $advanceFormHtml = '
              <form action="segnalazioni.php" method="POST" style="margin:0;">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="ticket_id" value="' . $t['id'] . '">
                <input type="hidden" name="new_status_id" value="3">
                <button type="submit" class="btn btn-sm btn-outline-success fw-bold shadow-sm d-flex align-items-center gap-1 px-2.5 py-1" style="border-radius: 6px;" title="Avanza a Risolta">
                  <i class="bi bi-check-circle-fill"></i> Segna Risolta
                </button>
              </form>';
        }

        // Pulsanti di gestione (Avanzamento Stato e Chiusura -> eliminazione dal db, niente storico)
        $actionsHtml = '
            <hr class="my-2 text-muted" style="opacity: 0.1;">
            <div class="d-flex justify-content-between align-items-center w-100 pt-1 flex-wrap gap-2">
              <div class="d-flex align-items-center gap-2 flex-wrap">
                ' . $advanceFormHtml . '
                <button type="button" class="btn btn-sm btn-danger fw-bold shadow-sm d-flex align-items-center gap-1 px-3 py-1" style="border-radius: 6px;" title="Chiudi ed elimina dal DB" data-bs-toggle="modal" data-bs-target="#confirmCloseModal" data-ticket-id="' . $t['id'] . '">
                  <i class="bi bi-x-circle-fill"></i> Chiudi ed Elimina
                </button>
              </div>
            </div>';
        $block->setContent('ticket_actions', $actionsHtml);
    }
} else {
    $block->setContent('has_tickets', '');
}

// 5. Popola le camere per la modale (nel caso venga analizzata dal motore di template anche se can_create è vuoto)
foreach ($allRooms as $room) {
    $block->setContent('modal_room_id', $room['id']);
    $block->setContent('modal_room_number', htmlspecialchars($room['room_number']));
    $block->setContent('modal_room_category', htmlspecialchars($room['category_name']));
}

// Popoliamo le variabili comuni del frame privato e notifiche del backoffice per Admin
setup_backoffice_page($page, 'Amministratore', 'admin');

$page->setContent('body', $block->get());
$page->close();
