<?php
require_once __DIR__ . '/include/bootstrap.inc.php';

// Helper per convertire i vari formati data (HTML5 o Gijgo Datepicker) in YYYY-MM-DD
function parse_date_custom(string $dateStr): ?string {
    $dateStr = trim($dateStr);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        return $dateStr;
    }
    // Gijgo / formato con slash: MM/DD/YYYY o DD/MM/YYYY
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateStr, $matches)) {
        $val1 = (int)$matches[1];
        $val2 = (int)$matches[2];
        $year = (int)$matches[3];
        // Determina se il formato è DD/MM/YYYY o MM/DD/YYYY
        if ($val1 > 12) {
            $day = $val1;
            $month = $val2;
        } elseif ($val2 > 12) {
            $day = $val2;
            $month = $val1;
        } else {
            // Default di Gijgo è solitamente MM/DD/YYYY
            $day = $val2;
            $month = $val1;
        }
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
    return null;
}

$action = $_GET['action'] ?? '';

// Gestione Azione: Aggiungi al Carrello
if ($action === 'add') {
    // Richiede login
    if (empty($_SESSION['user'])) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . $config['base'] . '/login.php');
        exit;
    }

    $roomId = (int)($_GET['room_id'] ?? 0);
    $checkInStr = $_GET['check_in'] ?? '';
    $checkOutStr = $_GET['check_out'] ?? '';

    $checkIn = parse_date_custom($checkInStr);
    $checkOut = parse_date_custom($checkOutStr);

    if ($roomId && $checkIn && $checkOut) {
        // Recuperiamo i dettagli della camera e della categoria
        $stmt = db()->prepare(
            'SELECT r.id AS room_id, r.room_number, c.id AS category_id, c.name AS category_name, c.base_price
             FROM rooms r
             JOIN room_categories c ON c.id = r.category_id
             WHERE r.id = ? AND r.status = \'available\''
        );
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();

        if ($room) {
            // Ricontrolliamo la disponibilità effettiva prima di mettere nel carrello
            $chk = db()->prepare(
                'SELECT 1 FROM bookings b
                 WHERE b.room_id = ? AND b.status_id <> 4
                   AND b.check_in_date < ? AND b.check_out_date > ?'
            );
            $chk->execute([$roomId, $checkOut, $checkIn]);
            $isBooked = (bool)$chk->fetch();

            if (!$isBooked) {
                // Calcola notti e prezzo totale
                $start = new DateTime($checkIn);
                $end = new DateTime($checkOut);
                $nights = $start->diff($end)->days;
                if ($nights <= 0) {
                    $nights = 1;
                }
                $totalPrice = $nights * $room['base_price'];

                // Salva nel carrello in sessione
                $_SESSION['cart'] = [
                    'room_id'       => $room['room_id'],
                    'room_number'   => $room['room_number'],
                    'category_id'   => $room['category_id'],
                    'category_name' => $room['category_name'],
                    'check_in'      => $checkIn,
                    'check_out'     => $checkOut,
                    'nights'        => $nights,
                    'base_price'    => $room['base_price'],
                    'total_price'   => $totalPrice
                ];

                header('Location: ' . $config['base'] . '/cart.php');
                exit;
            } else {
                $errorMsg = 'Spiacenti, la camera selezionata è stata appena prenotata da un altro utente per queste date.';
            }
        }
    }
}

// Parametri di Ricerca
$checkInRaw = $_GET['check_in'] ?? $_SESSION['search']['check_in'] ?? '';
$checkOutRaw = $_GET['check_out'] ?? $_SESSION['search']['check_out'] ?? '';
$selectedCatId = (int)($_GET['category_id'] ?? 0);

$checkIn = $checkInRaw ? parse_date_custom($checkInRaw) : null;
$checkOut = $checkOutRaw ? parse_date_custom($checkOutRaw) : null;

$error = '';
$hasResults = '';

// Se abbiamo inserito delle date, validiamole
if ($checkInRaw || $checkOutRaw) {
    if (!$checkIn || !$checkOut) {
        $error = 'Formato delle date non valido.';
    } else {
        $today = date('Y-m-d');
        if ($checkIn < $today) {
            $error = 'La data di check-in non può essere nel passato.';
        } elseif ($checkIn >= $checkOut) {
            $error = 'La data di check-out deve essere successiva alla data di check-in.';
        } else {
            // Date valide! Salviamole in sessione per comodità
            $_SESSION['search'] = [
                'check_in'  => $checkIn,
                'check_out' => $checkOut
            ];
            $hasResults = '1';
        }
    }
}

// Carica categorie di stanze
$catQuery = 'SELECT * FROM room_categories';
if ($selectedCatId > 0) {
    $catQuery .= ' WHERE id = :cat_id';
}
$catQuery .= ' ORDER BY base_price ASC';

$stmtCat = db()->prepare($catQuery);
if ($selectedCatId > 0) {
    $stmtCat->execute(['cat_id' => $selectedCatId]);
} else {
    $stmtCat->execute();
}
$categories = $stmtCat->fetchAll();

$skin = new_page($config['skin']);
$skin->setContent('title',      'Ricerca Camere');
$skin->setContent('year',       date('Y'));
$skin->setContent('base',       $config['base']);
$skin->setContent('skin',       $config['skin']);
$skin->setContent('is_logged',  !empty($_SESSION['user']) ? '1' : '');
$skin->setContent('user.name',  $_SESSION['user']['name'] ?? '');
$skin->setContent('cart_count', !empty($_SESSION['cart']) ? '1' : '');
$skin->setContent('cart_badge', !empty($_SESSION['cart']) ? ' (1)' : '');

$block = new_block('rooms');
$block->setContent('error', $error);
$block->setContent('check_in_val', htmlspecialchars($checkInRaw));
$block->setContent('check_out_val', htmlspecialchars($checkOutRaw));
$block->setContent('has_results', $hasResults);

// Se abbiamo risultati validi, verifichiamo la disponibilità reale per ciascuna categoria
$nights = 1;
if ($hasResults && $checkIn && $checkOut) {
    $start = new DateTime($checkIn);
    $end = new DateTime($checkOut);
    $nights = $start->diff($end)->days;
}
$block->setContent('nights', $nights);

foreach ($categories as $cat) {
    $block->setContent('category_id',          $cat['id']);
    $block->setContent('category_name',        htmlspecialchars($cat['name']));
    $block->setContent('category_description', htmlspecialchars($cat['description']));
    $block->setContent('category_capacity',    $cat['capacity']);
    $block->setContent('category_price',       number_format($cat['base_price'], 2, ',', '.'));
    
    $img = $cat['image_url'] ? $cat['image_url'] : 'room1.jpg';
    $block->setContent('category_image_path',  $config['base'] . '/skins/' . $config['skin'] . '/assets/img/rooms/' . htmlspecialchars($img));

    $isAvailable = false;
    $roomId = 0;
    $roomNum = '';

    if ($hasResults && $checkIn && $checkOut) {
        // Cerca una camera fisica libera di questa categoria
        $stmtRoom = db()->prepare(
            'SELECT r.id, r.room_number FROM rooms r
             WHERE r.category_id = ? AND r.status = \'available\'
               AND r.id NOT IN (
                   SELECT b.room_id FROM bookings b
                   WHERE b.status_id <> 4
                     AND b.check_in_date < ? AND b.check_out_date > ?
               )
             LIMIT 1'
        );
        $stmtRoom->execute([$cat['id'], $checkOut, $checkIn]);
        $availableRoom = $stmtRoom->fetch();

        if ($availableRoom) {
            $isAvailable = true;
            $roomId = $availableRoom['id'];
            $roomNum = $availableRoom['room_number'];
        }
    }

    $availabilityHtml = '';
    if ($isAvailable) {
        $totalPrice = $nights * $cat['base_price'];
        $availabilityHtml .= '<div class="text-right">';
        $availabilityHtml .= '  <span class="text-success font-weight-bold" style="display: block; margin-bottom: 8px; font-size: 1.05rem;">';
        $availabilityHtml .= '      <i class="fas fa-check-circle mr-1"></i> Disponibile!';
        $availabilityHtml .= '  </span>';
        $availabilityHtml .= '  <span style="font-size: 1rem; color: #4a5568; display: block; margin-bottom: 8px;">';
        $availabilityHtml .= '      Totale: <strong>&euro;' . number_format($totalPrice, 2, ',', '.') . '</strong>';
        $availabilityHtml .= '  </span>';

        if (!empty($_SESSION['user'])) {
            $addUrl = $config['base'] . '/rooms.php?action=add&room_id=' . $roomId . '&check_in=' . urlencode($checkInRaw) . '&check_out=' . urlencode($checkOutRaw);
            $availabilityHtml .= '  <a href="' . $addUrl . '" class="btn btn-sm" style="background-color: #d87040; color: white; padding: 12px 20px; font-weight: bold; border-radius: 4px; font-size: 0.9rem;">Prenota & Blocca</a>';
        } else {
            $loginUrl = $config['base'] . '/login.php';
            $availabilityHtml .= '  <a href="' . $loginUrl . '" class="btn btn-sm btn-secondary" style="padding: 12px 20px; font-weight: bold; border-radius: 4px; font-size: 0.9rem;">Accedi per prenotare</a>';
        }
        $availabilityHtml .= '</div>';
    } else {
        $availabilityHtml .= '<div class="text-right">';
        $availabilityHtml .= '  <span class="text-danger font-weight-bold" style="display: block; margin-bottom: 5px; font-size: 1.05rem;">';
        $availabilityHtml .= '      <i class="fas fa-times-circle mr-1"></i> Esaurito';
        $availabilityHtml .= '  </span>';
        $availabilityHtml .= '  <span class="text-muted" style="font-size: 0.85rem; display: block;">Non ci sono camere libere in questo periodo</span>';
        $availabilityHtml .= '</div>';
    }
    
    $block->setContent('category_availability_html', $availabilityHtml);
}


$skin->setContent('body', $block->get());
$skin->close();
