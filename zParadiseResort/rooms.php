<?php
require_once __DIR__ . '/include/bootstrap.inc.php';

$skin = new_page($config['skin']);
$skin->setContent('title',     'Le nostre Camere');
$skin->setContent('year',      date('Y'));
$skin->setContent('base',      $config['base']);
$skin->setContent('skin',      $config['skin']);
$skin->setContent('is_logged', !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name', $_SESSION['user']['name'] ?? '');

$block = new_block('rooms');

// Query per recuperare le stanze disponibili
$stmt = $db->query("
    SELECT r.id as room_id, r.room_number, c.name, c.base_price, c.image_url, c.description
    FROM rooms r
    JOIN room_categories c ON r.category_id = c.id
    WHERE r.status = 'available'
");
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
    
    // Aggiungo il numero della stanza al nome
    $room['name'] .= " (Camera " . $room['room_number'] . ")";
    
    $block->setContent('room_image', $room['image_url']);
    $block->setContent('room_name', $room['name']);
    $block->setContent('room_price', $room['base_price']);
    $block->setContent('room_desc', $room['description']);
}

$skin->setContent('body', $block->get());
$skin->close();
