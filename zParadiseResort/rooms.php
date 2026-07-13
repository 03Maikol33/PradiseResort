<?php
require_once __DIR__ . '/include/bootstrap.inc.php';

$skin = new_page($config['skin']);
$skin->setContent('title',     'Le nostre Camere');
$skin->setContent('year',      date('Y'));
$skin->setContent('base',      $config['base']);
$skin->setContent('skin',      $config['skin']);
$skin->setContent('is_logged', !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name', !empty($_SESSION['user']['name']) ? explode(' ', $_SESSION['user']['name'])[0] : '');

$block = new_block('rooms');

// Recupera le categorie per i filtri
$stmtCat = $db->query("SELECT id, name FROM room_categories ORDER BY base_price ASC");
$categories = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

$selectedCatId = (int)($_GET['category_id'] ?? 0);

foreach ($categories as $cat) {
    $isActive = ($cat['id'] == $selectedCatId);
    $block->setContent('filter_cat_id', $cat['id']);
    $block->setContent('filter_cat_name', htmlspecialchars($cat['name']));
    $block->setContent('filter_class', $isActive ? 'active-filter' : '');
    $block->setContent('filter_style', $isActive ? 'background-color: #0abab5; color: #fff;' : 'background-color: #f0f0f0; color: #333;');
    $block->setContent('filter_url', $config['base'] . '/rooms.php?category_id=' . $cat['id']);
}
$isAllActive = ($selectedCatId == 0);
$block->setContent('filter_all_class', $isAllActive ? 'active-filter' : '');
$block->setContent('filter_all_style', $isAllActive ? 'background-color: #0abab5; color: #fff;' : 'background-color: #f0f0f0; color: #333;');
$block->setContent('filter_all_url', $config['base'] . '/rooms.php');

// Query per recuperare le stanze disponibili
$query = "
    SELECT r.id as room_id, r.category_id, r.room_number, c.name, c.base_price, c.image_url, c.description
    FROM rooms r
    JOIN room_categories c ON r.category_id = c.id
    WHERE r.status = 'available'
";
$params = [];
if ($selectedCatId > 0) {
    $query .= " AND c.id = :cat_id";
    $params['cat_id'] = $selectedCatId;
}

$stmt = $db->prepare($query);
$stmt->execute($params);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rooms as $room) {
    // Mappatura immagini db a immagini reali del template
    $img_map = [
        'singola.jpg' => 'room1.jpg',
        'doppia.jpg'  => 'room2.jpg',
        'suite.jpg'   => 'room3.jpg'
    ];
    $db_img = $room['image_url'];
    if (isset($img_map[$db_img])) {
        $room['image_url'] = $img_map[$db_img];
    }
    
    // Normalizzazione URL immagine
    if (empty($room['image_url'])) {
        $room['image_url'] = $config['base'] . '/skins/' . $config['skin'] . '/assets/img/rooms/room1.jpg'; // fallback
    } else {
        if (strpos($room['image_url'], 'http') !== 0 && strpos($room['image_url'], '/') !== 0) {
            $room['image_url'] = $config['base'] . '/skins/' . $config['skin'] . '/assets/img/rooms/' . $room['image_url'];
        }
    }
    
    $room['name'] .= " (Camera " . $room['room_number'] . ")";
    
    $block->setContent('room_image', $room['image_url']);
    $block->setContent('room_name', $room['name']);
    $block->setContent('room_price', $room['base_price']);
    $block->setContent('room_desc', $room['description']);
    $block->setContent('category_url', $config['base'] . '/room-details.php?cat_id=' . $room['category_id']);
}

$skin->setContent('body', $block->get());
$skin->close();
