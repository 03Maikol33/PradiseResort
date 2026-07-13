<?php

require_once __DIR__ . '/include/bootstrap.inc.php';

$skin = new_page($config['skin']);
$skin->setContent('title',     'Home');
$skin->setContent('year',      date('Y'));
$skin->setContent('base',      $config['base']);
$skin->setContent('skin',      $config['skin']);
$skin->setContent('is_logged', !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name', $_SESSION['user']['name'] ?? '');
$cartCountVal = get_cart_count();
$skin->setContent('cart_count', $cartCountVal > 0 ? '1' : '');
$skin->setContent('cart_badge', $cartCountVal > 0 ? " ($cartCountVal)" : '');


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
    $block->setContent('category_url',         $config['base'] . '/room-details.php?cat_id=' . $cat['id']);
}

//carica le recensioni
$reviews = db()->query('SELECT * FROM reviews ORDER BY created_at DESC LIMIT 4')->fetchAll();
foreach ($reviews as $review) {
    //ottieni autore
    $author = db()->prepare('SELECT first_name, last_name FROM users WHERE id = ?');
    $author->execute([$review['id']]);
    $author = $author->fetch();

    //calcolo rating
    $stars = '';
    for($i = 0; $i < $review['rating']; $i++){
        $stars .= '<i class="fas fa-star"></i>';
    }

    //ottengo la categoria cercandola tra quelle già fetchate
    $block->setContent('recensione_autore_iniziali', strtoupper($author['first_name'][0] . $author['last_name'][0]));
    $block->setContent('recensione_autore', htmlspecialchars($author['first_name'] . ' ' . $author['last_name']));
    $block->setContent('recensione_testo', htmlspecialchars($review['comment']));
    $block->setContent('recensione_stelle', $stars);
    $block->setContent('recensione_data', preg_replace( '/(\d{4})-(\d{2})-(\d{2}).*/', '$3-$2-$1', $review['created_at']));
}


$skin->setContent('body', $block->get());
$skin->close();

