<?php

require_once __DIR__ . '/include/bootstrap.inc.php';

$skin = new_page($config['skin']);
$skin->setContent('title',     'Home');
$skin->setContent('year',      date('Y'));
$skin->setContent('base',      $config['base']);
$skin->setContent('skin',      $config['skin']);
$skin->setContent('is_logged', !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name', $_SESSION['user']['name'] ?? '');

$block = new_block('index');

$skin->setContent('body', $block->get());
$skin->close();
