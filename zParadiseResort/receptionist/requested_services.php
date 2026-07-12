<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_service('requested_services.php');

$page = new_page('administration', 'receptionist-frame-private');
$block = new_block('requested_services');

$db = db();

// Fetch filter options
$stmtAmenities = $db->query("SELECT id, name FROM amenities ORDER BY name ASC");
$allAmenitiesList = $stmtAmenities->fetchAll();

$filterAmenity = isset($_GET['amenity']) ? (int)$_GET['amenity'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? (int)$_GET['status'] : 0;

$block->setContent('search_query', htmlspecialchars($search));

// Popoliamo il filtro dei servizi
foreach ($allAmenitiesList as $am) {
    $block->setContent('filter_am_id', $am['id']);
    $block->setContent('filter_am_name', htmlspecialchars($am['name']));
    $block->setContent('filter_am_selected', ($am['id'] == $filterAmenity) ? 'selected' : '');
}

// Popoliamo il filtro degli stati
$statusTranslations = [
    'In Cart' => 'Nel Carrello',
    'Pending' => 'In attesa',
    'Confirmed' => 'Confermata',
    'Cancelled' => 'Cancellata',
    'Completed' => 'Completata'
];
$stmtStatuses = $db->query("SELECT id, name FROM booking_statuses ORDER BY id ASC");
$statuses = $stmtStatuses->fetchAll();
foreach ($statuses as $st) {
    $stName = $statusTranslations[$st['name']] ?? $st['name'];
    $block->setContent('filter_status_id', $st['id']);
    $block->setContent('filter_status_name', htmlspecialchars($stName));
    $block->setContent('filter_status_selected', ($st['id'] == $statusFilter) ? 'selected' : '');
}

// Costruiamo la query
$query = "SELECT ba.booking_id, ba.quantity, a.name AS amenity_name, a.price AS amenity_price,
                 b.check_in_date, b.check_out_date, b.status_id,
                 u.first_name, u.last_name, u.email,
                 r.room_number, rc.name AS category_name, bs.name AS status_name
          FROM booking_amenities ba
          JOIN amenities a ON ba.amenity_id = a.id
          JOIN bookings b ON ba.booking_id = b.id
          JOIN users u ON b.user_id = u.id
          JOIN rooms r ON b.room_id = r.id
          JOIN room_categories rc ON r.category_id = rc.id
          JOIN booking_statuses bs ON b.status_id = bs.id
          WHERE 1=1";

$params = [];

if ($filterAmenity > 0) {
    $query .= " AND ba.amenity_id = ?";
    $params[] = $filterAmenity;
}

if ($statusFilter > 0) {
    $query .= " AND b.status_id = ?";
    $params[] = $statusFilter;
}

if ($search !== '') {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR r.room_number LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$query .= " ORDER BY b.check_in_date ASC, a.name ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();

if (count($requests) > 0) {
    $block->setContent('requests_list', '1');
    foreach ($requests as $req) {
        $block->setContent('booking_id', $req['booking_id']);
        $block->setContent('guest_name', htmlspecialchars($req['first_name'] . ' ' . $req['last_name']));
        $block->setContent('guest_email', htmlspecialchars($req['email']));
        $block->setContent('room_number', htmlspecialchars($req['room_number']));
        $block->setContent('room_category', htmlspecialchars($req['category_name']));
        $block->setContent('service_name', htmlspecialchars($req['amenity_name']));
        $block->setContent('quantity', $req['quantity']);
        $block->setContent('check_in', date('d/m/Y', strtotime($req['check_in_date'])));
        $block->setContent('check_out', date('d/m/Y', strtotime($req['check_out_date'])));
        
        $totalVal = (float)$req['amenity_price'] * (int)$req['quantity'];
        $block->setContent('service_total', number_format($totalVal, 2, ',', '.'));
        
        $translatedStatus = $statusTranslations[$req['status_name']] ?? $req['status_name'];
        $block->setContent('status_name', htmlspecialchars($translatedStatus));
        
        // Badge class per lo stato
        $badgeClass = 'text-bg-secondary';
        $statusId = (int)$req['status_id'];
        if ($statusId === 1) {
            $badgeClass = 'text-bg-secondary'; // In Cart
        } elseif ($statusId === 2) {
            $badgeClass = 'text-bg-warning'; // Pending
        } elseif ($statusId === 3) {
            $badgeClass = 'text-bg-success'; // Confirmed
        } elseif ($statusId === 4) {
            $badgeClass = 'text-bg-danger'; // Cancelled
        } elseif ($statusId === 5) {
            $badgeClass = 'text-bg-info text-white'; // Completed
        }
        $block->setContent('status_badge_class', $badgeClass);
    }
} else {
    $block->setContent('requests_list', '');
}

setup_backoffice_page($page, 'Receptionist', 'receptionist');
$page->setContent('body', $block->get());
$page->close();
