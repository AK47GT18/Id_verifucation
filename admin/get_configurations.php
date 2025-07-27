<?php

session_start();
require_once '../includes/db_connection.php';
require_once '../includes/config_manager.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$configManager = new ConfigManager($conn);
$configs = $configManager->getConfigurations();

echo json_encode([
    'success' => true,
    'configs' => $configs
]);