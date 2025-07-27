<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: /id_verification/login.php");
    exit;
}

$username = $_SESSION['username'];
$userId = $_SESSION['user_id'];

// Fetch verified records for the current user
$recordsQuery = "
    SELECT 
        r.id,
        r.form_number,
        r.national_id,
        r.first_name,
        r.last_name,
        r.village,
        r.traditional_authority,
        v.verified_at,
        v.comment,
        v.verification_type
    FROM records r
    JOIN verifications v ON r.id = v.record_id
    WHERE v.verified_by = ?
    ORDER BY v.verified_at DESC";

$stmt = $conn->prepare($recordsQuery);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$verifiedRecords = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get unique TAs and Villages for filters
$tas = array_unique(array_filter(array_column($verifiedRecords, 'traditional_authority')));
$villages = array_unique(array_filter(array_column($verifiedRecords, 'village')));
sort($tas);
sort($villages);

// Calculate statistics
$stats = [
    'total_verified' => count($verifiedRecords),
    'today_verified' => array_reduce($verifiedRecords, function($count, $record) {
        return $count + (date('Y-m-d', strtotime($record['verified_at'])) === date('Y-m-d') ? 1 : 0);
    }, 0),
    'total_manual' => array_reduce($verifiedRecords, function($count, $record) {
        return $count + ($record['verification_type'] === 'manual' ? 1 : 0);
    }, 0),
    'total_scan' => array_reduce($verifiedRecords, function($count, $record) {
        return $count + ($record['verification_type'] === 'scan' ? 1 : 0);
    }, 0)
];

// Fetch user verification counts
$userStatsQuery = "
    SELECT 
        u.username,
        COUNT(v.id) as verification_count
    FROM users u
    LEFT JOIN verifications v ON u.id = v.verified_by
    WHERE u.enabled = 1
    GROUP BY u.id, u.username
    ORDER BY verification_count DESC";

$userStatsResult = $conn->query($userStatsQuery);
$userStats = $userStatsResult ? $userStatsResult->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Verified Records</title>
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
            border-color: #16A34A;
        }
        .btn-primary {
            background-color: #16A34A;
            color: white;
        }
        .btn-primary:hover {
            background-color: #15803D;
        }
        .table-row:hover {
            background-color: #F9FAFB;
        }
        .notification {
            transition: opacity 0.5s ease-out;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen text-gray-900">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg fixed h-full transition-transform duration-300 ease-in-out transform -translate-x-full md:translate-x-0 z-40">
            <div class="h-full flex flex-col">
                <div class="flex items-center gap-3 p-6 border-b border-gray-100">
                    <div class="bg-green-50 p-2 rounded-lg">
                        <span class="material-icons text-2xl text-green-600">verified_user</span>
                    </div>
                    <div>
                        <div class="font-bold text-gray-900">ID Verifier</div>
                        <div class="text-sm text-gray-500">Management System</div>
                    </div>
                </div>
                <nav class="flex-1 p-4 space-y-2">
                    <a href="index.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors">
                        <span class="material-icons text-lg">dashboard</span>
                        Dashboard
                    </a>
                    <a href="scan.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors">
                        <span class="material-icons text-lg">qr_code_scanner</span>
                        Scan & Verify
                    </a>
                    <a href="records.php" class="flex items-center gap-3 p-3 rounded-lg bg-green-50 text-green-600 font-medium">
                        <span class="material-icons text-lg">folder</span>
                        Records
                    </a>
                </nav>
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
                        <h1 class="text-xl font-semibold text-gray-900">My Verified Records</h1>
                    </div>
                    <div class="flex items-center gap-3">
                        <button onclick="exportRecords()" class="btn-primary px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                            <span class="material-icons text-sm">download</span>
                            Export to Excel
                        </button>
                        <button onclick="toggleManualVerifyModal()" class="btn-primary px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                            <span class="material-icons text-sm">edit</span>
                            Manual Verify
                        </button>
                    </div>
                </div>
                <div class="px-4 pb-4">
                    <div class="flex flex-col md:flex-row md:items-center gap-4">
                        <div class="flex-1 w-full">
                            <div class="relative">
                                <input id="searchRecords" type="text" placeholder="Search by form number, name, or ID..." class="w-full pl-12 pr-4 py-3 text-base rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <span class="material-icons absolute left-4 top-3.5 text-gray-400">search</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <button id="advancedFiltersBtn" class="flex items-center gap-2 px-4 py-3 text-green-600 hover:bg-green-50 rounded-lg transition-colors">
                                <span class="material-icons">tune</span>
                                Filters
                            </button>
                        </div>
                    </div>
                    <div id="advancedFilters" class="hidden mt-4 bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                <span class="material-icons text-green-600">tune</span>
                                Advanced Filters
                            </h2>
                        </div>
                        <div class="p-6 bg-gray-50 rounded-b-xl">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                                    <div class="grid grid-cols-2 gap-4">
                                        <input type="date" id="dateFrom" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500">
                                        <input type="date" id="dateTo" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Traditional Authority</label>
                                    <select id="taFilter" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500">
                                        <option value="">All TAs</option>
                                        <?php foreach($tas as $ta): ?>
                                        <option value="<?php echo htmlspecialchars($ta); ?>"><?php echo htmlspecialchars($ta); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Village</label>
                                    <select id="villageFilter" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500">
                                        <option value="">All Villages</option>
                                        <?php foreach($villages as $village): ?>
                                        <option value="<?php echo htmlspecialchars($village); ?>"><?php echo htmlspecialchars($village); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="flex justify-end gap-3 mt-4">
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
            </header>

            <main class="flex-1 p-6 bg-gray-50">
                <div class="max-w-7xl mx-auto">
                    <div id="notification" class="hidden fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg">
                        <div class="flex items-center gap-2">
                            <span id="notificationIcon" class="material-icons"></span>
                            <span id="notificationMessage"></span>
                        </div>
                    </div>

                    <!-- Stats Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Total Verified</p>
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $stats['total_verified']; ?></h3>
                                </div>
                                <div class="bg-green-50 p-3 rounded-xl">
                                    <span class="material-icons text-2xl text-green-600">verified</span>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center text-sm text-gray-500">
                                <span class="material-icons text-base mr-1">history</span>
                                All time
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Verified Today</p>
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $stats['today_verified']; ?></h3>
                                </div>
                                <div class="bg-blue-50 p-3 rounded-xl">
                                    <span class="material-icons text-2xl text-blue-600">today</span>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center text-sm text-blue-600">
                                <span class="material-icons text-base mr-1">trending_up</span>
                                Today’s progress
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Manual Verifications</p>
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $stats['total_manual']; ?></h3>
                                </div>
                                <div class="bg-purple-50 p-3 rounded-xl">
                                    <span class="material-icons text-2xl text-purple-600">edit</span>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center text-sm text-purple-600">
                                <span class="material-icons text-base mr-1">check</span>
                                Manual entries
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Scan Verifications</p>
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $stats['total_scan']; ?></h3>
                                </div>
                                <div class="bg-orange-50 p-3 rounded-xl">
                                    <span class="material-icons text-2xl text-orange-600">qr_code_scanner</span>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center text-sm text-orange-600">
                                <span class="material-icons text-base mr-1">check</span>
                                Scanned entries
                            </div>
                        </div>
                    </div>

                    <!-- User Stats Table -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
                        <div class="p-6 border-b border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                <span class="material-icons text-green-600">people</span>
                                User Verification Counts
                            </h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-200">
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Username</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Verifications</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach($userStats as $user): ?>
                                    <tr class="table-row transition-colors">
                                        <td class="py-4 px-6">
                                            <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['username']); ?></span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="text-sm text-gray-600"><?php echo $user['verification_count']; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($userStats)): ?>
                                    <tr>
                                        <td colspan="2" class="py-4 px-6 text-center text-sm text-gray-500">
                                            No user verification data available.
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Records Table -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-100">
                            <div class="flex items-center justify-between">
                                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <span class="material-icons text-green-600">folder</span>
                                    My Verified Records
                                </h2>
                                <span id="recordCount" class="text-sm text-gray-600">
                                    Showing <?php echo count($verifiedRecords); ?> of <?php echo count($verifiedRecords); ?> records
                                </span>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-200">
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Form #</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Name</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">National ID</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Village</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">TA</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Verified At</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Method</th>
                                        <th class="text-right py-4 px-6 text-sm font-semibold text-gray-900">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach($verifiedRecords as $record): ?>
                                    <tr class="table-row transition-colors" data-verified-at="<?php echo htmlspecialchars($record['verified_at']); ?>">
                                        <td class="py-4 px-6">
                                            <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($record['form_number']); ?></span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="text-sm text-gray-600"><?php echo htmlspecialchars($record['national_id']); ?></span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="text-sm text-gray-600"><?php echo htmlspecialchars($record['village'] ?? '-'); ?></span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="text-sm text-gray-600"><?php echo htmlspecialchars($record['traditional_authority'] ?? '-'); ?></span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="text-sm text-gray-600"><?php echo date('M d, Y H:i', strtotime($record['verified_at'])); ?></span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="text-sm text-gray-600"><?php echo ucfirst($record['verification_type']); ?></span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <div class="flex items-center justify-end gap-2">
                                                <button onclick="unverifyRecord(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['national_id']); ?>')"
                                                        class="p-2 text-red-600 hover:text-red-800 rounded-lg hover:bg-red-50"
                                                        title="Unverify">
                                                    <span class="material-icons">cancel</span>
                                                </button>
                                                <button onclick="viewDetails(<?php echo $record['id']; ?>)"
                                                        class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
                                                        title="View Details">
                                                    <span class="material-icons">visibility</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($verifiedRecords)): ?>
                                    <tr>
                                        <td colspan="8" class="py-4 px-6 text-center text-sm text-gray-500">
                                            No verified records found for this user.
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Manual Verification Modal -->
    <div id="manualVerifyModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <span class="material-icons text-green-600">edit</span>
                Manual Verification
            </h2>
            <form id="manualVerifyForm">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">National ID</label>
                    <input type="text" name="national_id" placeholder="Enter national ID" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Comment (optional)</label>
                    <textarea name="comment" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500" rows="3"></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 btn-primary py-2 rounded-lg">Verify</button>
                    <button type="button" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 rounded-lg" onclick="toggleManualVerifyModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <span class="material-icons text-green-600">person</span>
                    Confirm Verification
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-4" id="confirmationDetails">
                    <!-- Details will be inserted here -->
                </div>
            </div>
            <div class="p-6 border-t border-gray-100 bg-gray-50 flex justify-end gap-3">
                <button onclick="closeConfirmationModal()" class="px-4 py-2 text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-100">
                    Cancel
                </button>
                <button onclick="confirmVerification()" class="px-6 py-2 btn-primary rounded-lg">
                    Verify Record
                </button>
            </div>
        </div>
    </div>

    <!-- Unverify Confirmation Modal -->
    <div id="unverifyModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <span class="material-icons text-red-600">cancel</span>
                    Confirm Unverification
                </h3>
            </div>
            <div class="p-6">
                <p class="text-sm text-gray-600 mb-4">Are you sure you want to unverify this record? This will remove the verification status and associated verification record.</p>
                <div class="space-y-4" id="unverifyDetails">
                    <!-- Details will be inserted here -->
                </div>
            </div>
            <div class="p-6 border-t border-gray-100 bg-gray-50 flex justify-end gap-3">
                <button onclick="closeUnverifyModal()" class="px-4 py-2 text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-100">
                    Cancel
                </button>
                <button onclick="confirmUnverification()" class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">
                    Unverify Record
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentRecord = null;

        // Mobile menu toggle
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.querySelector('aside');
        const overlay = document.getElementById('overlay');

        function toggleMenu() {
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }

        menuBtn.addEventListener('click', toggleMenu);
        overlay.addEventListener('click', toggleMenu);

        // Notification handling
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const notificationMessage = document.getElementById('notificationMessage');
            const notificationIcon = document.getElementById('notificationIcon');

            notificationMessage.textContent = message;
            notificationIcon.textContent = type === 'success' ? 'check_circle' : 'error';
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg flex items-center gap-2 notification ${type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`;
            notification.classList.remove('hidden');

            setTimeout(() => {
                notification.classList.add('opacity-0');
                setTimeout(() => {
                    notification.classList.add('hidden');
                    notification.classList.remove('opacity-0');
                }, 500);
            }, 3000);
        }

        // Manual Verification Modal
        function toggleManualVerifyModal() {
            const modal = document.getElementById('manualVerifyModal');
            modal.classList.toggle('hidden');
            if (!modal.classList.contains('hidden')) {
                document.getElementById('manualVerifyForm').reset();
            }
        }

        // Confirmation Modals
        function showConfirmationModal(record) {
            currentRecord = record;
            const modal = document.getElementById('confirmationModal');
            const details = document.getElementById('confirmationDetails');
            
            details.innerHTML = `
                <div class="flex items-center gap-4 p-4 bg-green-50 rounded-lg">
                    <div class="bg-green-100 p-2 rounded-full">
                        <span class="material-icons text-green-600">check_circle</span>
                    </div>
                    <div>
                        <p class="font-medium text-green-900">Match Found</p>
                        <p class="text-sm text-green-600">Please confirm the details below</p>
                    </div>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Name</label>
                        <p class="text-gray-900">${record.first_name} ${record.last_name}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">National ID</label>
                        <p class="text-gray-900">${record.national_id}</p>
                    </div>
                </div>
            `;
            
            modal.classList.remove('hidden');
        }

        function closeConfirmationModal() {
            document.getElementById('confirmationModal').classList.add('hidden');
            currentRecord = null;
        }

        function showUnverifyModal(record) {
            currentRecord = record;
            const modal = document.getElementById('unverifyModal');
            const details = document.getElementById('unverifyDetails');
            
            details.innerHTML = `
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Name</label>
                        <p class="text-gray-900">${record.first_name} ${record.last_name}</p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">National ID</label>
                        <p class="text-gray-900">${record.national_id}</p>
                    </div>
                </div>
            `;
            
            modal.classList.remove('hidden');
        }

        function closeUnverifyModal() {
            document.getElementById('unverifyModal').classList.add('hidden');
            currentRecord = null;
        }

        async function confirmVerification() {
            if (!currentRecord) {
                showNotification('No record selected for verification', 'error');
                return;
            }
            
            try {
                const form = document.getElementById('manualVerifyForm');
                const comment = form ? form.querySelector('[name="comment"]').value.trim() : '';
                
                const response = await fetch('process_verification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        record_id: currentRecord.id,
                        national_id: currentRecord.national_id,
                        verification_type: 'manual',
                        comment: comment,
                        verified_by: <?php echo $userId; ?>
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                
                if (data.success) {
                    showNotification('Record verified successfully', 'success');
                    closeConfirmationModal();
                    toggleManualVerifyModal();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showNotification(data.message || 'Error verifying record', 'error');
                }
            } catch (error) {
                showNotification('Error processing verification: ' + error.message, 'error');
                console.error('Error:', error);
            }
        }

        async function confirmUnverification() {
            if (!currentRecord) {
                showNotification('No record selected for unverification', 'error');
                return;
            }
            
            try {
                const response = await fetch('process_unverification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        record_id: currentRecord.id,
                        national_id: currentRecord.national_id,
                        verified_by: <?php echo $userId; ?>
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                
                if (data.success) {
                    showNotification('Record unverified successfully', 'success');
                    closeUnverifyModal();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showNotification(data.message || 'Error unverifying record', 'error');
                }
            } catch (error) {
                showNotification('Error processing unverification: ' + error.message, 'error');
                console.error('Error:', error);
            }
        }

        async function verifyRecord(id, identifier) {
            try {
                const response = await fetch('get_record.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: id,
                        national_id: identifier
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                
                if (data.success && data.record) {
                    if (data.record.status === 'Verified') {
                        showNotification('Record is already verified', 'error');
                        return;
                    }
                    currentRecord = {
                        ...data.record,
                        verification_type: 'manual'
                    };
                    showConfirmationModal(currentRecord);
                } else {
                    showNotification(data.message || 'Record not found', 'error');
                }
            } catch (error) {
                showNotification('Error fetching record: ' + error.message, 'error');
                console.error('Error:', error);
            }
        }

        async function unverifyRecord(id, national_id) {
            try {
                const response = await fetch('get_record.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: id,
                        national_id: national_id
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                
                if (data.success && data.record) {
                    if (data.record.status !== 'Verified') {
                        showNotification('Record is not verified', 'error');
                        return;
                    }
                    currentRecord = data.record;
                    showUnverifyModal(currentRecord);
                } else {
                    showNotification(data.message || 'Record not found', 'error');
                }
            } catch (error) {
                showNotification('Error fetching record: ' + error.message, 'error');
                console.error('Error:', error);
            }
        }

        // Filter records
        function filterRecords() {
            const search = document.getElementById('searchRecords').value.toLowerCase().trim();
            const ta = document.getElementById('taFilter').value.toLowerCase();
            const village = document.getElementById('villageFilter').value.toLowerCase();
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;

            const rows = document.querySelectorAll('tbody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const formNumber = row.querySelector('td:nth-child(1)')?.textContent.toLowerCase() || '';
                const name = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                const nationalId = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
                const villageCell = row.querySelector('td:nth-child(4)')?.textContent.toLowerCase() || '';
                const taCell = row.querySelector('td:nth-child(5)')?.textContent.toLowerCase() || '';
                const verifiedAt = row.getAttribute('data-verified-at') || '';

                let show = true;

                if (search && !formNumber.includes(search) && !name.includes(search) && !nationalId.includes(search)) {
                    show = false;
                }

                if (ta && !taCell.includes(ta)) {
                    show = false;
                }

                if (village && !villageCell.includes(village)) {
                    show = false;
                }

                if (dateFrom || dateTo) {
                    if (!verifiedAt) {
                        show = false;
                    } else {
                        const recordDate = new Date(verifiedAt);
                        if (dateFrom && recordDate < new Date(dateFrom + 'T00:00:00')) {
                            show = false;
                        }
                        if (dateTo && recordDate > new Date(dateTo + 'T23:59:59')) {
                            show = false;
                        }
                    }
                }

                row.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            updateRecordCount(visibleCount);
        }

        function clearFilters() {
            const inputs = ['searchRecords', 'taFilter', 'villageFilter', 'dateFrom', 'dateTo'];
            inputs.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            filterRecords();
            document.getElementById('advancedFilters').classList.add('hidden');
        }

        function applyFilters() {
            filterRecords();
            document.getElementById('advancedFilters').classList.add('hidden');
        }

        function updateRecordCount(visible) {
            const total = <?php echo count($verifiedRecords); ?>;
            document.getElementById('recordCount').textContent = `Showing ${visible} of ${total} records`;
        }

        function exportRecords() {
            window.location.href = 'export_records.php';
        }

        // Attach event listeners
        const filterInputs = ['searchRecords', 'taFilter', 'villageFilter', 'dateFrom', 'dateTo'];
        filterInputs.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', filterRecords);
                if (id === 'searchRecords') {
                    el.addEventListener('input', debounce(filterRecords, 300));
                }
            }
        });

        document.getElementById('advancedFiltersBtn').addEventListener('click', function() {
            document.getElementById('advancedFilters').classList.toggle('hidden');
        });

        document.getElementById('manualVerifyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const nationalId = this.querySelector('[name="national_id"]').value.trim();
            if (nationalId) {
                verifyRecord(null, nationalId);
            } else {
                showNotification('Please enter a valid National ID', 'error');
            }
        });

        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // View record details
        function viewDetails(id) {
            window.location.href = `record_details.php?id=${id}`;
        }
    </script>
</body>
</html>