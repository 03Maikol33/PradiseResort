<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_service('amenities.php');

$page = new_page('administration', 'frame-private');
$block = new_block('amenity_edit');

$db = db();

$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;

$error = '';
$success = '';

$amenity = [
    'id' => 0,
    'name' => '',
    'description' => '',
    'price' => '',
    'image_url' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? '';
    $image_url = trim($_POST['image_url'] ?? '');

    if (empty($name) || empty($price)) {
        $error = "Nome e Prezzo sono obbligatori.";
    } else {
        $price = str_replace(',', '.', $price);
        if (!is_numeric($price) || $price < 0) {
            $error = "Il prezzo deve essere un numero valido.";
        } else {
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE amenities SET name = ?, description = ?, price = ?, image_url = ? WHERE id = ?");
                if ($stmt->execute([$name, $description, $price, $image_url, $id])) {
                    header("Location: amenities.php?success=" . urlencode("Servizio aggiuntivo aggiornato con successo."));
                    exit;
                } else {
                    $error = "Errore durante l'aggiornamento del servizio.";
                }
            } else {
                $stmt = $db->prepare("INSERT INTO amenities (name, description, price, image_url) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$name, $description, $price, $image_url])) {
                    header("Location: amenities.php?success=" . urlencode("Servizio aggiuntivo creato con successo."));
                    exit;
                } else {
                    $error = "Errore durante la creazione del servizio.";
                }
            }
        }
    }

    $amenity = [
        'id' => $id,
        'name' => $name,
        'description' => $description,
        'price' => $price,
        'image_url' => $image_url
    ];
} elseif ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM amenities WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $amenity = $existing;
    } else {
        header("Location: amenities.php?error=" . urlencode("Servizio aggiuntivo non trovato."));
        exit;
    }
}

$block->setContent('page_title', $id > 0 ? 'Modifica Servizio Aggiuntivo' : 'Aggiungi Servizio Aggiuntivo');
$block->setContent('action_text', $id > 0 ? 'Aggiorna' : 'Salva');
$block->setContent('am_id', $amenity['id']);
$block->setContent('am_name', htmlspecialchars($amenity['name']));
$block->setContent('am_description', htmlspecialchars($amenity['description'] ?? ''));
$block->setContent('am_price', htmlspecialchars($amenity['price']));
$block->setContent('am_image_url', htmlspecialchars($amenity['image_url'] ?? ''));

$block->setContent('error_msg', htmlspecialchars($error));
$block->setContent('show_error', $error ? '1' : '');

setup_backoffice_page($page, 'Amministratore', 'admin');
$page->setContent('body', $block->get());
$page->close();
