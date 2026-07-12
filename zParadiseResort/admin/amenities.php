<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_admin();

$page = new_page('administration', 'frame-private');
$block = new_block('amenities');

$db = db();

// Gestione dell'aggiornamento dello stato (sospensione) tramite POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $db->prepare("UPDATE amenities SET is_suspended = 1 - is_suspended WHERE id = ?");
                if ($stmt->execute([$id])) {
                    header("Location: amenities.php?success=" . urlencode("Stato del servizio aggiornato con successo."));
                    exit;
                } else {
                    header("Location: amenities.php?error=" . urlencode("Errore durante l'aggiornamento dello stato."));
                    exit;
                }
            } catch (Exception $e) {
                header("Location: amenities.php?error=" . urlencode("Errore di database: " . $e->getMessage()));
                exit;
            }
        }
    }
}

// Check for success or error messages
$success_msg = isset($_GET['success']) ? trim($_GET['success']) : '';
$error_msg = isset($_GET['error']) ? trim($_GET['error']) : '';

$block->setContent('success_msg', htmlspecialchars($success_msg));
$block->setContent('error_msg', htmlspecialchars($error_msg));
$block->setContent('show_success', $success_msg ? '1' : '');
$block->setContent('show_error', $error_msg ? '1' : '');

$query = "SELECT id, name, description, price, image_url, is_suspended FROM amenities ORDER BY id ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$amenities = $stmt->fetchAll();

if (count($amenities) > 0) {
    $block->setContent('amenities_list', '1');
    foreach ($amenities as $amenity) {
        $block->setContent('am_id', $amenity['id']);
        $block->setContent('am_name', htmlspecialchars($amenity['name']));
        $block->setContent('am_price', number_format($amenity['price'], 2, ',', '.'));
        
        $imgUrl = htmlspecialchars($amenity['image_url'] ?? '');
        if ($imgUrl !== '') {
            $imgHtml = '<img src="' . $imgUrl . '" alt="' . htmlspecialchars($amenity['name']) . '" class="rounded" style="width: 50px; height: 35px; object-fit: cover; border: 1px solid #cbd5e1;">';
        } else {
            $imgHtml = '<span class="text-muted small" style="font-style: italic;">Nessuna</span>';
        }
        $block->setContent('am_image_html', $imgHtml);
        
        $statusClass = $amenity['is_suspended'] ? 'text-bg-warning' : 'text-bg-success';
        $statusLabel = $amenity['is_suspended'] ? 'Sospeso' : 'Attivo';
        $block->setContent('am_status', '<span class="badge ' . $statusClass . '">' . $statusLabel . '</span>');
        
        $btnText = $amenity['is_suspended'] ? 'Attiva' : 'Sospendi';
        $btnClass = $amenity['is_suspended'] ? 'btn-outline-success' : 'btn-outline-warning';
        $block->setContent('am_toggle_btn', '
            <form action="" method="POST" style="display:inline; margin:0;" onsubmit="return confirm(\'Sei sicuro di voler ' . strtolower($btnText) . ' questo servizio?\');">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="id" value="' . $amenity['id'] . '">
                <button type="submit" class="btn btn-sm ' . $btnClass . '" title="' . $btnText . '"><i class="bi ' . ($amenity['is_suspended'] ? 'bi-check-circle' : 'bi-slash-circle') . '"></i> ' . $btnText . '</button>
            </form>');
        
        // Shorten description for table view
        $desc = htmlspecialchars($amenity['description'] ?? '');
        if (strlen($desc) > 80) {
            $desc = substr($desc, 0, 77) . '...';
        }
        $block->setContent('am_desc', $desc);
    }
} else {
    $block->setContent('amenities_list', ''); 
}

setup_backoffice_page($page, 'Amministratore', 'admin');
$page->setContent('body', $block->get());
$page->close();
