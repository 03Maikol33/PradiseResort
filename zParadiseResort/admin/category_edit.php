<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_service('categories.php');

$page = new_page('administration', 'frame-private');
$block = new_block('category_edit');

$db = db();

$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

$error = '';
$success = '';

// Default values
$category = [
    'id' => 0,
    'name' => '',
    'description' => '',
    'base_price' => '',
    'capacity' => '',
    'image_url' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $base_price = $_POST['base_price'] ?? '';
    $capacity = $_POST['capacity'] ?? '';
    $image_url = trim($_POST['image_url'] ?? '');

    // Validation
    if (empty($name) || empty($base_price) || empty($capacity)) {
        $error = "Nome, Prezzo Base e Capacità sono obbligatori.";
    } elseif (!is_numeric($base_price) || $base_price < 0) {
        $error = "Il prezzo base deve essere un numero valido.";
    } elseif (!is_numeric($capacity) || $capacity < 1) {
        $error = "La capacità deve essere un numero intero positivo.";
    } else {
        if ($id > 0) {
            // Update
            $stmt = $db->prepare("UPDATE room_categories SET name = ?, description = ?, base_price = ?, capacity = ?, image_url = ? WHERE id = ?");
            if ($stmt->execute([$name, $description, $base_price, $capacity, $image_url, $id])) {
                header("Location: categories.php?success=" . urlencode("Categoria aggiornata con successo."));
                exit;
            } else {
                $error = "Errore durante l'aggiornamento della categoria.";
            }
        } else {
            // Insert
            $stmt = $db->prepare("INSERT INTO room_categories (name, description, base_price, capacity, image_url) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $description, $base_price, $capacity, $image_url])) {
                header("Location: categories.php?success=" . urlencode("Categoria creata con successo."));
                exit;
            } else {
                $error = "Errore durante la creazione della categoria.";
            }
        }
    }
    
    // Repopulate form with submitted data on error
    $category = [
        'id' => $id,
        'name' => $name,
        'description' => $description,
        'base_price' => $base_price,
        'capacity' => $capacity,
        'image_url' => $image_url
    ];
} elseif ($id > 0) {
    // Fetch existing data
    $stmt = $db->prepare("SELECT * FROM room_categories WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        $category = $existing;
    } else {
        header("Location: categories.php?error=" . urlencode("Categoria non trovata."));
        exit;
    }
}

$block->setContent('page_title', $id > 0 ? 'Modifica Categoria' : 'Aggiungi Categoria');
$block->setContent('action_text', $id > 0 ? 'Aggiorna' : 'Salva');
$block->setContent('cat_id', $category['id']);
$block->setContent('cat_name', htmlspecialchars($category['name']));
$block->setContent('cat_description', htmlspecialchars($category['description']));
$block->setContent('cat_base_price', htmlspecialchars($category['base_price']));
$block->setContent('cat_capacity', htmlspecialchars($category['capacity']));
$block->setContent('cat_image_url', htmlspecialchars($category['image_url']));

$block->setContent('error_msg', htmlspecialchars($error));
$block->setContent('show_error', $error ? '1' : '');

$page->setContent('body', $block->get());
$page->close();
