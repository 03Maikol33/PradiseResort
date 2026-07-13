<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_service('rooms.php');

$page = new_page('administration', 'receptionist-frame-private');
setup_backoffice_page($page, 'Receptionist', 'receptionist');
$block = new_block('receptionist-rooms');

$db = db();

$success_msg = isset($_GET['success']) ? trim($_GET['success']) : '';
$error_msg = isset($_GET['error']) ? trim($_GET['error']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $room_id = (int)$_POST['room_id'];
    $new_status = trim($_POST['status']);
    
    // Receptionist can only set Available or Cleaning
    if (in_array($new_status, ['Available', 'Cleaning'])) {
        $stmt_update = $db->prepare("UPDATE rooms SET status = ? WHERE id = ?");
        if ($stmt_update->execute([$new_status, $room_id])) {
            header("Location: rooms.php?success=" . urlencode("Stato camera aggiornato con successo."));
            exit;
        } else {
            $error_msg = "Errore durante l'aggiornamento dello stato.";
        }
    } else {
        $error_msg = "Stato non valido o non autorizzato.";
    }
}

$block->setContent('success_msg', htmlspecialchars($success_msg));
$block->setContent('error_msg', htmlspecialchars($error_msg));
$block->setContent('show_success', $success_msg ? '1' : '');
$block->setContent('show_error', $error_msg ? '1' : '');

$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$floor = isset($_GET['floor']) ? trim($_GET['floor']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

$block->setContent('status_Available_selected', strcasecmp($status_filter, 'Available') === 0 ? 'selected' : '');
$block->setContent('status_Maintenance_selected', strcasecmp($status_filter, 'Maintenance') === 0 ? 'selected' : '');
$block->setContent('status_Cleaning_selected', strcasecmp($status_filter, 'Cleaning') === 0 ? 'selected' : '');

// Fetch categories for the filter dropdown
$stmt_cats = $db->query("SELECT id, name FROM room_categories ORDER BY name ASC");
$categories = $stmt_cats->fetchAll();

foreach ($categories as $cat) {
    $block->setContent('filter_cat_id', $cat['id']);
    $block->setContent('filter_cat_name', htmlspecialchars($cat['name']));
    $block->setContent('filter_cat_selected', ($cat['id'] == $category_id) ? 'selected' : '');
}

// Fetch distinct floors for the filter dropdown
$stmt_floors = $db->query("SELECT DISTINCT floor FROM rooms ORDER BY floor ASC");
$floors = $stmt_floors->fetchAll();

foreach ($floors as $fl) {
    $block->setContent('filter_floor_val', htmlspecialchars($fl['floor']));
    $block->setContent('filter_floor_selected', (strval($fl['floor']) === $floor) ? 'selected' : '');
}

// Build query
$query = "SELECT r.id, r.room_number, r.floor, r.status, c.name AS category_name 
          FROM rooms r
          LEFT JOIN room_categories c ON r.category_id = c.id
          WHERE 1=1";
$params = [];

if ($category_id > 0) {
    $query .= " AND r.category_id = ?";
    $params[] = $category_id;
}

if ($floor !== '') {
    $query .= " AND r.floor = ?";
    $params[] = (int)$floor;
}

if ($status_filter !== '') {
    $query .= " AND r.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY r.room_number ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

if (count($rooms) > 0) {
    $block->setContent('rooms_list', '1');
    foreach ($rooms as $room) {
        $block->setContent('room_id', $room['id']);
        $block->setContent('room_number', htmlspecialchars($room['room_number']));
        $block->setContent('room_category', htmlspecialchars($room['category_name']));
        $block->setContent('room_floor', htmlspecialchars($room['floor']));
        
        // Translate status and assign badge color
        $status = strtolower($room['status']);
        $status_label = $status;
        $badge_class = 'bg-secondary';
        
        if ($status === 'available') {
            $status_label = 'Disponibile';
            $badge_class = 'bg-success';
        } elseif ($status === 'maintenance') {
            $status_label = 'In Manutenzione';
            $badge_class = 'bg-danger';
        } elseif ($status === 'cleaning') {
            $status_label = 'In Pulizia';
            $badge_class = 'bg-warning text-dark';
        }
        
        $block->setContent('room_status_label', $status_label);
        $block->setContent('room_status_badge', $badge_class);
        
        $form_html = '';
        if ($status === 'maintenance') {
            $form_html = '<span class="text-muted small">Solo admin</span>';
        } else {
            $is_available_selected = ($status === 'available') ? 'selected' : '';
            $is_cleaning_selected = ($status === 'cleaning') ? 'selected' : '';
            $form_html = '
              <form action="rooms.php" method="POST" class="d-flex justify-content-end gap-2 align-items-center m-0">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="room_id" value="' . $room['id'] . '">
                  <select name="status" class="form-select form-select-sm w-auto" required>
                      <option value="Available" ' . $is_available_selected . '>Disponibile</option>
                      <option value="Cleaning" ' . $is_cleaning_selected . '>In Pulizia</option>
                  </select>
                  <button type="submit" class="btn btn-sm btn-primary">Salva</button>
              </form>
            ';
        }
        $block->setContent('action_column', $form_html);
    }
} else {
    $block->setContent('rooms_list', ''); 
}

$page->setContent('body', $block->get());
$page->close();
