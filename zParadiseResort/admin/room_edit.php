<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

if (empty($_SESSION['user']) || !is_admin()) {
    header("Location: {$config['base']}/login.php");
    exit;
}

$page = new_page('administration', 'frame-private');
$block = new_block('room_edit');

$db = db();

$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

$error = '';
$success = '';

// Default values
$room = [
    'id' => 0,
    'room_number' => '',
    'category_id' => '',
    'floor' => '',
    'status' => 'available'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_number = trim($_POST['room_number'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $floor = $_POST['floor'] ?? '';
    $status = $_POST['status'] ?? 'available';

    // Validation
    if (empty($room_number) || empty($category_id) || $floor === '') {
        $error = "Numero stanza, Categoria e Piano sono obbligatori.";
    } elseif (!in_array($status, ['available', 'maintenance', 'cleaning'])) {
        $error = "Stato non valido.";
    } else {
        // Check for duplicate room_number
        $stmt_check = $db->prepare("SELECT id FROM rooms WHERE room_number = ? AND id != ?");
        $stmt_check->execute([$room_number, $id]);
        if ($stmt_check->fetch()) {
            $error = "Il numero stanza specificato esiste già.";
        } else {
            if ($id > 0) {
                // Update
                $stmt = $db->prepare("UPDATE rooms SET room_number = ?, category_id = ?, floor = ?, status = ? WHERE id = ?");
                if ($stmt->execute([$room_number, $category_id, $floor, $status, $id])) {
                    header("Location: rooms.php?success=" . urlencode("Stanza aggiornata con successo."));
                    exit;
                } else {
                    $error = "Errore durante l'aggiornamento della stanza.";
                }
            } else {
                // Insert
                $stmt = $db->prepare("INSERT INTO rooms (room_number, category_id, floor, status) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$room_number, $category_id, $floor, $status])) {
                    header("Location: rooms.php?success=" . urlencode("Stanza creata con successo."));
                    exit;
                } else {
                    $error = "Errore durante la creazione della stanza.";
                }
            }
        }
    }
    
    // Repopulate form with submitted data on error
    $room = [
        'id' => $id,
        'room_number' => $room_number,
        'category_id' => $category_id,
        'floor' => $floor,
        'status' => $status
    ];
} elseif ($id > 0) {
    // Fetch existing data
    $stmt = $db->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        $room = $existing;
    } else {
        header("Location: rooms.php?error=" . urlencode("Stanza non trovata."));
        exit;
    }
}

$block->setContent('page_title', $id > 0 ? 'Modifica Stanza' : 'Aggiungi Stanza');
$block->setContent('action_text', $id > 0 ? 'Aggiorna' : 'Salva');
$block->setContent('room_id', $room['id']);
$block->setContent('room_number', htmlspecialchars($room['room_number']));
$block->setContent('room_floor', htmlspecialchars($room['floor']));

// Populate categories dropdown
$stmt_cats = $db->query("SELECT id, name FROM room_categories ORDER BY name ASC");
$categories = $stmt_cats->fetchAll();

foreach ($categories as $cat) {
    $block->setContent('cat_opt_id', $cat['id']);
    $block->setContent('cat_opt_name', htmlspecialchars($cat['name']));
    $block->setContent('cat_opt_selected', ($cat['id'] == $room['category_id']) ? 'selected' : '');
}

// Set status selection
$block->setContent('status_available_selected', ($room['status'] === 'available') ? 'selected' : '');
$block->setContent('status_maintenance_selected', ($room['status'] === 'maintenance') ? 'selected' : '');
$block->setContent('status_cleaning_selected', ($room['status'] === 'cleaning') ? 'selected' : '');

$block->setContent('error_msg', htmlspecialchars($error));
$block->setContent('show_error', $error ? '1' : '');

$page->setContent('body', $block->get());
$page->close();
