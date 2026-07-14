<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

// Controlla l'accesso basato sul servizio
require_service('users.php');

// Inizializza la pagina con il layout privato del receptionist
$page = new_page('administration', 'receptionist-frame-private');
$block = new_block('receptionist-users');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$block->setContent('search_query', htmlspecialchars($search));

$db = db();

// Query per estrarre solo i clienti registrati (Guest) e calcolare le loro prenotazioni attive
// Le prenotazioni attive sono definite con status_id IN (2, 3) (Pending = 2, Confirmed = 3)
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
    $block->setContent('users_list', '1'); // per la logica ifempty
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
        
        // Assegna una classe badge elegante a seconda del numero di prenotazioni
        if ($activeBookings > 0) {
            $badgeClass = 'bg-success text-white';
        } else {
            $badgeClass = 'bg-light text-muted border';
        }
        $block->setContent('active_bookings_badge_class', $badgeClass);
        
        // Calcola le iniziali dell'utente per l'avatar circolare
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
