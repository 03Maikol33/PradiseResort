<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

// Controlliamo che l'utente sia loggato e abbia i permessi di amministratore
require_admin();

$db = db();
$message = '';
$error = '';

// Gestione delle azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;

        if ($first_name === '' || $last_name === '' || $email === '' || $password === '' || ($role_id !== 1 && $role_id !== 2)) {
            $error = 'Tutti i campi sono obbligatori e il ruolo inserito deve essere valido.';
        } else {
            // Controlla se l'email esiste già
            $check = $db->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = 'Questa email è già registrata nel portale.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                try {
                    $db->beginTransaction();

                    // Inserimento utente
                    $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, password) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$first_name, $last_name, $email, $hashed_password]);
                    $user_id = $db->lastInsertId();

                    // Assegnazione gruppo (ruolo)
                    $stmtGroup = $db->prepare("INSERT INTO user_gruppi (user_id, group_id) VALUES (?, ?)");
                    $stmtGroup->execute([$user_id, $role_id]);

                    $db->commit();
                    $message = "Membro dello staff registrato con successo!";
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Errore durante la registrazione: " . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'delete') {
        $user_id_to_delete = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

        if ($user_id_to_delete === (int)$_SESSION['user']['id']) {
            $error = "Non puoi eliminare il tuo stesso account!";
        } else {
            try {
                // Verifichiamo che l'utente da eliminare sia effettivamente staff o admin
                $verify = $db->prepare("
                    SELECT g.name FROM user_gruppi ug 
                    JOIN gruppi g ON ug.group_id = g.id 
                    WHERE ug.user_id = ?
                ");
                $verify->execute([$user_id_to_delete]);
                $roles = $verify->fetchAll(PDO::FETCH_COLUMN);

                // Controlla che appartenga ad Admin o Receptionist
                $is_staff = false;
                foreach ($roles as $r) {
                    if (in_array(strtolower($r), ['admin', 'receptionist'])) {
                        $is_staff = true;
                    }
                }

                if (!$is_staff) {
                    $error = "Puoi eliminare soltanto membri dello staff (Admin o Receptionist).";
                } else {
                    $db->beginTransaction();

                    // Rimuove i gruppi dell'utente
                    $stmtGroup = $db->prepare("DELETE FROM user_gruppi WHERE user_id = ?");
                    $stmtGroup->execute([$user_id_to_delete]);

                    // Rimuove l'utente
                    $stmtUser = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmtUser->execute([$user_id_to_delete]);

                    $db->commit();
                    $message = "Account rimosso con successo.";
                }
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = "Errore durante l'eliminazione: " . $e->getMessage();
            }
        }
    }
}

// Inizializza frame e blocco
$page = new_page('administration', 'frame-private');
$block = new_block('staff');

$block->setContent('message', $message);
$block->setContent('error', $error);

// Query per recuperare tutti i membri dello staff (Admin e Receptionist)
$query = "
    SELECT u.id, u.first_name, u.last_name, u.email, g.name AS group_name 
    FROM users u
    JOIN user_gruppi ug ON u.id = ug.user_id
    JOIN gruppi g ON ug.group_id = g.id
    WHERE g.name IN ('Admin', 'Receptionist')
    ORDER BY g.name ASC, u.last_name ASC
";
$stmt = $db->query($query);
$staff_members = $stmt->fetchAll();

if (count($staff_members) > 0) {
    $block->setContent('staff_list', '1');
    foreach ($staff_members as $member) {
        $block->setContent('staff_id', $member['id']);
        $block->setContent('staff_name', htmlspecialchars($member['first_name'] . ' ' . $member['last_name']));
        $block->setContent('staff_email', htmlspecialchars($member['email']));
        
        $role = $member['group_name'];
        $badgeClass = (strtolower($role) === 'admin') ? 'text-bg-danger' : 'text-bg-info';
        $block->setContent('staff_role', '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($role) . '</span>');
        
        if ((int)$member['id'] === (int)$_SESSION['user']['id']) {
            $block->setContent('staff_action', '<span class="badge text-bg-secondary py-2 px-3">Tu</span>');
        } else {
            $block->setContent('staff_action', '
                  <button type="button" class="btn btn-outline-danger btn-sm btn-delete-staff" 
                          data-id="' . $member['id'] . '" 
                          data-name="' . htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) . '"
                          data-bs-toggle="modal" 
                          data-bs-target="#deleteConfirmModal">
                    <i class="bi bi-trash"></i> Elimina
                  </button>');
        }
    }
} else {
    $block->setContent('staff_list', '');
}

$page->setContent('body', $block->get());
$page->close();
