<?php
require 'include/bootstrap.inc.php';
$stmt = $db->query('SHOW TABLES');
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);
foreach($tables as $table) {
    echo "TABLE: $table\n";
    $stmt2 = $db->query("DESCRIBE $table");
    print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
}
