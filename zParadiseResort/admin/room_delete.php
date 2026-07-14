<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_service('rooms.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $db = db();

    try {
        $stmt = $db->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            header("Location: rooms.php?success=" . urlencode("Stanza eliminata con successo."));
        } else {
            header("Location: rooms.php?error=" . urlencode("Stanza non trovata."));
        }
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            header("Location: rooms.php?error=" . urlencode("Impossibile eliminare: la stanza è associata a delle prenotazioni o a ticket di manutenzione."));
        } else {
            header("Location: rooms.php?error=" . urlencode("Si è verificato un errore durante l'eliminazione della stanza."));
        }
    }
} else {
    header("Location: rooms.php");
}
exit;
