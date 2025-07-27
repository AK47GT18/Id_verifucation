<?php

session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$type = $_GET['type'] ?? 'full';
$dateFrom = $_GET['dateFrom'] ?? '';
$dateTo = $_GET['dateTo'] ?? '';
$user = $_GET['user'] ?? '';

// Build query based on report type
switch ($type) {
    case 'verified':
        $query = "
            SELECT 
                r.form_number,
                r.national_id,
                CONCAT(r.first_name, ' ', r.last_name) as beneficiary_name,
                r.gender,
                r.village,
                r.TA,
                r.SCTP_UBR_NUMBER,
                u.username as verified_by,
                v.verified_at,
                v.comment
            FROM records r
            JOIN verifications v ON r.id = v.record_id
            JOIN users u ON v.verified_by = u.id
            WHERE r.status = 'Verified'
        ";
        break;

    case 'verifiers':
        $query = "
            SELECT 
                u.username,
                CONCAT(u.first_name, ' ', u.last_name) as full_name,
                COUNT(v.id) as records_verified,
                MIN(v.verified_at) as first_verification,
                MAX(v.verified_at) as last_verification
            FROM users u
            LEFT JOIN verifications v ON u.id = v.verified_by
            WHERE u.role = 'Verifier'
            GROUP BY u.id
        ";
        break;

    default:
        $query = "
            SELECT 
                r.form_number,
                r.national_id,
                CONCAT(r.first_name, ' ', r.last_name) as beneficiary_name,
                r.gender,
                r.village,
                r.TA,
                r.SCTP_UBR_NUMBER,
                r.status,
                IFNULL(u.username, '-') as verified_by,
                IFNULL(v.verified_at, '-') as verified_at
            FROM records r
            LEFT JOIN verifications v ON r.id = v.record_id
            LEFT JOIN users u ON v.verified_by = u.id
        ";
}

// Add date filters if provided
if ($dateFrom) {
    $query .= " AND v.verified_at >= '$dateFrom 00:00:00'";
}
if ($dateTo) {
    $query .= " AND v.verified_at <= '$dateTo 23:59:59'";
}
if ($user) {
    $query .= " AND u.username = '$user'";
}

$query .= " ORDER BY " . ($type === 'verifiers' ? 'records_verified DESC' : 'r.created_at DESC');

try {
    $result = $conn->query($query);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . $type . '_' . date('Y-m-d') . '.csv"');
    
    // Create CSV file
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, array_keys($result->fetch_assoc()));
    
    // Reset pointer
    $result->data_seek(0);
    
    // Add data
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    
    fclose($output);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error generating report: ' . $e->getMessage()
    ]);
}

