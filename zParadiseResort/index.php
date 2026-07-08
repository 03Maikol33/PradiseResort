<?php

require_once __DIR__ . '/include/bootstrap.inc.php';

$skin = new_page($config['skin']);
$skin->setContent('title',     'Home');
$skin->setContent('year',      date('Y'));
$skin->setContent('base',      $config['base']);
$skin->setContent('skin',      $config['skin']);
$skin->setContent('is_logged', !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name', $_SESSION['user']['name'] ?? '');
$skin->setContent('cart_count', !empty($_SESSION['cart']) ? '1' : '');
$skin->setContent('cart_badge', !empty($_SESSION['cart']) ? ' (1)' : '');


$block = new_block('index');

// Carica le categorie di stanze dal database
$categories = db()->query('SELECT * FROM room_categories ORDER BY base_price ASC')->fetchAll();
foreach ($categories as $cat) {
    $block->setContent('category_name',        htmlspecialchars($cat['name']));
    $block->setContent('category_description', htmlspecialchars($cat['description']));
    $block->setContent('category_price',       number_format($cat['base_price'], 0, ',', '.')); // senza decimali per l'estetica se preferito, o con 2
    $block->setContent('category_capacity',    $cat['capacity']);
    
    // Se l'immagine non è specificata o non esiste, usiamo un default
    $img = $cat['image_url'] ? $cat['image_url'] : 'room1.jpg';
    $block->setContent('category_image_path',  $config['base'] . '/skins/' . $config['skin'] . '/assets/img/rooms/' . htmlspecialchars($img));
    $block->setContent('category_url',         $config['base'] . '/rooms.php?category_id=' . $cat['id']);
}

$skin->setContent('body', $block->get());
$skin->close();

