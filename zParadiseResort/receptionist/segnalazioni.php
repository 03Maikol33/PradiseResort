<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

// Controlliamo che l'utente sia loggato
if (empty($_SESSION['user'])) {
    header("Location: {$config['base']}/login.php");
    exit;
}

// Verifica ruolo Receptionist (se Admin, reindirizza o consenti in base alla logica del backoffice)
if (!is_receptionist()) {
    if (is_admin()) {
        header("Location: {$config['base']}/admin/index.php");
        exit;
    }
    header("Location: {$config['base']}/index.php");
    exit;
}

$db = db();

// Assicura idempontenza e registrazione del servizio nel DB per i controlli ACL di gruppo
try {
    $db->exec("INSERT IGNORE INTO services (id, script_name, description) VALUES (11, 'segnalazioni.php', 'Gestione Segnalazioni')");
    $db->exec("INSERT IGNORE INTO group_services (group_id, service_id) VALUES (1, 11), (2, 11)");
} catch (Exception $e) {}

$successMsg = $_SESSION['success_msg'] ?? '';
$errorMsg = $_SESSION['error_msg'] ?? '';
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// Gestione invio nuova segnalazione e blocco eliminazione/chiusura
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_ticket') {
        $roomIdRaw = trim($_POST['room_id'] ?? '');
        $issueDescription = trim($_POST['issue_description'] ?? '');

        if (empty($issueDescription)) {
            $_SESSION['error_msg'] = "La descrizione del problema o della richiesta è obbligatoria.";
            header("Location: segnalazioni.php");
            exit;
        }

        $roomId = null;
        if ($roomIdRaw !== '' && $roomIdRaw !== 'nessuna') {
            $selectedRoomId = (int)$roomIdRaw;
            if ($selectedRoomId > 0) {
                // Verifica che la stanza esista all'interno del resort
                $checkRoom = $db->prepare("SELECT 1 FROM rooms WHERE id = ?");
                $checkRoom->execute([$selectedRoomId]);
                if ($checkRoom->fetch()) {
                    $roomId = $selectedRoomId;
                } else {
                    $_SESSION['error_msg'] = "La camera selezionata non esiste.";
                    header("Location: segnalazioni.php");
                    exit;
                }
            }
        }

        try {
            // Inseriamo la segnalazione (Status Open = 1)
            $stmtInsert = $db->prepare("
                INSERT INTO maintenance_tickets (room_id, reported_by_user_id, status_id, issue_description)
                VALUES (?, ?, 1, ?)
            ");
            $stmtInsert->execute([
                $roomId,
                $_SESSION['user']['id'],
                $issueDescription
            ]);

            $_SESSION['success_msg'] = "Segnalazione inviata con successo. È stata inserita nel sistema di manutenzione.";
        } catch (Exception $e) {
            $_SESSION['error_msg'] = "Si è verificato un errore durante l'inserimento della segnalazione: " . $e->getMessage();
        }
    } elseif (in_array($action, ['delete_ticket', 'close_ticket', 'update_status', 'resolve_ticket'])) {
        // Blocco di sicurezza rigoroso: il receptionist non ha i permessi per eliminare o chiudere i ticket
        $_SESSION['error_msg'] = "Operazione non consentita: come receptionist puoi solo consultare le segnalazioni o inviarne di nuove. Non è permesso eliminarle o chiuderle.";
    } else {
        $_SESSION['error_msg'] = "Azione non riconosciuta o non autorizzata per il ruolo di receptionist.";
    }

    header("Location: segnalazioni.php");
    exit;
}

// Inizializza la pagina usando il frame privato dell'amministrazione (receptionist-frame-private)
$page = new_page('administration', 'receptionist-frame-private');
$block = new_block('segnalazioni');

$block->setContent('success_msg', htmlspecialchars($successMsg));
$block->setContent('error_msg', htmlspecialchars($errorMsg));

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
    // Tese separatamente per il filtro di ricerca
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

// 4. Costruzione ed esecuzione query per consultare tutte le segnalazioni
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

        // Dettagli Segnalatore
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
    }
} else {
    $block->setContent('has_tickets', '');
}

// 5. Popola le camere per la modale di inserimento nuova segnalazione
foreach ($allRooms as $room) {
    $block->setContent('modal_room_id', $room['id']);
    $block->setContent('modal_room_number', htmlspecialchars($room['room_number']));
    $block->setContent('modal_room_category', htmlspecialchars($room['category_name']));
}

// Popoliamo le variabili comuni del frame privato e notifiche del backoffice
setup_backoffice_page($page, 'Receptionist', 'receptionist');

$page->setContent('body', $block->get());
$page->close();
