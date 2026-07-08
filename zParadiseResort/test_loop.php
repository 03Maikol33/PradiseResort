<?php
require 'include/bootstrap.inc.php';

$block = new_block('test_loop');

$data = [
    ['name' => 'Room 1', 'price' => '100'],
    ['name' => 'Room 2', 'price' => '200']
];

$block->setContent('room', $data);

print_r($block->get());
