<?php

/*
  Configurazione globale dell'applicazione.
  Contiene tutte le impostazioni che possono essere modificate per adattare l'applicazione al proprio ambiente.
  
  Le costanti NONE / FILE / MEMORY servono al template engine
  (template2.inc.php) per la modalita' di cache.
 */


define('NONE',   0);
define('FILE',   1);
define('MEMORY', 2);

$config = [

    //Database
    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'paradiseresort',
        'user'    => 'root',
        'pass'    => 'root',
        'charset' => 'utf8mb4',
    ],

    /* --- Skin e paths -------------------------------------------- */
    'skin'         => 'customers',          // frontend skin
    'admin_skin'   => 'administration',         // backend skin
    'base'         => '/progetto/zParadiseResort', // base URL relativa alla root di XAMPP
    'upload_dir'   => 'uploads',

    /* --- Cache del template engine ------------------------------- */
    'cache_folder'  => 'cache',
    'cache_mode'    => NONE,           // NONE durante lo sviluppo
    'cache_timeout' => 600,

    /* --- Lingua ------------------------- */
    'languages'        => [],
    'currentlanguage'  => 'it',
    'currenttab'       => '',
];
