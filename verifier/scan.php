<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: /id_verification/login.php");
    exit;
}

$username = $_SESSION['username'];
$userId = $_SESSION['user_id'];

// Fetch all records that need verification
$recordsQuery = "
    SELECT 
        r.*,
        v.verified_at,
        v.comment,
        v.verification_type,
        u.username as verified_by
    FROM records r
    LEFT JOIN verifications v ON r.id = v.record_id
    LEFT JOIN users u ON v.verified_by = u.id
    ORDER BY r.created_at DESC";

$recordsResult = $conn->query($recordsQuery);
$records = $recordsResult ? $recordsResult->fetch_all(MYSQLI_ASSOC) : [];

// Get statistics
$stats = [
    'total_records' => count($records),
    'verified_records' => array_sum(array_map(fn($r) => $r['status'] === 'Verified' ? 1 : 0, $records)),
    'pending_records' => array_sum(array_map(fn($r) => $r['status'] === 'Unverified' ? 1 : 0, $records))
];

// Get unique filter values from records
$tas = array_unique(array_filter(array_column($records, 'traditional_authority')));
$projects = array_unique(array_filter(array_column($records, 'project')));
$genders = ['M', 'F', 'Male', 'Female'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan & Verify</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode"></script>
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
<body class="bg-gray-50 min-h-screen">
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
                    <a href="scan.php" class="flex items-center gap-3 p-3 rounded-lg bg-green-50 text-green-600 font-medium">
                        <span class="material-icons text-lg">qr_code_scanner</span>
                        Scan & Verify
                    </a>
                    <a href="records.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors">
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
                        <h1 class="text-xl font-semibold text-gray-900">Scan & Verify</h1>
                    </div>
                    <div class="flex items-center gap-3">
                        <button id="manualVerifyBtn" onclick="toggleManualVerifyModal()" class="btn-primary px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                            <span class="material-icons text-sm">edit</span>
                            Manual Verify
                        </button>
                        <button id="scanBtn" onclick="toggleScanner()" class="btn-primary px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                            <span class="material-icons text-sm">qr_code_scanner</span>
                            Start Scanning
                        </button>
                    </div>
                </div>
                <div class="px-4 pb-4">
                    <div class="flex flex-col md:flex-row md:items-center gap-4">
                        <div class="flex-1 w-full">
                            <div class="relative">
                                <input id="searchInput" type="text" placeholder="Search by form number, name, or ID..." class="w-full pl-12 pr-4 py-3 text-base rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <span class="material-icons absolute left-4 top-3.5 text-gray-400">search</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <select id="filterStatus" class="px-4 py-3 rounded-xl border border-gray-300 text-base focus:ring-2 focus:ring-green-500">
                                <option value="">All Status</option>
                                <option value="Verified">Verified</option>
                                <option value="Unverified">Unverified</option>
                            </select>
                            <select id="filterTA" class="px-4 py-3 rounded-xl border border-gray-300 text-base focus:ring-2 focus:ring-green-500">
                                <option value="">All TAs</option>
                                <?php foreach($tas as $ta): ?>
                                <option value="<?php echo htmlspecialchars($ta); ?>"><?php echo htmlspecialchars($ta); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button id="advancedFiltersBtn" class="flex items-center gap-2 px-4 py-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors">
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
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Project</label>
                                    <select id="filterProject" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500">
                                        <option value="">All Projects</option>
                                        <?php foreach($projects as $project): ?>
                                        <option value="<?php echo htmlspecialchars($project); ?>"><?php echo htmlspecialchars($project); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                                    <select id="filterGender" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500">
                                        <option value="">All Genders</option>
                                        <?php foreach($genders as $gender): ?>
                                        <option value="<?php echo htmlspecialchars($gender); ?>"><?php echo htmlspecialchars($gender); ?></option>
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
                                    <p class="text-sm font-medium text-gray-500">Verified Records</p>
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $stats['verified_records']; ?></h3>
                                </div>
                                <div class="bg-green-50 p-3 rounded-xl">
                                    <span class="material-icons text-2xl text-green-600">check_circle</span>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center text-sm text-green-600">
                                <span class="material-icons text-base mr-1">trending_up</span>
                                <?php echo $stats['total_records'] > 0 ? round(($stats['verified_records']/$stats['total_records']) * 100) : 0; ?>% verified
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Pending Verification</p>
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $stats['pending_records']; ?></h3>
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

                    <!-- Records Table -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-100">
                            <div class="flex items-center justify-between">
                                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <span class="material-icons text-green-600">folder</span>
                                    Records
                                </h2>
                                <span id="recordCount" class="text-sm text-gray-600">
                                    Showing <?php echo count($records); ?> of <?php echo count($records); ?> records
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
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Gender</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">TA</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Status</th>
                                        <th class="text-right py-4 px-6 text-sm font-semibold text-gray-900">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach($records as $record): ?>
                                    <tr class="table-row transition-colors" 
                                        data-created-at="<?php echo htmlspecialchars($record['created_at']); ?>" 
                                        data-project="<?php echo htmlspecialchars($record['project'] ?? ''); ?>">
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
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo in_array($record['gender'], ['M', 'Male']) ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800'; ?>">
                                                <?php echo htmlspecialchars($record['gender']); ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="text-sm text-gray-600"><?php echo htmlspecialchars($record['traditional_authority'] ?? ''); ?></span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $record['status'] === 'Verified' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                                <span class="material-icons text-base mr-1"><?php echo $record['status'] === 'Verified' ? 'check_circle' : 'pending'; ?></span>
                                                <?php echo htmlspecialchars($record['status']); ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <div class="flex items-center justify-end gap-2">
                                                <?php if($record['status'] !== 'Verified'): ?>
                                                <button onclick="verifyRecord(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['national_id']); ?>')"
                                                        class="p-2 text-green-600 hover:text-green-800 rounded-lg hover:bg-green-50"
                                                        title="Verify">
                                                    <span class="material-icons">verified</span>
                                                </button>
                                                <?php else: ?>
                                                <button onclick="unverifyRecord(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['national_id']); ?>')"
                                                        class="p-2 text-red-600 hover:text-red-800 rounded-lg hover:bg-red-50"
                                                        title="Unverify">
                                                    <span class="material-icons">cancel</span>
                                                </button>
                                                <?php endif; ?>
                                                <button onclick="viewDetails(<?php echo $record['id']; ?>)"
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

    <!-- Scanner Modal -->
    <div id="scannerModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white p-8 rounded-xl shadow-lg max-w-lg w-full">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <span class="material-icons text-green-600">barcode</span>
                    Scan Barcode
                </h2>
                <button onclick="toggleScanner()" class="text-gray-500 hover:text-gray-700">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <div id="barcode-reader" class="w-full"></div>
            <div id="barcode-reader-results" class="mt-4 text-center text-sm text-gray-600"></div>
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
                <button onclick="closeConfirmationModal()" 
                        class="px-4 py-2 text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-100">
                    Cancel
                </button>
                <button onclick="confirmVerification()" 
                        class="px-6 py-2 btn-primary rounded-lg">
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
                <button onclick="closeUnverifyModal()" 
                        class="px-4 py-2 text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-100">
                    Cancel
                </button>
                <button onclick="confirmUnverification()" 
                        class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">
                    Unverify Record
                </button>
            </div>
        </div>
    </div>

    <script>
        // Initialize currentRecord at the top to avoid reference errors
        let currentRecord = null;

        // Mobile menu toggle
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.querySelector('aside');
        const overlay = document.getElementById('overlay') || document.createElement('div');
        overlay.id = 'overlay';
        overlay.className = 'hidden fixed inset-0 bg-black bg-opacity-50 z-30';
        document.body.appendChild(overlay);

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

        // Barcode Scanner
        let html5QrcodeScanner = null;

        function toggleScanner() {
            const modal = document.getElementById('scannerModal');
            modal.classList.toggle('hidden');

            if (!modal.classList.contains('hidden')) {
                if (!html5QrcodeScanner) {
                    html5QrcodeScanner = new Html5QrcodeScanner(
                        "barcode-reader", 
                        { 
                            fps: 10, 
                            qrbox: { width: 250, height: 250 },
                            formatsToSupport: [
                                Html5QrcodeSupportedFormats.CODE_128,
                                Html5QrcodeSupportedFormats.CODE_39,
                                Html5QrcodeSupportedFormats.EAN_13,
                                Html5QrcodeSupportedFormats.UPC_A
                            ]
                        }
                    );
                    html5QrcodeScanner.render(onScanSuccess, onScanError);
                }
            } else {
                if (html5QrcodeScanner) {
                    html5QrcodeScanner.clear().catch(err => console.error('Error clearing scanner:', err));
                    html5QrcodeScanner = null;
                }
            }
        }

        function onScanSuccess(decodedText) {
            document.getElementById('barcode-reader-results').innerHTML = `Scanned: ${decodedText}`;
            verifyRecord(null, decodedText);
        }

        function onScanError(error) {
            console.warn(`Scan error: ${error}`);
            showNotification('Failed to scan barcode. Please try again.', 'error');
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
            currentRecord = record; // Set currentRecord here
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
            if (html5QrcodeScanner) {
                html5QrcodeScanner.pause();
            }
        }

        function closeConfirmationModal() {
            document.getElementById('confirmationModal').classList.add('hidden');
            currentRecord = null;
            if (html5QrcodeScanner) {
                html5QrcodeScanner.resume();
            }
        }

        function showUnverifyModal(record) {
            currentRecord = record; // Set currentRecord here
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
                        verification_type: currentRecord.verification_type || 'scan',
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
                    toggleScanner();
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
                        verification_type: id ? 'manual' : 'scan'
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

        // Improved Filter Logic
        function filterRecords() {
            const search = document.getElementById('searchInput').value.toLowerCase().trim();
            const status = document.getElementById('filterStatus').value.toLowerCase();
            const ta = document.getElementById('filterTA').value;
            const project = document.getElementById('filterProject')?.value.toLowerCase() || '';
            const gender = document.getElementById('filterGender')?.value || '';
            const dateFrom = document.getElementById('dateFrom')?.value || '';
            const dateTo = document.getElementById('dateTo')?.value || '';

            const rows = document.querySelectorAll('tbody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const formNumber = row.querySelector('td:nth-child(1)')?.textContent.toLowerCase() || '';
                const name = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                const nationalId = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
                const genderCell = row.querySelector('td:nth-child(4)')?.textContent || '';
                const taCell = row.querySelector('td:nth-child(5)')?.textContent || '';
                const statusCell = row.querySelector('td:nth-child(6)')?.textContent.toLowerCase() || '';
                const projectCell = row.getAttribute('data-project')?.toLowerCase() || '';
                const createdAt = row.getAttribute('data-created-at') || '';

                let show = true;

                if (search && !formNumber.includes(search) && !name.includes(search) && !nationalId.includes(search)) {
                    show = false;
                }

                if (status && !statusCell.includes(status)) {
                    show = false;
                }

                if (ta && taCell !== ta) {
                    show = false;
                }

                if (project && !projectCell.includes(project)) {
                    show = false;
                }

                if (gender && genderCell !== gender) {
                    show = false;
                }

                if (dateFrom || dateTo) {
                    if (!createdAt) {
                        show = false;
                    } else {
                        const recordDate = new Date(createdAt);
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
            const inputs = ['searchInput', 'filterStatus', 'filterTA', 'filterProject', 'filterGender', 'dateFrom', 'dateTo'];
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
            const total = document.querySelectorAll('tbody tr').length;
            document.getElementById('recordCount').textContent = `Showing ${visible} of ${total} records`;
        }

        // Attach event listeners
        const filterInputs = ['searchInput', 'filterStatus', 'filterTA', 'filterProject', 'filterGender', 'dateFrom', 'dateTo'];
        filterInputs.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', filterRecords);
                if (id === 'searchInput') {
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

        // Debounce function to optimize search
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