<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

// Controlliamo che l'utente sia loggato e sia Admin
if (empty($_SESSION['user'])) {
    header("Location: {$config['base']}/login.php");
    exit;
}

if (!is_admin()) {
    if (is_receptionist()) {
        header("Location: {$config['base']}/receptionist/index.php");
        exit;
    }
    header("Location: {$config['base']}/index.php");
    exit;
}

// Inizializza la pagina usando il frame privato dell'amministrazione
$page = new_page('administration', 'frame-private');
$block = new_block('dashboard');

// Eseguiamo le query per i dati della dashboard
$db = db();

// 1. Ricavi Totali (Confirmed e Completed)
$stmtRev = $db->query("SELECT SUM(total_price) AS rev FROM bookings WHERE status_id IN (3, 5)");
$rowRev = $stmtRev->fetch();
$totalRevenue = number_format($rowRev['rev'] ?? 0.00, 2, ',', '.');

// 2. Prenotazioni Totali
$stmtBookings = $db->query("SELECT COUNT(*) AS cnt FROM bookings");
$rowBookings = $stmtBookings->fetch();
$totalBookings = $rowBookings['cnt'] ?? 0;

// 3. Prenotazioni in Attesa (Pending)
$stmtPending = $db->query("SELECT COUNT(*) AS cnt FROM bookings WHERE status_id = 2");
$rowPending = $stmtPending->fetch();
$pendingBookings = $rowPending['cnt'] ?? 0;

// 4. Stanze in Manutenzione
$stmtMaint = $db->query("SELECT COUNT(*) AS cnt FROM rooms WHERE status = 'maintenance'");
$rowMaint = $stmtMaint->fetch();
$maintenanceRooms = $rowMaint['cnt'] ?? 0;

// Popoliamo il blocco
$block->setContent('total_revenue', $totalRevenue);
$block->setContent('total_bookings', $totalBookings);
$block->setContent('pending_bookings', $pendingBookings);
$block->setContent('maintenance_rooms', $maintenanceRooms);

// Popoliamo le variabili comuni del frame privato
$page->setContent('base', $config['base']);
$page->setContent('skin', 'administration');
$page->setContent('user_name', htmlspecialchars($_SESSION['user']['name']));
$page->setContent('user_role', 'Amministratore');
$page->setContent('role_path', 'admin');
$page->setContent('is_admin_role', '1');

$page->setContent('body', $block->get());
$page->close();
