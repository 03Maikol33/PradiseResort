<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_service('services.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;

    if ($group_id > 0 && $service_id > 0) {
        $db = db();

        $stmtCheck = $db->prepare("SELECT 1 FROM group_services WHERE group_id = ? AND service_id = ?");
        $stmtCheck->execute([$group_id, $service_id]);

        if (!$stmtCheck->fetch()) {
            $stmtInsert = $db->prepare("INSERT INTO group_services (group_id, service_id) VALUES (?, ?)");
            if ($stmtInsert->execute([$group_id, $service_id])) {
                header("Location: {$config['base']}/admin/services.php?success=" . urlencode("Permesso assegnato con successo."));
                exit;
            } else {
                header("Location: {$config['base']}/admin/services.php?error=" . urlencode("Errore durante l'assegnazione del permesso."));
                exit;
            }
        } else {
            header("Location: {$config['base']}/admin/services.php?error=" . urlencode("Il gruppo ha già questo permesso."));
            exit;
        }
    } else {
        header("Location: {$config['base']}/admin/services.php?error=" . urlencode("Dati non validi."));
        exit;
    }
}

header("Location: {$config['base']}/admin/services.php");
exit;
