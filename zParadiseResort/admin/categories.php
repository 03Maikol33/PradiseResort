<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_admin();

$page = new_page('administration', 'frame-private');
$block = new_block('categories');

$db = db();

$success_msg = isset($_GET['success']) ? trim($_GET['success']) : '';
$error_msg = isset($_GET['error']) ? trim($_GET['error']) : '';

$block->setContent('success_msg', htmlspecialchars($success_msg));
$block->setContent('error_msg', htmlspecialchars($error_msg));
$block->setContent('show_success', $success_msg ? '1' : '');
$block->setContent('show_error', $error_msg ? '1' : '');

$query = "SELECT id, name, description, base_price, capacity, image_url FROM room_categories ORDER BY id ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll();

if (count($categories) > 0) {
    $block->setContent('categories_list', '1');
    foreach ($categories as $category) {
        $block->setContent('cat_id', $category['id']);
        $block->setContent('cat_name', htmlspecialchars($category['name']));
        $block->setContent('cat_price', number_format($category['base_price'], 2));
        $block->setContent('cat_capacity', htmlspecialchars($category['capacity']));
        $block->setContent('cat_image', htmlspecialchars($category['image_url'] ? $category['image_url'] : ''));

        $desc = htmlspecialchars($category['description']);
        if (strlen($desc) > 50) {
            $desc = substr($desc, 0, 47) . '...';
        }
        $block->setContent('cat_desc', $desc);
    }
} else {
    $block->setContent('categories_list', '');
}

$page->setContent('body', $block->get());
$page->close();
