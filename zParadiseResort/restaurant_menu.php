<?php
require_once __DIR__ . '/include/bootstrap.inc.php';

$skin = new_page($config['skin']);
$skin->setContent('title',      'Menù Ristorante');
$skin->setContent('year',       date('Y'));
$skin->setContent('base',       $config['base']);
$skin->setContent('skin',       $config['skin']);
$skin->setContent('is_logged',  !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name', !empty($_SESSION['user']['name']) ? explode(' ', $_SESSION['user']['name'])[0] : '');
$cartCountVal = get_cart_count();
$skin->setContent('cart_count', $cartCountVal > 0 ? '1' : '');
$skin->setContent('cart_badge', $cartCountVal > 0 ? " ($cartCountVal)" : '');

$block = new_block('restaurant_menu');
// Non c'è un ciclo php complesso qui perché il menù è statico, 
// ma potremmo passargli l'URL base e della skin per le immagini
$block->setContent('base', $config['base']);
$block->setContent('skin', $config['skin']);

$skin->setContent('body', $block->get());
$skin->close();
