<?php

/*
 * Connessione al database via PDO (PHP Data Objects, una libreria PHP per interagire con i database).
 * Singleton. Si ottiene l'istanza con db().
 * Configurazione db in config.php.
 */

function db(): PDO {

    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    //prendo configurazione db da config.php
    global $config;
    $c = $config['db'];

    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";

    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        //mappa errori query in eccezioni.
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        //uso il nome della colonna come chiave dell'array associativo restituito da fetch() e fetchAll().
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        //contro sql injection
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

$db = db();
