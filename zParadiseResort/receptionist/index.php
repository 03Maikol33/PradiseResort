<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

// Controlliamo che l'utente sia loggato e sia Receptionist
if (empty($_SESSION['user'])) {
    header("Location: {$config['base']}/login.php");
    exit;
}

if (!is_receptionist()) {
    if (is_admin()) {
        header("Location: {$config['base']}/admin/index.php");
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

// 4. Stanze in Manutenzione (Conteggio dal database)
$stmtMaint = $db->query("SELECT COUNT(*) AS cnt FROM rooms WHERE status = 'maintenance'");
$rowMaint = $stmtMaint->fetch();
$maintenanceRooms = $rowMaint['cnt'] ?? 0;

// --- STATISTICHE DI OGGI (OPERATIVITÀ QUOTIDIANA) ---
$todayStr = date('Y-m-d');

// A. Arrivi di Oggi
$stmtArr = $db->prepare("SELECT COUNT(*) AS cnt FROM bookings WHERE check_in_date = ? AND status_id IN (2, 3)");
$stmtArr->execute([$todayStr]);
$todayArrivals = $stmtArr->fetch()['cnt'] ?? 0;

// B. Partenze di Oggi
$stmtDep = $db->prepare("SELECT COUNT(*) AS cnt FROM bookings WHERE check_out_date = ? AND status_id IN (3, 5)");
$stmtDep->execute([$todayStr]);
$todayDepartures = $stmtDep->fetch()['cnt'] ?? 0;

// C. Ospiti in Casa (Stanze occupate oggi)
$stmtInHouse = $db->prepare("SELECT COUNT(DISTINCT room_id) AS cnt FROM bookings WHERE check_in_date <= ? AND check_out_date > ? AND status_id = 3");
$stmtInHouse->execute([$todayStr, $todayStr]);
$inHouseGuests = $stmtInHouse->fetch()['cnt'] ?? 0;

// --- STATO CAMERE ---
// Total Camere
$stmtTotRooms = $db->query("SELECT COUNT(*) AS cnt FROM rooms");
$roomsTotal = $stmtTotRooms->fetch()['cnt'] ?? 0;

// Camere in Manutenzione
$roomsMaintenance = $maintenanceRooms;

// Camere Occupate
$roomsOccupied = $inHouseGuests;

// Camere Libere
$roomsAvailable = max(0, $roomsTotal - $roomsOccupied - $roomsMaintenance);

// Calcolo Percentuali
$pctAvailable = $roomsTotal > 0 ? round(($roomsAvailable / $roomsTotal) * 100) : 0;
$pctOccupied = $roomsTotal > 0 ? round(($roomsOccupied / $roomsTotal) * 100) : 0;
$pctMaintenance = $roomsTotal > 0 ? round(($roomsMaintenance / $roomsTotal) * 100) : 0;

// Popoliamo il blocco principale
$block->setContent('total_revenue', $totalRevenue);
$block->setContent('total_bookings', $totalBookings);
$block->setContent('pending_bookings', $pendingBookings);
$block->setContent('maintenance_rooms', $maintenanceRooms);

// Popoliamo le statistiche operative e le camere
$block->setContent('today_arrivals', $todayArrivals);
$block->setContent('today_departures', $todayDepartures);
$block->setContent('in_house_guests', $inHouseGuests);

$block->setContent('rooms_total', $roomsTotal);
$block->setContent('rooms_available', $roomsAvailable);
$block->setContent('rooms_occupied', $roomsOccupied);
$block->setContent('rooms_maintenance', $roomsMaintenance);

$block->setContent('pct_available', $pctAvailable);
$block->setContent('pct_occupied', $pctOccupied);
$block->setContent('pct_maintenance', $pctMaintenance);

// Popoliamo le variabili comuni del frame privato e le notifiche
setup_backoffice_page($page, 'Receptionist', 'receptionist');

$page->setContent('body', $block->get());
$page->close();
