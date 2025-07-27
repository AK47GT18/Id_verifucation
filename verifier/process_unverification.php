<?php
session_start();
require_once '../includes/db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$record_id = $data['record_id'] ?? null;
$national_id = $data['national_id'] ?? null;
$verified_by = $data['verified_by'] ?? $_SESSION['user_id'];

if (!$record_id || !$national_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Verify record exists and is verified
    $stmt = $conn->prepare("SELECT id, status FROM records WHERE id = ? AND national_id = ?");
    $stmt->bind_param('is', $record_id, $national_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$record = $result->fetch_assoc()) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Record not found or invalid national ID']);
        exit;
    }

    if ($record['status'] !== 'Verified') {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Record is not verified']);
        exit;
    }

    // Update record status
    $stmt = $conn->prepare("UPDATE records SET status = 'Unverified', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param('i', $record_id);
    $stmt->execute();

    // Remove verification record
    $stmt = $conn->prepare("DELETE FROM verifications WHERE record_id = ? AND verified_by = ?");
    $stmt->bind_param('ii', $record_id, $verified_by);
    $stmt->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Record unverified successfully']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} finally {
    $stmt->close();
    $conn->close();
}
?>