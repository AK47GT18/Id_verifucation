<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['username'])) {
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$recordId = $_GET['id'] ?? null;

if (!$recordId) {
    exit(json_encode(['success' => false, 'message' => 'Record ID required']));
}

$query = "
    SELECT 
        r.*,
        v.verified_at,
        v.comment,
        u.username as verified_by,
        CASE 
            WHEN v.id IS NOT NULL THEN 'QR Scan'
            ELSE 'Manual'
        END as verification_method
    FROM records r
    LEFT JOIN verifications v ON r.id = v.record_id
    LEFT JOIN users u ON v.verified_by = u.id
    WHERE r.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $recordId);
$stmt->execute();
$result = $stmt->get_result();
$record = $result->fetch_assoc();

if ($record) {
    echo json_encode(['success' => true, 'record' => $record]);
} else {
    echo json_encode(['success' => false, 'message' => 'Record not found']);
}