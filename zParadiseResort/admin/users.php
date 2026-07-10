<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_admin();

// Inizializza la pagina usando il frame privato dell'amministrazione
$page = new_page('administration', 'frame-private');
$block = new_block('users');
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$block->setContent('search_query', htmlspecialchars($search));

// Fetch categories for the dropdown
$db = db();
$stmt_groups = $db->query("SELECT id, name FROM gruppi ORDER BY name ASC");
$groups = $stmt_groups->fetchAll();

foreach ($groups as $group) {
    $block->setContent('category_id', $group['id']);
    $block->setContent('category_name', htmlspecialchars($group['name']));
    $block->setContent('category_selected', ($group['id'] == $category_id) ? 'selected' : '');
}

// Build the query
$query = "SELECT u.id, u.first_name, u.last_name, u.email, u.created_at, g.name AS group_name
          FROM users u
          LEFT JOIN user_gruppi ug ON u.id = ug.user_id
          LEFT JOIN gruppi g ON ug.group_id = g.id
          WHERE 1=1";

$params = [];

if ($search !== '') {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($category_id > 0) {
    $query .= " AND g.id = ?";
    $params[] = $category_id;
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

if (count($users) > 0) {
    $block->setContent('users_list', '1'); // For ifempty logic
    foreach ($users as $user) {
        $block->setContent('user_name', htmlspecialchars($user['first_name'] . ' ' . $user['last_name']));
        $block->setContent('user_email', htmlspecialchars($user['email']));
        
        $role = $user['group_name'] ? $user['group_name'] : 'Guest';
        $badgeClass = 'text-bg-secondary';
        if (strtolower($role) == 'admin') {
            $badgeClass = 'text-bg-danger';
        } else if (strtolower($role) == 'receptionist') {
            $badgeClass = 'text-bg-info';
        } else {
            $badgeClass = 'text-bg-success';
        }
        
        $block->setContent('user_role', '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($role) . '</span>');
        $block->setContent('user_joined', date('M d, Y', strtotime($user['created_at'])));
    }
} else {
    $block->setContent('users_list', ''); 
}

setup_backoffice_page($page, 'Amministratore', 'admin');
$page->setContent('body', $block->get());
$page->close();
