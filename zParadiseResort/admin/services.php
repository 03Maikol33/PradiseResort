<?php
require_once __DIR__ . '/../include/bootstrap.inc.php';

require_admin();

$page = new_page('administration', 'frame-private');
$block = new_block('services');

$db = db();

// Check for success or error messages
$success_msg = isset($_GET['success']) ? trim($_GET['success']) : '';
$error_msg = isset($_GET['error']) ? trim($_GET['error']) : '';

$block->setContent('success_msg', htmlspecialchars($success_msg));
$block->setContent('error_msg', htmlspecialchars($error_msg));
$block->setContent('show_success', $success_msg ? '1' : '');
$block->setContent('show_error', $error_msg ? '1' : '');

// Preleviamo tutti i servizi per popolare la select nel modal "Assegna"
$stmtAllServices = $db->query("SELECT id, script_name, description FROM services ORDER BY script_name ASC");
$allServices = $stmtAllServices->fetchAll();
foreach ($allServices as $srv) {
    $block->setContent('all_service_id', $srv['id']);
    $block->setContent('all_service_name', htmlspecialchars($srv['script_name']));
    $block->setContent('all_service_description', htmlspecialchars($srv['description'] ?? ''));
}

// Preleviamo tutti i gruppi
$queryGroups = "SELECT id, name, description FROM gruppi ORDER BY id ASC";
$stmtGroups = $db->query($queryGroups);
$groups = $stmtGroups->fetchAll();

if (count($groups) > 0) {
    $block->setContent('groups_list', '1');
    foreach ($groups as $group) {
        $block->setContent('group_id', $group['id']);
        $block->setContent('group_name', htmlspecialchars($group['name']));
        $block->setContent('group_description', htmlspecialchars($group['description'] ?? ''));
    }
} else {
    $block->setContent('groups_list', ''); 
}

// Generiamo HTML per le tabelle raggruppate per gruppo
$groupsTablesHtml = '';

// Pre-carichiamo i servizi associati per ogni gruppo
$queryAssigned = 'SELECT gs.group_id, s.id as service_id, s.script_name, s.description 
                  FROM group_services gs 
                  JOIN services s ON gs.service_id = s.id 
                  ORDER BY s.script_name ASC';
$stmtAssigned = $db->query($queryAssigned);
$assignedServices = $stmtAssigned->fetchAll();

// Raggruppiamo i risultati per group_id in un array associativo
$servicesByGroup = [];
foreach ($assignedServices as $row) {
    $servicesByGroup[$row['group_id']][] = $row;
}

// Creiamo un blocco per ogni gruppo
foreach ($groups as $group) {
    $groupBlock = new_block('services_group_table');
    $groupBlock->setContent('group_id', $group['id']);
    $groupBlock->setContent('group_name', htmlspecialchars($group['name']));
    $groupBlock->setContent('group_name_js', addslashes(htmlspecialchars($group['name'])));
    
    $groupServices = $servicesByGroup[$group['id']] ?? [];
    
    if (count($groupServices) > 0) {
        $groupBlock->setContent('services_list', '1');
        foreach ($groupServices as $service) {
            $groupBlock->setContent('service_id', $service['service_id']);
            $groupBlock->setContent('service_name', htmlspecialchars($service['script_name']));
            $groupBlock->setContent('service_name_js', addslashes(htmlspecialchars($service['script_name'])));
            $groupBlock->setContent('service_description', htmlspecialchars($service['description'] ?? ''));
            $groupBlock->setContent('service_group_id', $group['id']);
            $groupBlock->setContent('service_group_name_js', addslashes(htmlspecialchars($group['name'])));
        }
    } else {
        $groupBlock->setContent('services_list', ''); 
    }
    
    $groupsTablesHtml .= $groupBlock->get();
}

$block->setContent('groups_tables_html', $groupsTablesHtml);

setup_backoffice_page($page, 'Amministratore', 'admin');
$page->setContent('body', $block->get());
$page->close();
