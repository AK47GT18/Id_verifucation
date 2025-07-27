<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['username'])) {
    header("Location: /id_verification/login.php");
    exit;
}

$username = $_SESSION['username'];
$userId = $_SESSION['user_id'];

// Fetch real statistics
$statsQuery = "
    SELECT 
        (SELECT COUNT(*) FROM records) as total_records,
        (SELECT COUNT(*) FROM records WHERE status = 'Verified') as verified,
        (SELECT COUNT(*) FROM records WHERE status = 'Unverified') as unverified
    FROM dual";
$stats = $conn->query($statsQuery)->fetch_assoc();

// Fetch recent activity
$activityQuery = "
    SELECT 
        CASE 
            WHEN v.id IS NOT NULL THEN 'verified'
            WHEN r.status = 'Unverified' THEN 'pending'
            ELSE 'failed'
        END as type,
        r.form_number as record_id,
        r.first_name,
        r.last_name,
        COALESCE(v.verified_at, r.created_at) as time,
        r.id as record_id_int
    FROM records r
    LEFT JOIN verifications v ON r.id = v.record_id
    WHERE v.verified_by = ? OR r.status = 'Unverified'
    ORDER BY time DESC
    LIMIT 10";

$stmt = $conn->prepare($activityQuery);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$recentActivity = [];

while ($row = $result->fetch_assoc()) {
    $recentActivity[] = [
        'type' => $row['type'],
        'record_id' => $row['record_id'],
        'name' => $row['first_name'] . ' ' . $row['last_name'],
        'time' => $row['time'],
        'icon' => match($row['type']) {
            'verified' => 'check_circle',
            'pending' => 'pending',
            'failed' => 'error_outline'
        },
        'icon_color' => match($row['type']) {
            'verified' => 'text-green-600',
            'pending' => 'text-orange-600',
            'failed' => 'text-red-600'
        },
        'record_id_int' => $row['record_id_int']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifier Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        @keyframes slideIn {
            from { transform: translateX(-100%); }
            to { transform: translateX(0); }
        }
        .animate-slide-in {
            animation: slideIn 0.3s ease-out;
        }
        .card {
            transition: transform 0.2s, border-color 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
            border-color: #16A34A; /* Green-600 */
        }
        .btn-primary {
            background-color: #16A34A; /* Green-600 */
            color: white;
        }
        .btn-primary:hover {
            background-color: #15803D; /* Green-700 */
        }
        .table-row:hover {
            background-color: #F9FAFB; /* Gray-50 */
        }
        .notification {
            transition: opacity 0.5s ease-out;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg fixed h-full transition-transform duration-300 ease-in-out transform -translate-x-full md:translate-x-0 z-40">
            <div class="h-full flex flex-col">
                <!-- Logo Section -->
                <div class="flex items-center gap-3 p-6 border-b border-gray-100">
                    <div class="bg-green-50 p-2 rounded-lg">
                        <span class="material-icons text-2xl text-green-600">verified_user</span>
                    </div>
                    <div>
                        <div class="font-bold text-gray-900">ID Verifier</div>
                        <div class="text-sm text-gray-500">Management System</div>
                    </div>
                </div>
                
                <!-- Navigation -->
                <nav class="flex-1 p-4 space-y-2">
                    <a href="index.php" class="flex items-center gap-3 p-3 rounded-lg bg-green-50 text-green-600 font-medium">
                        <span class="material-icons text-lg">dashboard</span>
                        Dashboard
                    </a>
                    <a href="scan.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors">
                        <span class="material-icons text-lg">qr_code_scanner</span>
                        Scan & Verify
                    </a>
                    <a href="records.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors">
                        <span class="material-icons text-lg">folder</span>
                        My Verifications
                    </a>
                </nav>
                
                <!-- User Section -->
                <div class="border-t border-gray-100 p-4">
                    <div class="flex items-center gap-3 p-2">
                        <div class="bg-gray-100 p-2 rounded-full">
                            <span class="material-icons text-gray-600">person</span>
                        </div>
                        <div>
                            <div class="font-medium text-sm"><?php echo htmlspecialchars($username); ?></div>
                            <a href="/auth/logout.php" class="text-xs text-red-500 hover:text-red-600">Sign out</a>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 md:ml-64 flex flex-col min-h-screen">
            <header class="bg-white shadow-sm border-b sticky top-0 z-30">
                <div class="flex items-center justify-between p-4">
                    <div class="flex items-center gap-4">
                        <button id="menuBtn" class="md:hidden p-2 rounded-lg hover:bg-gray-100">
                            <span class="material-icons text-gray-600 text-2xl">menu</span>
                        </button>
                        <h1 class="text-xl font-semibold text-gray-900">Dashboard</h1>
                    </div>
                    <div class="flex items-center gap-3">
                        <button id="advancedFiltersBtn" class="flex items-center gap-2 px-4 py-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors">
                            <span class="material-icons">tune</span>
                            Filters
                        </button>
                        <button class="btn-primary px-4 py-2 rounded-lg flex items-center gap-2 transition-colors" onclick="quickScan()">
                            <span class="material-icons text-sm">qr_code_scanner</span>
                            Quick Scan
                        </button>
                    </div>
                </div>
                <!-- Search & Filters -->
                <div class="px-4 pb-4">
                    <div class="flex flex-col md:flex-row md:items-center gap-4">
                        <div class="flex-1 w-full">
                            <div class="relative">
                                <input id="searchActivity" type="text" placeholder="Search by record ID..." class="w-full pl-12 pr-4 py-3 text-base rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <span class="material-icons absolute left-4 top-3.5 text-gray-400">search</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <select id="activityFilter" class="px-4 py-3 rounded-xl border border-gray-300 text-base focus:ring-2 focus:ring-green-500">
                                <option value="">All Activities</option>
                                <option value="verified">Verified</option>
                                <option value="scanned">Scanned</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-6 bg-gray-50">
                <div class="max-w-7xl mx-auto">
                    <!-- Notification -->
                    <div id="notification" class="hidden fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg">
                        <div class="flex items-center gap-2">
                            <span id="notificationIcon" class="material-icons"></span>
                            <span id="notificationMessage"></span>
                        </div>
                    </div>

                    <!-- Stats Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Total Records</p>
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $stats['total_records']; ?></h3>
                                </div>
                                <div class="bg-green-50 p-3 rounded-xl">
                                    <span class="material-icons text-2xl text-green-600">folder</span>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center text-sm text-gray-500">
                                <span class="material-icons text-base mr-1">history</span>
                                Updated: Today
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Verified</p>
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $stats['verified']; ?></h3>
                                </div>
                                <div class="bg-green-50 p-3 rounded-xl">
                                    <span class="material-icons text-2xl text-green-600">verified</span>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center text-sm text-green-600">
                                <span class="material-icons text-base mr-1">trending_up</span>
                                <?php echo round(($stats['verified']/$stats['total_records']) * 100); ?>% verified
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Pending</p>
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $stats['unverified']; ?></h3>
                                </div>
                                <div class="bg-orange-50 p-3 rounded-xl">
                                    <span class="material-icons text-2xl text-orange-600">pending_actions</span>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center text-sm text-orange-600">
                                <span class="material-icons text-base mr-1">warning</span>
                                Awaiting verification
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Filters Panel -->
                    <div id="advancedFilters" class="hidden bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
                        <div class="p-6 border-b border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                <span class="material-icons text-green-600">tune</span>
                                Advanced Filters
                            </h2>
                        </div>
                        <div class="p-6 bg-gray-50 rounded-b-xl">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                                    <div class="grid grid-cols-2 gap-4">
                                        <input type="date" id="dateFrom" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500">
                                        <input type="date" id="dateTo" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500">
                                    </div>
                                </div>
                                <div class="flex items-end">
                                    <div class="flex justify-end gap-3 w-full">
                                        <button onclick="clearFilters()" class="px-4 py-2 text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-100">
                                            Clear All
                                        </button>
                                        <button onclick="applyFilters()" class="px-6 py-2 btn-primary rounded-lg">
                                            Apply Filters
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-100">
                            <div class="flex items-center justify-between">
                                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <span class="material-icons text-green-600">history</span>
                                    Recent Activity
                                </h2>
                                <span id="activityCount" class="text-sm text-gray-600">
                                    Showing <?php echo count($recentActivity); ?> of <?php echo count($recentActivity); ?> activities
                                </span>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-200">
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Activity</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Record ID</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Time</th>
                                        <th class="text-right py-4 px-6 text-sm font-semibold text-gray-900">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach($recentActivity as $activity): ?>
                                    <tr class="table-row transition-colors">
                                        <td class="py-4 px-6">
                                            <div class="flex items-center gap-2">
                                                <span class="material-icons <?php echo $activity['icon_color']; ?>"><?php echo $activity['icon']; ?></span>
                                                <span class="text-sm font-medium text-gray-900">
                                                    <?php 
                                                    echo match($activity['type']) {
                                                        'verified' => 'Verified record',
                                                        'scanned' => 'Scanned record',
                                                        'failed' => 'Failed verification for',
                                                        default => 'Updated record'
                                                    };
                                                    ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="text-sm text-gray-600"><?php echo htmlspecialchars($activity['record_id']); ?></span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="text-sm text-gray-600"><?php echo date('M d, Y H:i', strtotime($activity['time'])); ?></span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <div class="flex items-center justify-end gap-2">
                                                <button onclick="viewRecord('<?php echo htmlspecialchars($activity['record_id']); ?>')"
                                                        class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
                                                        title="View Details">
                                                    <span class="material-icons">visibility</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>

    <!-- Record Details Modal -->
    <div id="recordModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Record Details</h3>
                        <button onclick="closeModal('recordModal')" class="p-2 hover:bg-gray-100 rounded-lg">
                            <span class="material-icons">close</span>
                        </button>
                    </div>
                </div>
                <div class="p-6" id="recordDetails">
                    Loading...
                </div>
                <div class="p-6 border-t border-gray-100 bg-gray-50">
                    <div class="flex justify-end gap-3">
                        <button onclick="closeModal('recordModal')" 
                                class="px-4 py-2 text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-100">
                            Close
                        </button>
                        <button onclick="verifyRecord()" 
                                class="px-6 py-2 btn-primary rounded-lg">
                            Verify Record
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Verification Modal -->
    <div id="verifyModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">Verify Record</h3>
                </div>
                <div class="p-6">
                    <form id="verifyForm" onsubmit="submitVerification(event)">
                        <input type="hidden" id="verifyRecordId">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Verification Comment
                            </label>
                            <textarea id="verifyComment" rows="3" 
                                    class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500"
                                    placeholder="Add any verification notes..."></textarea>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" onclick="closeModal('verifyModal')" 
                                    class="px-4 py-2 text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-100">
                                Cancel
                            </button>
                            <button type="submit" class="px-6 py-2 btn-primary rounded-lg">
                                Confirm Verification
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentRecordId = null;

        async function viewRecord(recordId) {
            currentRecordId = recordId;
            const modal = document.getElementById('recordModal');
            const detailsContainer = document.getElementById('recordDetails');
            
            modal.classList.remove('hidden');
            detailsContainer.innerHTML = 'Loading...';

            try {
                const response = await fetch(`get_record_details.php?id=${recordId}`);
                const data = await response.json();
                
                if (data.success) {
                    detailsContainer.innerHTML = `
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Form Number</p>
                                <p class="mt-1">${data.record.form_number}</p>
                           