<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_service('services.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $db = db();

    if ($id === 1) {
        header("Location: {$config['base']}/admin/services.php?error=" . urlencode("Impossibile eliminare il gruppo Admin principale."));
        exit;
    }

    try {
        $db->beginTransaction();

        $stmtServices = $db->prepare("DELETE FROM group_services WHERE group_id = ?");
        $stmtServices->execute([$id]);

        $stmtUsers = $db->prepare("DELETE FROM user_gruppi WHERE group_id = ?");
        $stmtUsers->execute([$id]);

        $stmt = $db->prepare("DELETE FROM gruppi WHERE id = ?");
        $stmt->execute([$id]);

        $db->commit();
        header("Location: {$config['base']}/admin/services.php?success=" . urlencode("Gruppo eliminato con successo."));
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        header("Location: {$config['base']}/admin/services.php?error=" . urlencode("Errore durante l'eliminazione: " . $e->getMessage()));
        exit;
    }
}

header("Location: {$config['base']}/admin/services.php");
exit;
