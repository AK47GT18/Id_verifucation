<?php
session_start();
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;
$national_id = $data['national_id'] ?? null;

if (!$id && !$national_id) {
    echo json_encode(['success' => false, 'message' => 'No identifier provided']);
    exit;
}

try {
    $query = "SELECT id, first_name, last_name, national_id, form_number, status 
              FROM records 
              WHERE " . ($id ? "id = ?" : "national_id = ?");
    $stmt = $conn->prepare($query);
    $identifier = $id ?? $national_id;
    $stmt->bind_param('s', $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($record = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'record' => $record]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} finally {
    $stmt->close();
    $conn->close();
}
?>