<?php

/*
 * Funzioni di controllo accesso.
 * Incluso nelle pagine che richiedono autenticazione o privilegi specifici.
 */

function require_login(): void {
    global $config;
    if (empty($_SESSION['user'])) {
        header('Location: ' . $config['base'] . '/login.php');
        exit;
    }
}

function is_admin(): bool {
    if (empty($_SESSION['user']['id'])) {
        return false;
    }
    $stmt = db()->prepare(
        'SELECT 1 FROM user_gruppi ug
         JOIN gruppi g ON g.id = ug.group_id
         WHERE ug.user_id = ? AND g.name = ?'
    );
    $stmt->execute([$_SESSION['user']['id'], 'Admin']);
    return (bool)$stmt->fetch();
}

function is_receptionist(): bool {
    if (empty($_SESSION['user']['id'])) {
        return false;
    }
    $stmt = db()->prepare(
        'SELECT 1 FROM user_gruppi ug
         JOIN gruppi g ON g.id = ug.group_id
         WHERE ug.user_id = ? AND g.name = ?'
    );
    $stmt->execute([$_SESSION['user']['id'], 'Receptionist']);
    return (bool)$stmt->fetch();
}

function is_staff(): bool {
    if (empty($_SESSION['user']['id'])) {
        return false;
    }
    $stmt = db()->prepare(
        'SELECT 1 FROM user_gruppi ug
         JOIN gruppi g ON g.id = ug.group_id
         WHERE ug.user_id = ? AND g.name IN (?, ?)'
    );
    $stmt->execute([$_SESSION['user']['id'], 'Admin', 'Receptionist']);
    return (bool)$stmt->fetch();
}

/*
 * Carica i servizi (pagine protette) a cui l'utente ha accesso, risolti
 * tramite la catena utente → gruppi → servizi. Restituisce una mappa
 * associativa: chiave = nome del servizio (= nome dello script), valore = true.
 */
function load_user_services(int $userId): array {
    $stmt = db()->prepare(
        'SELECT DISTINCT s.script_name
         FROM user_gruppi ug
         JOIN group_services gs ON gs.group_id = ug.group_id
         JOIN services s ON s.id = gs.service_id
         WHERE ug.user_id = ?'
    );
    $stmt->execute([$userId]);
    // Creiamo una mappa chiave-valore con i nomi degli script consentiti
    return array_fill_keys(array_column($stmt->fetchAll(), 'script_name'), true);
}

/*
 * Verifica se l'utente loggato ha accesso a un servizio.
 * I servizi vengono caricati in sessione al login (login.php).
 */
function has_service(string $service): bool {
    return isset($_SESSION['user']['services'][$service]);
}

/*
 * Autorizzazione basata sui Servizi: richiede che lo script corrente (o quello
 * indicato) sia tra i servizi concessi ai gruppi dell'utente.
 */
function require_service(?string $service = null): void {
    require_login();
    $service = $service ?? basename($_SERVER['SCRIPT_NAME']);
    if (!has_service($service)) {
        http_response_code(403);
        die('Accesso negato: servizio non autorizzato.');
    }
}

/*
 * Gate delle pagine del backoffice. L'accesso è autorizzato tramite il
 * meccanismo dei Servizi: lo script corrente deve essere un servizio
 * assegnato a un gruppo dell'utente (nel seed tutti i servizi del backoffice
 * sono concessi al gruppo "admin").
 */
function require_admin(): void {
    require_service();
}
function block_admin(): void {
    global $config;
    if (!empty($_SESSION['user']) && is_admin()) {
        header('Location: ' . $config['base'] . '/admin/index.php');
        exit;
    }
}
