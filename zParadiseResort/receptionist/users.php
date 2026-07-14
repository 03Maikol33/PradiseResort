<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

if (empty($_SESSION['user'])) {
    header("Location: {$config['base']}/login.php");
    exit;
}

if (!is_receptionist()) {
    if (is_admin()) {
        header("Location: {$config['base']}/admin/users.php");
        exit;
    }
    header("Location: {$config['base']}/index.php");
    exit;
}

require_service('users.php');

$page = new_page('administration', 'receptionist-frame-private');
$block = new_block('receptionist-users');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$block->setContent('search_query', htmlspecialchars($search));

$db = db();

        // Prenotazioni attive (2=Pending, 3=Confirmed)
$query = "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.created_at,
                 (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id AND b.status_id IN (2, 3)) AS active_bookings
          FROM users u
          JOIN user_gruppi ug ON u.id = ug.user_id
          JOIN gruppi g ON ug.group_id = g.id
          WHERE g.name = 'Guest'";

$params = [];

if ($search !== '') {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

if (count($users) > 0) {
    $block->setContent('users_list', '1');
    foreach ($users as $user) {
        $fullName = $user['first_name'] . ' ' . $user['last_name'];
        $block->setContent('user_name', htmlspecialchars($fullName));
        $block->setContent('user_email', htmlspecialchars($user['email']));

        $phoneHtml = '';
        if (!empty($user['phone'])) {
            $phoneHtml = '<p class="text-muted small mb-0"><i class="bi bi-telephone me-1"></i>' . htmlspecialchars($user['phone']) . '</p>';
        }
        $block->setContent('user_phone_html', $phoneHtml);

        $activeBookings = (int)$user['active_bookings'];
        $block->setContent('active_bookings', $activeBookings);

        if ($activeBookings > 0) {
            $badgeClass = 'bg-success text-white';
        } else {
            $badgeClass = 'bg-light text-muted border';
        }
        $block->setContent('active_bookings_badge_class', $badgeClass);

        $initials = '';
        if (!empty($user['first_name'])) {
            $initials .= strtoupper(substr($user['first_name'], 0, 1));
        }
        if (!empty($user['last_name'])) {
            $initials .= strtoupper(substr($user['last_name'], 0, 1));
        }
        if ($initials === '') {
            $initials = 'U';
        }
        $block->setContent('user_initials', htmlspecialchars($initials));

        $block->setContent('user_joined', date('d/m/Y', strtotime($user['created_at'])));
    }
} else {
    $block->setContent('users_list', '');
}

setup_backoffice_page($page, 'Receptionist', 'receptionist');
$page->setContent('body', $block->get());
$page->close();
