<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';
$stmt = db()->query("
    SELECT u.id, u.first_name, u.last_name, 
           (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id AND b.status_id IN (2, 3)) AS active_bookings 
    FROM users u 
    JOIN user_gruppi ug ON u.id = ug.user_id 
    JOIN gruppi g ON ug.group_id = g.id 
    WHERE g.name = 'Guest'
");
var_dump($stmt->fetchAll());
