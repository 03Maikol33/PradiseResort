<?php
require_once __DIR__ . '/include/bootstrap.inc.php';

$creazione = file_get_contents(__DIR__ . '/../sql/creazione.sql');
$popolamento = file_get_contents(__DIR__ . '/../sql/popolamento.sql');

try {
    db()->exec($creazione);
    echo "Creazione OK\n";
    db()->exec($popolamento);
    echo "Popolamento OK\n";
} catch (PDOException $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}
