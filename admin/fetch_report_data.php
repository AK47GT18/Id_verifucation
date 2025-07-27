<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

try {
    // Get filter parameters
    $dateFrom = $_GET['dateFrom'] ?? null;
    $dateTo = $_GET['dateTo'] ?? null;
    $user = $_GET['user'] ?? null;
    $status = $_GET['status'] ?? null;

    // Base statistics query
    $statsQuery = "
        SELECT 
            (SELECT COUNT(*) FROM users WHERE disabled = 0) as total_users,
            (SELECT COUNT(*) FROM records) as total_records,
            (SELECT COUNT(*) FROM records WHERE status = 'Verified') as verified_records,
            (SELECT COUNT(DISTINCT verified_by) FROM verifications) as active_verifiers
    ";
    
    $stats = $conn->query($statsQuery)->fetch_assoc();

    // User verifications query with filters
    $verificationQuery = "
        SELECT 
            u.username,
            u.first_name,
            u.last_name,
            COUNT(v.id) as verified_count,
            MAX(v.verified_at) as last_verification
        FROM users u
        LEFT JOIN verifications v ON u.id = v.verified_by
        WHERE u.role = 'Verifier' AND u.disabled = 0
    ";

    $params = [];
    $types = '';

    if ($dateFrom) {
        $verificationQuery .= " AND v.verified_at >= ?";
        $params[] = $dateFrom . ' 00:00:00';
        $types .= 's';
    }

    if ($dateTo) {
        $verificationQuery .= " AND v.verified_at <= ?";
        $params[] = $dateTo . ' 23:59:59';
        $types .= 's';
    }

    if ($user) {
        $verificationQuery .= " AND u.username = ?";
        $params[] = $user;
        $types .= 's';
    }

    $verificationQuery .= " GROUP BY u.id ORDER BY verified_count DESC";

    $stmt = $conn->prepare($verificationQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $userVerifications = [];
    while ($row = $result->fetch_assoc()) {
        $userVerifications[] = [
            'username' => $row['username'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'verified_count' => (int)$row['verified_count'],
            'last_verification' => $row['last_verification']
        ];
    }

    // Get verification trends
    $trendsQuery = "
        SELECT 
            DATE(verified_at) as date,
            COUNT(*) as count
        FROM verifications
        WHERE verified_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(verified_at)
        ORDER BY date
    ";
    
    $trends = $conn->query($trendsQuery)->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'userVerifications' => $userVerifications,
        'trends' => $trends
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching report data'
    ]);
}