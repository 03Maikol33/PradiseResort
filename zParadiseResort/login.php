<?php

require_once __DIR__ . '/include/bootstrap.inc.php';

//reindirizzamento utente loggato
if (!empty($_SESSION['user'])) {
    if (is_admin()) {
        header('Location: ' . $config['base'] . '/admin/index.php');
        exit;
    } elseif (is_receptionist()) {
        header('Location: ' . $config['base'] . '/receptionist/index.php');
        exit;
    } else {
        header('Location: ' . $config['base'] . '/index.php');
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Compila tutti i campi.';
    } else {
        $stmt = db()->prepare('SELECT id, email, first_name, last_name, CONCAT(first_name, " ", last_name) AS name, password FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if ($row && password_verify($password, $row['password'])) {
            $_SESSION['user'] = [
                'id'       => $row['id'],
                'email' => $row['email'],
                'name'     => $row['name'],
            ];

            // Carica in sessione le pagine protette a cui l'utente ha
            // accesso tramite i suoi gruppi
            $_SESSION['user']['services'] = load_user_services((int)$row['id']);

            // Controlla il ruolo dell'utente e reindirizza di conseguenza
            $roleStmt = db()->prepare(
                'SELECT g.name FROM user_gruppi ug
                 JOIN gruppi g ON g.id = ug.group_id
                 WHERE ug.user_id = ?'
            );
            $roleStmt->execute([$row['id']]);
            $roleRow = $roleStmt->fetch();
            $role = strtolower($roleRow['name'] ?? 'guest');

            if ($role === 'admin') {
                $dest = $config['base'] . '/admin/index.php';
            } elseif ($role === 'receptionist') {
                $dest = $config['base'] . '/receptionist/index.php';
            } else {
                $dest = $config['base'] . '/index.php';
            }

            header('Location: ' . $dest);
            exit;
        } else {
            $error = 'email o password errati.';
        }
    }
}

$skin = new_page($config['skin']);
$skin->setContent('title',     'Accedi');
$skin->setContent('year',      date('Y'));
$skin->setContent('base',      $config['base']);
$skin->setContent('skin',      $config['skin']);
$skin->setContent('is_logged', !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name', $_SESSION['user']['name'] ?? '');
$cartCountVal = get_cart_count();
$skin->setContent('cart_count', $cartCountVal > 0 ? '1' : '');
$skin->setContent('cart_badge', $cartCountVal > 0 ? " ($cartCountVal)" : '');

$block = new_block('login');
$block->setContent('error',    $error);
$block->setContent('email', htmlspecialchars($_POST['email'] ?? ''));

$skin->setContent('body', $block->get());
$skin->close();