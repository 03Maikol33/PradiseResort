<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

if (empty($_SESSION['user'])) {
    header("Location: {$config['base']}/login.php");
    exit;
}

if (!is_receptionist()) {
    if (is_admin()) {
        header("Location: {$config['base']}/admin/activity.php");
        exit;
    }
    header("Location: {$config['base']}/index.php");
    exit;
}

$db = db();
$page = new_page('administration', 'receptionist-frame-private');
$block = new_block('activity');

$todayStr = date('Y-m-d');
$statusTranslations = [
    'In Cart' => 'Nel Carrello',
    'Pending' => 'In attesa',
    'Confirmed' => 'Confermata',
    'Cancelled' => 'Cancellata',
    'Completed' => 'Completata'
];

$stmtArr = $db->prepare("
    SELECT b.id, b.check_in_date, b.check_out_date, b.total_price, b.status_id,
           u.first_name, u.last_name, u.email, r.room_number, rc.name AS category_name, bs.name AS status_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN rooms r ON b.room_id = r.id
    JOIN room_categories rc ON r.category_id = rc.id
    JOIN booking_statuses bs ON b.status_id = bs.id
    WHERE b.check_in_date = ? AND b.status_id IN (2, 3)
    ORDER BY b.id ASC
");
$stmtArr->execute([$todayStr]);
$arrivals = $stmtArr->fetchAll();
$cntArrivals = count($arrivals);
$block->setContent('cnt_arrivals', $cntArrivals);

if ($cntArrivals > 0) {
    $block->setContent('arr_list_empty', '');
    foreach ($arrivals as $a) {
        $block->setContent('arr_booking_id', $a['id']);
        $block->setContent('arr_guest_name', htmlspecialchars($a['first_name'] . ' ' . $a['last_name']));
        $block->setContent('arr_guest_email', htmlspecialchars($a['email']));
        $block->setContent('arr_room_number', htmlspecialchars($a['room_number']));
        $block->setContent('arr_room_category', htmlspecialchars($a['category_name']));
        $block->setContent('arr_check_in', date('d/m/Y', strtotime($a['check_in_date'])));
        $block->setContent('arr_check_out', date('d/m/Y', strtotime($a['check_out_date'])));
        $block->setContent('arr_total_price', number_format($a['total_price'], 2, ',', '.'));

        $trStatus = $statusTranslations[$a['status_name']] ?? $a['status_name'];
        $block->setContent('arr_status_name', htmlspecialchars($trStatus));

        $badgeClass = 'text-bg-secondary';
        $sid = (int)$a['status_id'];
        if ($sid === 2) $badgeClass = 'text-bg-warning';
        elseif ($sid === 3) $badgeClass = 'text-bg-success';
        $block->setContent('arr_badge_class', $badgeClass);
    }
} else {
    $block->setContent('arr_list_empty', '1');
}

$stmtDep = $db->prepare("
    SELECT b.id, b.check_in_date, b.check_out_date, b.total_price, b.status_id,
           u.first_name, u.last_name, u.email, r.room_number, rc.name AS category_name, bs.name AS status_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN rooms r ON b.room_id = r.id
    JOIN room_categories rc ON r.category_id = rc.id
    JOIN booking_statuses bs ON b.status_id = bs.id
    WHERE b.check_out_date = ? AND b.status_id IN (3, 5)
    ORDER BY b.id ASC
");
$stmtDep->execute([$todayStr]);
$departures = $stmtDep->fetchAll();
$cntDepartures = count($departures);
$block->setContent('cnt_departures', $cntDepartures);

if ($cntDepartures > 0) {
    $block->setContent('dep_list_empty', '');
    foreach ($departures as $d) {
        $block->setContent('dep_booking_id', $d['id']);
        $block->setContent('dep_guest_name', htmlspecialchars($d['first_name'] . ' ' . $d['last_name']));
        $block->setContent('dep_guest_email', htmlspecialchars($d['email']));
        $block->setContent('dep_room_number', htmlspecialchars($d['room_number']));
        $block->setContent('dep_room_category', htmlspecialchars($d['category_name']));
        $block->setContent('dep_check_in', date('d/m/Y', strtotime($d['check_in_date'])));
        $block->setContent('dep_check_out', date('d/m/Y', strtotime($d['check_out_date'])));
        $block->setContent('dep_total_price', number_format($d['total_price'], 2, ',', '.'));

        $trStatus = $statusTranslations[$d['status_name']] ?? $d['status_name'];
        $block->setContent('dep_status_name', htmlspecialchars($trStatus));

        $badgeClass = 'text-bg-secondary';
        $sid = (int)$d['status_id'];
        if ($sid === 3) $badgeClass = 'text-bg-success';
        elseif ($sid === 5) $badgeClass = 'text-bg-info text-white';
        $block->setContent('dep_badge_class', $badgeClass);
    }
} else {
    $block->setContent('dep_list_empty', '1');
}

$stmtInH = $db->prepare("
    SELECT b.id, b.check_in_date, b.check_out_date, b.total_price, b.status_id,
           u.first_name, u.last_name, u.email, r.room_number, rc.name AS category_name, bs.name AS status_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN rooms r ON b.room_id = r.id
    JOIN room_categories rc ON r.category_id = rc.id
    JOIN booking_statuses bs ON b.status_id = bs.id
    WHERE b.check_in_date <= ? AND b.check_out_date > ? AND b.status_id = 3
    ORDER BY b.id ASC
");
$stmtInH->execute([$todayStr, $todayStr]);
$inhouse = $stmtInH->fetchAll();
$cntInHouse = count($inhouse);
$block->setContent('cnt_in_house', $cntInHouse);

if ($cntInHouse > 0) {
    $block->setContent('inh_list_empty', '');
    foreach ($inhouse as $ih) {
        $block->setContent('inh_booking_id', $ih['id']);
        $block->setContent('inh_guest_name', htmlspecialchars($ih['first_name'] . ' ' . $ih['last_name']));
        $block->setContent('inh_guest_email', htmlspecialchars($ih['email']));
        $inh_room_number = htmlspecialchars($ih['room_number']);
        $block->setContent('inh_room_number', $inh_room_number);
        $block->setContent('inh_room_category', htmlspecialchars($ih['category_name']));
        $block->setContent('inh_check_in', date('d/m/Y', strtotime($ih['check_in_date'])));
        $block->setContent('inh_check_out', date('d/m/Y', strtotime($ih['check_out_date'])));
        $block->setContent('inh_total_price', number_format($ih['total_price'], 2, ',', '.'));

        $trStatus = $statusTranslations[$ih['status_name']] ?? $ih['status_name'];
        $block->setContent('inh_status_name', htmlspecialchars($trStatus));

        $badgeClass = 'text-bg-success';
        $block->setContent('inh_badge_class', $badgeClass);
    }
} else {
    $block->setContent('inh_list_empty', '');
}

$block->setContent('base', $GLOBALS['config']['base']);
$block->setContent('role_path', 'receptionist');

$stmtRest = $db->prepare("
    SELECT r.id, r.reservation_date, r.reservation_time, r.guests, r.meal_type, r.status,
           u.first_name, u.last_name, u.email
    FROM restaurant_reservations r
    JOIN users u ON r.user_id = u.id
    WHERE r.reservation_date = ? AND r.status IN ('Pending', 'Confirmed')
    ORDER BY r.reservation_time ASC
");
$stmtRest->execute([$todayStr]);
$rest = $stmtRest->fetchAll();
$cntRest = count($rest);
$block->setContent('cnt_restaurant', $cntRest);

if ($cntRest > 0) {
    $block->setContent('rest_list_empty', '1');
    foreach ($rest as $rt) {
        $block->setContent('rest_booking_id', $rt['id']);
        $block->setContent('rest_guest_name', htmlspecialchars($rt['first_name'] . ' ' . $rt['last_name']));
        $block->setContent('rest_guest_email', htmlspecialchars($rt['email']));
        $block->setContent('rest_time', htmlspecialchars(substr($rt['reservation_time'], 0, 5)));
        $block->setContent('rest_guests', (int)$rt['guests']);
        $block->setContent('rest_meal', htmlspecialchars($rt['meal_type']));

        $status_it = $rt['status'] === 'Confirmed' ? 'Confermata' : 'In Attesa';
        $badgeClass = $rt['status'] === 'Confirmed' ? 'text-bg-success' : 'text-bg-warning';

        $block->setContent('rest_status_name', $status_it);
        $block->setContent('rest_badge_class', $badgeClass);
    }
} else {
    $block->setContent('rest_list_empty', '');
}

setup_backoffice_page($page, 'Receptionist', 'receptionist');

$page->setContent('body', $block->get());
$page->close();
