<?php
require_once __DIR__ . '/include/bootstrap.inc.php';

// reindirizzamento se già loggato
if (!empty($_SESSION['user'])) {
    header('Location: ' . $config['base'] . '/index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
        $error = 'Compila tutti i campi.';
    } else {
        // Controllo se l'email esiste già
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email già registrata.';
        } else {
            // Hash della password e inserimento
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare('INSERT INTO users (first_name, last_name, email, password) VALUES (?, ?, ?, ?)');
            $stmt->execute([$firstName, $lastName, $email, $hashedPassword]);
            
            $userId = db()->lastInsertId();
            
            //assegna automaticamente il truolo customer, ovvero il gruppo guest [id 3 nel db]
            $stmtGroup = db()->prepare('INSERT INTO user_gruppi (user_id, group_id) VALUES (?, ?)');
            $stmtGroup->execute([$userId, 3]);

            $success = 'Registrazione completata! Ora puoi effettuare il login.';
        }
    }
}

$skin = new_page($config['skin']);
$skin->setContent('title',     'Registrazione');
$skin->setContent('year',      date('Y'));
$skin->setContent('base',      $config['base']);
$skin->setContent('skin',      $config['skin']);
$skin->setContent('is_logged', !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name', $_SESSION['user']['name'] ?? '');
$cartCountVal = get_cart_count();
$skin->setContent('cart_count', $cartCountVal > 0 ? '1' : '');
$skin->setContent('cart_badge', $cartCountVal > 0 ? " ($cartCountVal)" : '');

$block = new_block('register');
$block->setContent('error', $error);
$block->setContent('success', $success);

$skin->setContent('body', $block->get());
$skin->close();