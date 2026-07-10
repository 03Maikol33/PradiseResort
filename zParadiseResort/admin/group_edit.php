<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_service('services.php');

$page = new_page('administration', 'frame-private');
$block = new_block('group_edit');

$db = db();
$error_msg = '';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $error_msg = 'Il nome del gruppo è obbligatorio.';
    } else {
        if ($id > 0) {
            // Check if editing Admin group and changing its name
            if ($id === 1 && strtolower($name) !== 'admin') {
                $error_msg = 'Impossibile modificare il nome del gruppo Admin principale.';
            } else {
                $stmt = $db->prepare("UPDATE gruppi SET name = ?, description = ? WHERE id = ?");
                if ($stmt->execute([$name, $description, $id])) {
                    header("Location: {$config['base']}/admin/services.php?success=" . urlencode("Gruppo aggiornato."));
                    exit;
                } else {
                    $error_msg = 'Errore durante l\'aggiornamento.';
                }
            }
        } else {
            $stmt = $db->prepare("INSERT INTO gruppi (name, description) VALUES (?, ?)");
            if ($stmt->execute([$name, $description])) {
                header("Location: {$config['base']}/admin/services.php?success=" . urlencode("Gruppo creato."));
                exit;
            } else {
                $error_msg = 'Errore durante la creazione.';
            }
        }
    }
}

// Pre-fill per Edit
if ($id > 0 && empty($error_msg)) {
    $stmt = $db->prepare("SELECT * FROM gruppi WHERE id = ?");
    $stmt->execute([$id]);
    $group = $stmt->fetch();
    if ($group) {
        $block->setContent('group_id', $group['id']);
        $block->setContent('group_name', htmlspecialchars($group['name']));
        $block->setContent('group_description', htmlspecialchars($group['description'] ?? ''));
        $block->setContent('page_title', 'Modifica Gruppo Utenti');
        $block->setContent('action_text', 'Aggiorna Gruppo');
    } else {
        header("Location: {$config['base']}/admin/services.php?error=" . urlencode("Gruppo non trovato."));
        exit;
    }
} else {
    // Add mode o errore form
    $block->setContent('group_id', $id);
    $block->setContent('group_name', htmlspecialchars($_POST['name'] ?? ''));
    $block->setContent('group_description', htmlspecialchars($_POST['description'] ?? ''));
    $block->setContent('page_title', 'Nuovo Gruppo Utenti');
    $block->setContent('action_text', 'Crea Gruppo');
}

$block->setContent('error_msg', htmlspecialchars($error_msg));
$block->setContent('show_error', $error_msg ? '1' : '');

setup_backoffice_page($page, 'Amministratore', 'admin');
$page->setContent('body', $block->get());
$page->close();
