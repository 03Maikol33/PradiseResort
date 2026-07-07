<?php

/*
 * Bootstrap dell'applicazione: punto di ingresso comune ad ogni script.
 * È il file che ogni pagina PHP includerà come prima cosa, per inizializzare l'ambiente.
 * Carica config, sessione, template engine, connessione DB.
 */

session_start();

// Inizializza $_SESSION['user'] come array vuoto se non esiste:
// il template engine itera su $_SESSION['user'] e darebbe warning
// quando l'utente non e' ancora loggato.
if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = [];
}

require_once __DIR__ . '/config.inc.php';
require_once __DIR__ . '/../template2.inc.php';
require_once __DIR__ . '/db.inc.php';
require_once __DIR__ . '/page.inc.php';
require_once __DIR__ . '/auth.inc.php';
