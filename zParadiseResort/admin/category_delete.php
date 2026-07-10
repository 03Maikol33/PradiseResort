<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_service('categories.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $db = db();
    
    try {
        $stmt = $db->prepare("DELETE FROM room_categories WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            header("Location: categories.php?success=" . urlencode("Categoria eliminata con successo."));
        } else {
            header("Location: categories.php?error=" . urlencode("Categoria non trovata."));
        }
    } catch (PDOException $e) {
        // SQLSTATE 23000 indicates an integrity constraint violation (e.g. foreign key)
        if ($e->getCode() == '23000') {
            header("Location: categories.php?error=" . urlencode("Attenzione: devi prima rimuovere le stanze associate a questa categoria."));
        } else {
            // Other DB errors
            header("Location: categories.php?error=" . urlencode("Si è verificato un errore durante l'eliminazione della categoria."));
        }
    }
} else {
    header("Location: categories.php");
}
exit;
