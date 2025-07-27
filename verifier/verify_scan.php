<?php
// filepath: /opt/lampp/htdocs/id_verification/verifier/verify_scan.php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['username'])) {
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$data = json_decode(file_get_contents('php://input'), true);
$scannedValue = $data['scanned_value'] ?? '';

// Try to match with either national ID or form number
$query = "
    SELECT * FROM records 
    WHERE (national_id = ? OR form_number = ?) 
    AND status != 'Verified'
    LIMIT 1";

$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $scannedValue, $scannedValue);
$stmt->execute();
$result = $stmt->get_result();
$record = $result->fetch_assoc();

if ($record) {
    echo json_encode([
        'success' => true,
        'record' => $record
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No matching unverified record found'
    ]);
}