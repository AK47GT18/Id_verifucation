<?php

session_start();
require_once '../includes/db_connection.php';
require_once '../includes/record_manager.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$recordManager = new RecordManager($conn);

try {
    // Fetch statistics
    $stats = [
        'total_users' => $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0],
        'total_records' => $conn->query("SELECT COUNT(*) FROM records")->fetch_row()[0],
        'verified_records' => $conn->query("SELECT COUNT(*) FROM records WHERE status = 'Verified'")->fetch_row()[0]
    ];

    // Fetch recent activity
    $query = "
        SELECT 
            'verified' as type,
            r.form_number as record_id,
            v.verified_at as time,
            u.username as user
        FROM verifications v
        JOIN records r ON v.record_id = r.id
        JOIN users u ON v.verified_by = u.id
        UNION ALL
        SELECT 
            'user_added' as type,
            username as record_id,
            created_at as time,
            'admin' as user
        FROM users
        WHERE created_at >= NOW() - INTERVAL 7 DAY
        ORDER BY time DESC
        LIMIT 10
    ";

    $result = $conn->query($query);
    $recentActivity = [];
    
    while ($row = $result->fetch_assoc()) {
        $timeAgo = strtotime($row['time']);
        $recentActivity[] = [
            'type' => $row['type'],
            'record_id' => $row['record_id'],
            'time' => human_time_diff($timeAgo),
            'icon' => $row['type'] === 'verified' ? 'check_circle' : 'person_add',
            'icon_color' => $row['type'] === 'verified' ? 'text-green-600' : 'text-blue-600',
            'user' => $row['user']
        ];
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'recentActivity' => $recentActivity
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching dashboard data'
    ]);
}

function human_time_diff($time) {
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } else {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
}