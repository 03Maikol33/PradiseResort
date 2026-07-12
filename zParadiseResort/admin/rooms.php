<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_admin();

$page = new_page('administration', 'frame-private');
$block = new_block('rooms');

$db = db();

$success_msg = isset($_GET['success']) ? trim($_GET['success']) : '';
$error_msg = isset($_GET['error']) ? trim($_GET['error']) : '';

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
    }
} else {
    $block->setContent('rooms_list', ''); 
}

$page->setContent('body', $block->get());
$page->close();
