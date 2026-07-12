<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_service('amenities.php');

$db = db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    try {
        // Controlla se il servizio è presente in prenotazioni attive (Pending = 2, Confirmed = 3)
        $stmtCheck = $db->prepare("
            SELECT COUNT(*) 
            FROM booking_amenities ba
            JOIN bookings b ON ba.booking_id = b.id
            WHERE ba.amenity_id = ? AND b.status_id IN (2, 3)
        ");
        $stmtCheck->execute([$id]);
        $activeBookingsCount = (int)$stmtCheck->fetchColumn();
        
        if ($activeBookingsCount > 0) {
            header("Location: amenities.php?error=" . urlencode("Impossibile eliminare il servizio poiché è richiesto da " . $activeBookingsCount . " prenotazioni attive. Sospendilo per disattivarlo."));
            exit;
        }

        $stmt = $db->prepare("DELETE FROM amenities WHERE id = ?");
        if ($stmt->execute([$id])) {
            header("Location: amenities.php?success=" . urlencode("Servizio aggiuntivo eliminato con successo."));
            exit;
        } else {
            header("Location: amenities.php?error=" . urlencode("Errore durante l'eliminazione del servizio."));
            exit;
        }
    } catch (Exception $e) {
        header("Location: amenities.php?error=" . urlencode("Impossibile eliminare il servizio poiché è in uso da alcune prenotazioni."));
        exit;
    }
}

header("Location: amenities.php");
exit;
