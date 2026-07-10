<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_service('services.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;

    if ($group_id > 0 && $service_id > 0) {
        $db = db();
        
        // Verifica regola di sicurezza: impedire la rimozione di services.php agli admin
        $stmtCheckAdmin = $db->prepare("
            SELECT g.name AS group_name, s.script_name 
            FROM group_services gs 
            JOIN gruppi g ON gs.group_id = g.id 
            JOIN services s ON gs.service_id = s.id 
            WHERE gs.group_id = ? AND gs.service_id = ?
        ");
        $stmtCheckAdmin->execute([$group_id, $service_id]);
        $check = $stmtCheckAdmin->fetch();
        
        if ($check) {
            if ($check['group_name'] === 'Admin' && $check['script_name'] === 'services.php') {
                header("Location: {$config['base']}/admin/services.php?error=" . urlencode("Errore: Impossibile rimuovere il permesso di gestione permessi al gruppo Admin. Rischio di lockout."));
                exit;
            }
            
            // Procedi con l'eliminazione
            $stmtDel = $db->prepare("DELETE FROM group_services WHERE group_id = ? AND service_id = ?");
            if ($stmtDel->execute([$group_id, $service_id])) {
                header("Location: {$config['base']}/admin/services.php?success=" . urlencode("Permesso rimosso con successo."));
                exit;
            } else {
                header("Location: {$config['base']}/admin/services.php?error=" . urlencode("Errore durante la rimozione del permesso."));
                exit;
            }
        } else {
            header("Location: {$config['base']}/admin/services.php?error=" . urlencode("Associazione permesso non trovata."));
            exit;
        }
        
    } else {
        header("Location: {$config['base']}/admin/services.php?error=" . urlencode("Dati non validi."));
        exit;
    }
}

header("Location: {$config['base']}/admin/services.php");
exit;
