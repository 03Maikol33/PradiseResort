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

    global $config;
    $c = $config['db'];

    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";

    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

$db = db();
