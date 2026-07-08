<?php
require_once __DIR__ . '/include/bootstrap.inc.php';

$catId = (int)($_GET['cat_id'] ?? 0);

if ($catId <= 0) {
    header('Location: ' . $config['base'] . '/index.php');
    exit;
}

$stmt = db()->prepare('SELECT * FROM room_categories WHERE id = :id');
$stmt->execute(['id' => $catId]);
$category = $stmt->fetch();

if (!$category) {
    header('Location: ' . $config['base'] . '/index.php');
    exit;
}

$skin = new_page($config['skin']);
$skin->setContent('title',     'Dettaglio ' . htmlspecialchars($category['name']));
$skin->setContent('year',      date('Y'));
$skin->setContent('base',      $config['base']);
$skin->setContent('skin',      $config['skin']);
$skin->setContent('is_logged', !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name', $_SESSION['user']['name'] ?? '');
$skin->setContent('cart_count', !empty($_SESSION['cart']) ? '1' : '');
$skin->setContent('cart_badge', !empty($_SESSION['cart']) ? ' (1)' : '');

$block = new_block('room-details');

$block->setContent('category_id',          $category['id']);
$block->setContent('category_name',        htmlspecialchars($category['name']));
$block->setContent('category_description', nl2br(htmlspecialchars($category['description'])));
$block->setContent('category_capacity',    $category['capacity']);
$block->setContent('category_price',       number_format($category['base_price'], 2, ',', '.'));

$img = $category['image_url'] ? $category['image_url'] : 'room1.jpg';
$block->setContent('category_image_path',  $config['base'] . '/skins/' . $config['skin'] . '/assets/img/rooms/' . htmlspecialchars($img));
$block->setContent('search_url',           $config['base'] . '/rooms-search.php?category_id=' . $category['id']);

$skin->setContent('body', $block->get());
$skin->close();
