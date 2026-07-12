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

// Migrazione autogestita (Self-Healing) per le note dello staff sulle prenotazioni
try {
    db()->query("SELECT staff_notes FROM bookings LIMIT 1");
} catch (Exception $e) {
    db()->query("ALTER TABLE bookings ADD COLUMN staff_notes TEXT DEFAULT NULL");
}

// Migrazione autogestita per la registrazione dei servizi e dei permessi
try {
    $db = db();
    
    // 1. Definiamo i servizi di default e le loro assegnazioni ai gruppi
    $defaultServices = [
        'index.php' => [
            'description' => 'Dashboard principale',
            'groups' => ['Admin', 'Receptionist']
        ],
        'rooms.php' => [
            'description' => 'Gestione Camere',
            'groups' => ['Admin', 'Receptionist']
        ],
        'bookings.php' => [
            'description' => 'Gestione Prenotazioni',
            'groups' => ['Admin', 'Receptionist']
        ],
        'users.php' => [
            'description' => 'Gestione Utenti Registrati',
            'groups' => ['Admin']
        ],
        'staff.php' => [
            'description' => 'Gestione Staff',
            'groups' => ['Admin']
        ],
        'services.php' => [
            'description' => 'Gestione Permessi',
            'groups' => ['Admin']
        ],
        'categories.php' => [
            'description' => 'Gestione Categorie Camere',
            'groups' => ['Admin']
        ],
        'activity.php' => [
            'description' => 'Gestione Attività',
            'groups' => ['Admin', 'Receptionist']
        ],
        'profile.php' => [
            'description' => 'Area personale',
            'groups' => ['Admin', 'Receptionist', 'Guest']
        ],
        'restaurant_bookings.php' => [
            'description' => 'Gestione Prenotazioni Ristorante',
            'groups' => ['Admin', 'Receptionist']
        ],
        'segnalazioni.php' => [
            'description' => 'Gestione Segnalazioni',
            'groups' => ['Admin', 'Receptionist']
        ],
        'amenities.php' => [
            'description' => 'Gestione Servizi Aggiuntivi',
            'groups' => ['Admin']
        ],
        'requested_services.php' => [
            'description' => 'Servizi Richiesti Dai Clienti',
            'groups' => ['Admin', 'Receptionist']
        ]
    ];

    // 2. Rinominiamo i vecchi script_name ereditati da precedenti seed
    $legacyMap = [
        'admin_dashboard.php' => 'index.php',
        'manage_rooms.php' => 'rooms.php',
        'manage_bookings.php' => 'bookings.php',
        'manage_users.php' => 'users.php'
    ];
    foreach ($legacyMap as $old => $new) {
        $stmt = $db->prepare("UPDATE services SET script_name = ? WHERE script_name = ?");
        $stmt->execute([$new, $old]);
    }
    
    // 3. Recuperiamo la mappa dinamica dei gruppi con i loro ID effettivi
    $groupsByName = [];
    $stmt = $db->query("SELECT id, name FROM gruppi");
    while ($row = $stmt->fetch()) {
        $groupsByName[$row['name']] = (int)$row['id'];
    }
    
    // 4. Verifichiamo ed inseriamo ogni servizio
    foreach ($defaultServices as $script => $data) {
        $stmt = $db->prepare("SELECT id FROM services WHERE script_name = ?");
        $stmt->execute([$script]);
        $serviceId = $stmt->fetchColumn();
        
        if (!$serviceId) {
            $ins = $db->prepare("INSERT INTO services (script_name, description) VALUES (?, ?)");
            $ins->execute([$script, $data['description']]);
            $serviceId = $db->lastInsertId();
        }
        
        // Associa il servizio ai gruppi configurati
        foreach ($data['groups'] as $groupName) {
            if (isset($groupsByName[$groupName])) {
                $groupId = $groupsByName[$groupName];
                $stmtCheck = $db->prepare("SELECT 1 FROM group_services WHERE group_id = ? AND service_id = ?");
                $stmtCheck->execute([$groupId, $serviceId]);
                if (!$stmtCheck->fetch()) {
                    $stmtIns = $db->prepare("INSERT INTO group_services (group_id, service_id) VALUES (?, ?)");
                    $stmtIns->execute([$groupId, $serviceId]);
                }
            }
        }
    }
    
    // 5. check and add image_url column to amenities table
    try {
        $db->query("SELECT image_url FROM amenities LIMIT 1");
    } catch (Exception $e) {
        $db->query("ALTER TABLE amenities ADD COLUMN image_url VARCHAR(255) DEFAULT NULL");
        
        // Update existing amenities with beautiful Unsplash images as seeds
        $db->query("UPDATE amenities SET image_url = 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=800&q=80' WHERE LOWER(name) LIKE '%spa%'");
        $db->query("UPDATE amenities SET image_url = 'https://images.unsplash.com/photo-1533089860892-a7c6f0a88666?auto=format&fit=crop&w=800&q=80' WHERE LOWER(name) LIKE '%colazione%'");
        $db->query("UPDATE amenities SET image_url = 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&w=800&q=80' WHERE LOWER(name) LIKE '%navetta%'");
    }

    // 6. check and add is_suspended column to amenities table
    try {
        $db->query("SELECT is_suspended FROM amenities LIMIT 1");
    } catch (Exception $e) {
        $db->query("ALTER TABLE amenities ADD COLUMN is_suspended TINYINT DEFAULT 0");
    }

    // 7. check and create restaurant_reservations table
    try {
        $db->query("SELECT 1 FROM restaurant_reservations LIMIT 1");
    } catch (Exception $e) {
        $db->query("
            CREATE TABLE restaurant_reservations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                reservation_date DATE NOT NULL,
                meal_type ENUM('Pranzo', 'Cena') NOT NULL,
                reservation_time TIME NOT NULL,
                guests INT NOT NULL DEFAULT 1,
                status ENUM('Pending', 'Confirmed', 'Cancelled') DEFAULT 'Pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
    }

    // 8. check and create ticket_statuses table
    try {
        $db->query("SELECT 1 FROM ticket_statuses LIMIT 1");
    } catch (Exception $e) {
        $db->query("
            CREATE TABLE ticket_statuses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL
            )
        ");
        $db->query("INSERT INTO ticket_statuses (id, name) VALUES (1, 'Open'), (2, 'In Progress'), (3, 'Resolved')");
    }

    // 9. check and create maintenance_tickets table
    try {
        $db->query("SELECT 1 FROM maintenance_tickets LIMIT 1");
    } catch (Exception $e) {
        $db->query("
            CREATE TABLE maintenance_tickets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                room_id INT DEFAULT NULL,
                reported_by_user_id INT NOT NULL,
                status_id INT NOT NULL,
                issue_description TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
                FOREIGN KEY (reported_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (status_id) REFERENCES ticket_statuses(id) ON DELETE RESTRICT
            )
        ");
    }
} catch (Exception $e) {
    // Gestione silenziosa degli errori in migrazione
}


function get_cart_count(): int {
    if (empty($_SESSION['user']['id'])) {
        return 0;
    }
    try {
        // Auto-pulizia dei carrelli inattivi ("In Cart" da più di 30 minuti)
        $expireTime = date('Y-m-d H:i:s', time() - 30 * 60);
        $stmtExpired = db()->prepare('SELECT id FROM bookings WHERE status_id = 1 AND created_at < ?');
        $stmtExpired->execute([$expireTime]);
        $expiredIds = $stmtExpired->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($expiredIds)) {
            $placeholders = implode(',', array_fill(0, count($expiredIds), '?'));
            
            $delInv = db()->prepare("DELETE FROM invoices WHERE booking_id IN ($placeholders)");
            $delInv->execute($expiredIds);
            
            $delAmen = db()->prepare("DELETE FROM booking_amenities WHERE booking_id IN ($placeholders)");
            $delAmen->execute($expiredIds);
            
            $delBook = db()->prepare("DELETE FROM bookings WHERE id IN ($placeholders)");
            $delBook->execute($expiredIds);
        }

        $stmt = db()->prepare('SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND status_id = 1');
        $stmt->execute([$_SESSION['user']['id']]);
        $row = $stmt->fetch();
        return (int)($row['count'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}
