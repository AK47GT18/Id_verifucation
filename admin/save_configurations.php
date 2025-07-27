<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/config_manager.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$configManager = new ConfigManager($conn);
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid request data']));
}

// Validate password change if included
if (isset($data['password'])) {
    if (!password_verify($data['password']['current_password'], $_SESSION['user_hash'])) {
        exit(json_encode(['success' => false, 'message' => 'Current password is incorrect']));
    }
    
    if (strlen($data['password']['new_password']) < 8) {
        exit(json_encode(['success' => false, 'message' => 'New password must be at least 8 characters']));
    }

    // Update password in users table
    $hash = password_hash($data['password']['new_password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->bind_param("ss", $hash, $_SESSION['username']);
    $stmt->execute();
    $stmt->close();
    
    unset($data['password']);
}

$result = $configManager->saveConfigurations($data, $_SESSION['username']);
echo json_encode($result);