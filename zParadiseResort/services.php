<?php
require_once __DIR__ . '/include/bootstrap.inc.php';

$skin = new_page($config['skin']);
$skin->setContent('title',      'Servizi Aggiuntivi');
$skin->setContent('year',       date('Y'));
$skin->setContent('base',       $config['base']);
$skin->setContent('skin',       $config['skin']);
$skin->setContent('is_logged',  !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name', !empty($_SESSION['user']['name']) ? explode(' ', $_SESSION['user']['name'])[0] : '');
$cartCountVal = get_cart_count();
$skin->setContent('cart_count', $cartCountVal > 0 ? '1' : '');
$skin->setContent('cart_badge', $cartCountVal > 0 ? " ($cartCountVal)" : '');

$block = new_block('services');
$block->setContent('base', $config['base']);
$block->setContent('skin', $config['skin']);

$db = db();
$stmt = $db->query("SELECT id, name, description, price, image_url FROM amenities WHERE is_suspended = 0 ORDER BY price ASC");
$amenities = $stmt->fetchAll(PDO::FETCH_ASSOC);

$icon_map = [
    'accesso spa' => 'fa-spa',
    'colazione in camera' => 'fa-coffee',
    'navetta aeroporto' => 'fa-plane-departure'
];

if (count($amenities) > 0) {
    $block->setContent('services_list', '1');
    foreach ($amenities as $am) {
        $key = strtolower(trim($am['name']));
        $iconClass = $icon_map[$key] ?? 'fa-concierge-bell';

        $img = $am['image_url'] ?? '';
        if ($img !== '') {
            if (strpos($img, 'http') !== 0 && strpos($img, '/') !== 0) {
                if ($img === 'spa_paradiseresort.jpg') {
                    $img = $config['base'] . '/skins/' . $config['skin'] . '/assets/img/swimmingpoolandspa/' . $img;
                } else {
                    $img = $config['base'] . '/skins/' . $config['skin'] . '/assets/img/' . $img;
                }
            }
            $serviceImageStyle = 'style="background-image: url(\'' . htmlspecialchars($img) . '\'); background-size: cover; background-position: center; height: 200px;"';
            $serviceIconHtml = '';
        } else {
            $serviceImageStyle = 'style="height: 200px;"';
            $serviceIconHtml = '<i class="fas ' . htmlspecialchars($iconClass) . '" style="font-size: 3.5rem;"></i>';
        }

        $block->setContent('service_id', $am['id']);
        $block->setContent('service_name', htmlspecialchars($am['name']));
        $block->setContent('service_desc', htmlspecialchars($am['description'] ?? ''));
        $block->setContent('service_price', number_format($am['price'], 2, ',', '.'));
        $block->setContent('service_image_style', $serviceImageStyle);
        $block->setContent('service_icon_html', $serviceIconHtml);
    }
} else {
    $block->setContent('services_list', '');
}

$skin->setContent('body', $block->get());
$skin->close();
