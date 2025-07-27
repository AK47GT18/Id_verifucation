<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['username'])) {
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$data = json_decode(file_get_contents('php://input'), true);
$identifier = $data['identifier'] ?? null;
$comment = $data['comment'] ?? '';
$userId = $_SESSION['user_id'];

if (!$identifier) {
    exit(json_encode(['success' => false, 'message' => 'Record identifier required']));
}

try {
    $conn->begin_transaction();

    // Find record by ID or form number
    $stmt = $conn->prepare("
        SELECT id FROM records 
        WHERE (id = ? OR form_number = ?) 
        AND status != 'Verified'
    ");
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->fetch_assoc();

    if (!$record) {
        throw new Exception('Record not found or already verified');
    }

    // Update record status
    $stmt = $conn->prepare("UPDATE records SET status = 'Verified' WHERE id = ?");
    $stmt->bind_param('i', $record['id']);
    $stmt->execute();

    // Add verification record
    $stmt = $conn->prepare("
        INSERT INTO verifications (record_id, verified_by, comment)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param('iis', $record['id'], $userId, $comment);
    $stmt->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Record verified successfully']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}