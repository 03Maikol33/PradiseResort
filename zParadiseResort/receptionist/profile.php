<?php

require_once __DIR__ . '/../include/bootstrap.inc.php';

require_service('profile.php');

$db = db();

        // Auto-migrazione schema DB se colonna mancante
try {
    $db->query("SELECT phone FROM users LIMIT 1");
} catch (Exception $e) {
    $db->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL");
}
try {
    $db->query("SELECT image_url FROM users LIMIT 1");
} catch (Exception $e) {
    $db->query("ALTER TABLE users ADD COLUMN image_url VARCHAR(255) DEFAULT NULL");
}

$action = $_POST['action'] ?? '';
$success_msg = '';
$error_msg = '';

if ($action === 'update_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($firstName) || empty($lastName)) {
        $error_msg = "Nome e Cognome sono obbligatori.";
    } else {
        try {
            $stmtUpdateUser = db()->prepare('UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?');
            $stmtUpdateUser->execute([$firstName, $lastName, $phone, $_SESSION['user']['id']]);

            $_SESSION['user']['name'] = $firstName . ' ' . $lastName;
            $_SESSION['user']['surname'] = $lastName;
            $_SESSION['user']['first_name'] = $firstName;
            $_SESSION['user']['last_name'] = $lastName;
            $_SESSION['user']['phone'] = $phone;

            $success_msg = "Profilo aggiornato con successo.";
        } catch (Exception $e) {
            $error_msg = "Errore durante l'aggiornamento del profilo.";
        }
    }
}

$stmtUser = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmtUser->execute([$_SESSION['user']['id']]);
$user = $stmtUser->fetch();

$page = new_page('administration', 'receptionist-frame-private');
setup_backoffice_page($page, 'Receptionist', 'receptionist');

$block = new_block('staff-profile');

$block->setContent('success_msg', htmlspecialchars($success_msg));
$block->setContent('error_msg', htmlspecialchars($error_msg));
$block->setContent('show_success', $success_msg ? '1' : '');
$block->setContent('show_error', $error_msg ? '1' : '');

$block->setContent('user_firstname', htmlspecialchars($user['first_name']));
$block->setContent('user_lastname', htmlspecialchars($user['last_name']));
$block->setContent('user_email', htmlspecialchars($user['email']));
$block->setContent('user_phone', htmlspecialchars($user['phone'] ?? ''));

$initials = strtoupper(substr($user['first_name'] ?? '', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
$block->setContent('user_initials', htmlspecialchars($initials));

$block->setContent('user_role', 'Receptionist');

$page->setContent('body', $block->get());
$page->close();
