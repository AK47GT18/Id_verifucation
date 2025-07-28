<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/record_manager.php';

// Restrict access to logged-in users
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || !isset($_SESSION['user_id'])) {
    header("Location: /id_verification/login.php");
    exit;
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];
$userId = $_SESSION['user_id'];
$recordManager = new RecordManager($conn);

// Pagination settings
$recordsPerPage = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $recordsPerPage;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    header('Content-Type: application/json');

    if ($action === 'add_record' && $role === 'Admin') {
        $data = [
            'form_number' => trim($_POST['form_number'] ?? ''),
            'national_id' => trim($_POST['national_id'] ?? ''),
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'gender' => trim($_POST['gender'] ?? ''),
            'project' => trim($_POST['project'] ?? ''),
            'traditional_authority' => trim($_POST['traditional_authority'] ?? ''),
            'group_village_head' => trim($_POST['group_village_head'] ?? ''),
            'village' => trim($_POST['village'] ?? ''),
            'SCTP_UBR_NUMBER' => trim($_POST['SCTP_UBR_NUMBER'] ?? ''),
            'HH_CODE' => trim($_POST['HH_CODE'] ?? ''),
            'TA' => trim($_POST['TA'] ?? ''),
            'CLUSTER' => trim($_POST['CLUSTER'] ?? ''),
            'ZONE' => trim($_POST['ZONE'] ?? ''),
            'household_head_name' => trim($_POST['household_head_name'] ?? '')
        ];
        echo json_encode($recordManager->addRecord($data));
        exit;
    } elseif ($action === 'edit_record' && $role === 'Admin') {
        $id = (int)$_POST['id'];
        $data = [
            'form_number' => trim($_POST['form_number'] ?? ''),
            'national_id' => trim($_POST['national_id'] ?? ''),
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'gender' => trim($_POST['gender'] ?? ''),
            'project' => trim($_POST['project'] ?? ''),
            'traditional_authority' => trim($_POST['traditional_authority'] ?? ''),
            'group_village_head' => trim($_POST['group_village_head'] ?? ''),
            'village' => trim($_POST['village'] ?? ''),
            'SCTP_UBR_NUMBER' => trim($_POST['SCTP_UBR_NUMBER'] ?? ''),
            'HH_CODE' => trim($_POST['HH_CODE'] ?? ''),
            'TA' => trim($_POST['TA'] ?? ''),
            'CLUSTER' => trim($_POST['CLUSTER'] ?? ''),
            'ZONE' => trim($_POST['ZONE'] ?? ''),
            'household_head_name' => trim($_POST['household_head_name'] ?? '')
        ];
        echo json_encode($recordManager->updateRecord($id, $data));
        exit;
    } elseif ($action === 'delete_record' && $role === 'Admin') {
        $id = (int)$_POST['id'];
        echo json_encode($recordManager->deleteRecord($id));
        exit;
    } elseif ($action === 'verify_record') {
        $id = (int)$_POST['id'];
        $comment = trim($_POST['comment'] ?? '');
        $verification_type = trim($_POST['verification_type'] ?? 'manual');
        echo json_encode($recordManager->verifyRecord($id, $username, $comment, $verification_type));
        exit;
    } elseif ($action === 'unverify_record') {
        $id = (int)$_POST['id'];
        echo json_encode($recordManager->unverifyRecord($id, $userId));
        exit;
    } elseif ($action === 'scan_barcode') {
        $barcode = trim($_POST['barcode'] ?? '');
        $record_id = $recordManager->getRecordByBarcode($barcode);
        if ($record_id) {
            echo json_encode($recordManager->verifyRecord($record_id, $username, null, 'scan'));
        } else {
            echo json_encode(['success' => false, 'message' => 'No record found for barcode: ' . htmlspecialchars($barcode)]);
        }
        exit;
    } elseif ($action === 'get_record') {
        $id = (int)$_POST['id'];
        $record = $recordManager->getRecordDetails($id);
        echo json_encode($record ? ['success' => true, 'record' => $record] : ['success' => false, 'message' => 'Record not found.']);
        exit;
    } elseif ($action === 'search_records') {
        $search = trim($_POST['search'] ?? '');
        $status = trim($_POST['status'] ?? '');
        $ta = trim($_POST['ta'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $project = trim($_POST['project'] ?? '');
        $village = trim($_POST['village'] ?? '');
        $dateFrom = trim($_POST['dateFrom'] ?? '');
        $dateTo = trim($_POST['dateTo'] ?? '');
        $verifiedBy = trim($_POST['verifiedBy'] ?? '');
        $verificationType = trim($_POST['verificationType'] ?? '');
        $filters = compact('search', 'status', 'ta', 'gender', 'project', 'village', 'dateFrom', 'dateTo', 'verifiedBy', 'verificationType');
        $records = $recordManager->searchRecords($filters, $role, $username, $offset, $recordsPerPage);
        $totalRecords = $recordManager->countRecords($filters, $role, $username);
        if (isset($_POST['export']) && $_POST['export'] === 'true') {
            echo json_encode(['success' => true, 'records' => $records]);
        } else {
            echo json_encode(['success' => true, 'records' => $records, 'totalRecords' => $totalRecords]);
        }
        exit;
    } elseif ($action === 'import_excel' && $role === 'Admin') {
        require '../vendor/autoload.php';
        try {
            $file = $_FILES['excel_file'];
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            array_shift($rows);
            $records = [];
            foreach ($rows as $row) {
                $records[] = [
                    'form_number' => $row[0] ?? '',
                    'national_id' => $row[1] ?? '',
                    'first_name' => $row[2] ?? '',
                    'last_name' => $row[3] ?? '',
                    'gender' => $row[4] ?? '',
                    'project' => $row[5] ?? '',
                    'traditional_authority' => $row[6] ?? '',
                    'village' => $row[7] ?? '',
                    'SCTP_UBR_NUMBER' => $row[8] ?? '',
                    'HH_CODE' => $row[9] ?? '',
                    'TA' => $row[10] ?? '',
                    'CLUSTER' => $row[11] ?? '',
                    'ZONE' => $row[12] ?? '',
                    'HOUSEHOLD_HEAD_NAME' => $row[13] ?? ''
                ];
            }
            echo json_encode($recordManager->importRecords($records));
            exit;
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error importing file: ' . $e->getMessage()
            ]);
            exit;
        }
    }
}

// Fetch initial records with pagination
$records = $recordManager->getRecords($role, $username, $offset, $recordsPerPage);
$totalRecords = $recordManager->countRecords([], $role, $username);
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get total counts for statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN r.status = 'Verified' THEN 1 ELSE 0 END) as verified_records,
        SUM(CASE WHEN v.verification_type = 'manual' THEN 1 ELSE 0 END) as manual_verifications,
        SUM(CASE WHEN v.verification_type = 'scan' THEN 1 ELSE 0 END) as scan_verifications
    FROM records r
    LEFT JOIN verifications v ON r.id = v.record_id";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();
$totalRecords = $stats['total_records'];
$totalVerified = $stats['verified_records'];
$manualVerifications = $stats['manual_verifications'] ?? 0;
$scanVerifications = $stats['scan_verifications'] ?? 0;
$remainingToVerify = $totalRecords - $totalVerified;

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

// Get filter dropdown data
$filterQuery = "
    SELECT 
        DISTINCT traditional_authority AS TA,
        project,
        village,
        gender,
        v.verification_type,
        u.username AS verified_by
    FROM records r
    LEFT JOIN verifications v ON r.id = v.record_id
    LEFT JOIN users u ON v.verified_by = u.id
    WHERE traditional_authority IS NOT NULL 
       OR project IS NOT NULL 
       OR village IS NOT NULL
       OR gender IS NOT NULL
       OR v.verification_type IS NOT NULL
       OR u.username IS NOT NULL";
$filterResult = $conn->query($filterQuery);
$tas = [];
$projects = [];
$villages = [];
$genders = [];
$verificationTypes = [];
$verifiedByUsers = [];
while ($row = $filterResult->fetch_assoc()) {
    if (!empty($row['TA'])) $tas[] = $row['TA'];
    if (!empty($row['project'])) $projects[] = $row['project'];
    if (!empty($row['village'])) $villages[] = $row['village'];
    if (!empty($row['gender'])) $genders[] = $row['gender'];
    if (!empty($row['verification_type'])) $verificationTypes[] = $row['verification_type'];
    if (!empty($row['verified_by'])) $verifiedByUsers[] = $row['verified_by'];
}
$tas = array_unique($tas);
$projects = array_unique($projects);
$villages = array_unique($villages);
$genders = array_unique($genders);
$verificationTypes = array_unique($verificationTypes);
$verifiedByUsers = array_unique($verifiedByUsers);
sort($tas);
sort($projects);
sort($villages);
sort($genders);
sort($verificationTypes);
sort($verifiedByUsers);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Management - ID Verification Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
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
            border-color: #3B82F6;
        }
        .btn-primary {
            background-color: #3B82F6;
            color: white;
        }
        .btn-primary:hover {
            background-color: #2563EB;
        }
        .table-row:hover {
            background-color: #F9FAFB;
        }
        .modal {
            animation: fadeIn 0.3s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .pagination a.active {
            background-color: #3B82F6;
            color: white;
        }
        .pagination a:hover:not(.active) {
            background-color: #E5E7EB;
        }
        .notification {
            transition: opacity 0.5s ease-out;
        }
        #scannerContainer {
            width: 100%;
            max-width: 500px;
            height: 300px;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg fixed h-full transition-transform duration-300 ease-in-out transform -translate-x-full md:translate-x-0 z-40">
            <div class="h-full flex flex-col">
                <div class="flex items-center gap-3 p-6 border-b border-gray-100">
                    <div class="bg-blue-50 p-2 rounded-lg">
                        <span class="material-icons text-2xl text-blue-600">admin_panel_settings</span>
                    </div>
                    <div>
                        <div class="font-bold text-gray-900">ID Verification Portal</div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($role); ?> Panel</div>
                    </div>
                </div>
                <nav class="flex-1 p-4 space-y-2">
                    <a href="index.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors <?php if(basename($_SERVER['PHP_SELF']) === 'index.php') echo 'bg-blue-50 text-blue-600 font-medium'; ?>">
                        <span class="material-icons text-lg">dashboard</span>
                        Dashboard
                    </a>
                    <?php if ($role === 'Admin'): ?>
                    <a href="user_management.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors <?php if(basename($_SERVER['PHP_SELF']) === 'user_management.php') echo 'bg-blue-50 text-blue-600 font-medium'; ?>">
                        <span class="material-icons text-lg">group</span>
                        User Management
                    </a>
                    <?php endif; ?>
                    <a href="record_management.php" class="flex items-center gap-3 p-3 rounded-lg bg-blue-50 text-blue-600 font-medium">
                        <span class="material-icons text-lg">folder</span>
                        Record Management
                    </a>
                    <a href="reports.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors <?php if(basename($_SERVER['PHP_SELF']) === 'reports.php') echo 'bg-blue-50 text-blue-600 font-medium'; ?>">
                        <span class="material-icons text-lg">bar_chart</span>
                        Reports
                    </a>
                    <?php if ($role === 'Admin'): ?>
                    <a href="configurations.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors <?php if(basename($_SERVER['PHP_SELF']) === 'configurations.php') echo 'bg-blue-50 text-blue-600 font-medium'; ?>">
                        <span class="material-icons text-lg">settings</span>
                        Configurations
                    </a>
                    <?php endif; ?>
                </nav>
                <div class="border-t border-gray-100 p-4">
                    <div class="flex items-center gap-3 p-2">
                        <div class="bg-gray-100 p-2 rounded-full">
                            <span class="material-icons text-gray-600">person</span>
                        </div>
                        <div>
                            <div class="font-medium text-sm"><?php echo htmlspecialchars($username); ?></div>
                            <a href="/id_verification/auth/logout.php" class="text-xs text-red-600 hover:text-red-700">Sign out</a>
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
                        <h1 class="text-xl font-semibold text-gray-900">Record Management</h1>
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="advancedFiltersBtn" class="inline-flex items-center gap-1 px-3 py-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                            <span class="material-icons text-sm">tune</span>
                            Filters
                        </button>
                        <button onclick="toggleModal('barcodeModal')" class="inline-flex items-center gap-1 px-3 py-2 text-sm btn-primary rounded-lg">
                            <span class="material-icons text-sm">qr_code_scanner</span>
                            Scan Barcode
                        </button>
                        <button onclick="toggleModal('manualVerifyModal')" class="inline-flex items-center gap-1 px-3 py-2 text-sm btn-primary rounded-lg">
                            <span class="material-icons text-sm">edit</span>
                            Manual Verify
                        </button>
                        <?php if ($role === 'Admin'): ?>
                        <button onclick="exportRecords()" class="inline-flex items-center gap-1 px-3 py-2 text-sm btn-primary rounded-lg">
                            <span class="material-icons text-sm">download</span>
                            Export
                        </button>
                        <button onclick="toggleModal('addRecordModal')" class="inline-flex items-center gap-1 px-3 py-2 text-sm btn-primary rounded-lg">
                            <span class="material-icons text-sm">add</span>
                            Add
                        </button>
                        <button onclick="toggleModal('importModal')" class="inline-flex items-center gap-1 px-3 py-2 text-sm btn-primary rounded-lg">
                            <span class="material-icons text-sm">upload_file</span>
                            Import
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="px-4 pb-4">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex-1 w-full">
                            <div class="relative">
                                <input 
                                    id="searchRecords" 
                                    type="text" 
                                    placeholder="Search by form number, name, ID, or verified by..." 
                                    class="w-full pl-12 pr-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-500"
                                >
                                <span class="material-icons absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400">search</span>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-6 bg-gray-50">
                <div class="max-w-7xl mx-auto">
                    <!-- Notification -->
                    <div id="notification" class="hidden fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg notification">
                        <div class="flex items-center gap-2">
                            <span id="notificationIcon" class="material-icons"></span>
                            <span id="notificationMessage"></span>
                        </div>
                    </div>

                    <!-- Statistics Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Total Records</p>
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $totalRecords; ?></h3>
                                </div>
                                <div class="bg-blue-50 p-3 rounded-xl">
                                    <span class="material-icons text-2xl text-blue-600">folder</span>
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
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $totalVerified; ?></h3>
                                </div>
                                <div class="bg-blue-50 p-3 rounded-xl">
                                    <span class="material-icons text-2xl text-blue-600">verified</span>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center text-sm text-blue-600">
                                <span class="material-icons text-base mr-1">trending_up</span>
                                <?php echo $totalRecords ? round(($totalVerified/$totalRecords) * 100) : 0; ?>% completion
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Manual Verifications</p>
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $manualVerifications; ?></h3>
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
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $scanVerifications; ?></h3>
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
                    <?php if ($role === 'Admin'): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
                        <div class="p-6 border-b border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                <span class="material-icons text-blue-600">people</span>
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
                    <?php endif; ?>

                    <!-- Advanced Filters Panel -->
                    <div id="advancedFilters" class="hidden bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
                        <div class="p-6 border-b border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                <span class="material-icons text-blue-600">tune</span>
                                Advanced Filters
                            </h2>
                        </div>
                        <div class="p-6 bg-gray-50 rounded-b-xl">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                    <select id="statusFilter" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-500">
                                        <option value="">All Statuses</option>
                                        <option value="Verified">Verified</option>
                                        <option value="Unverified">Unverified</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Date Range (Verified At)</label>
                                    <div class="grid grid-cols-2 gap-4">
                                        <input type="date" id="dateFrom" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-500">
                                        <input type="date" id="dateTo" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Verification Type</label>
                                    <select id="verificationTypeFilter" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-500">
                                        <option value="">All Types</option>
                                        <?php foreach($verificationTypes as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars(ucfirst($type)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Verified By</label>
                                    <select id="verifiedByFilter" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-500">
                                        <option value="">All Users</option>
                                        <?php foreach($verifiedByUsers as $user): ?>
                                        <option value="<?php echo htmlspecialchars($user); ?>"><?php echo htmlspecialchars($user); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Project</label>
                                    <select id="projectFilter" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-500">
                                        <option value="">All Projects</option>
                                        <?php foreach($projects as $project): ?>
                                        <option value="<?php echo htmlspecialchars($project); ?>"><?php echo htmlspecialchars($project); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Village</label>
                                    <select id="villageFilter" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-500">
                                        <option value="">All Villages</option>
                                        <?php foreach($villages as $village): ?>
                                        <option value="<?php echo htmlspecialchars($village); ?>"><?php echo htmlspecialchars($village); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Traditional Authority</label>
                                    <select id="taFilter" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-500">
                                        <option value="">All TAs</option>
                                        <?php foreach($tas as $ta): ?>
                                        <option value="<?php echo htmlspecialchars($ta); ?>"><?php echo htmlspecialchars($ta); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                                    <select id="genderFilter" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-500">
                                        <option value="">All Genders</option>
                                        <?php foreach($genders as $gender): ?>
                                        <option value="<?php echo htmlspecialchars($gender); ?>"><?php echo htmlspecialchars($gender); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="flex justify-end mt-6 gap-3">
                                <button onclick="clearFilters()" class="px-4 py-2 text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-100">
                                    Clear All
                                </button>
                                <button onclick="applyFilters()" class="px-6 py-2 btn-primary rounded-lg">
                                    Apply Filters
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Records Table -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-100">
                            <div class="flex items-center justify-between">
                                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <span class="material-icons text-blue-600">folder</span>
                                    Verification Records
                                </h2>
                                <span id="recordCount" class="text-sm text-gray-600">
                                    Showing <?php echo count($records); ?> of <?php echo $totalRecords; ?> records
                                </span>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-200">
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Form Number</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Name</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">National ID</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Gender</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Project</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">TA</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Village</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Status</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Method</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Verified By</th>
                                        <th class="text-right py-4 px-6 text-sm font-semibold text-gray-900">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="recordsTableBody" class="divide-y divide-gray-200">
                                    <?php if (empty($records)): ?>
                                        <tr>
                                            <td colspan="11" class="py-4 px-6 text-center text-gray-500">
                                                No records found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($records as $record): ?>
                                            <tr class="table-row transition-colors" data-verified-at="<?php echo htmlspecialchars($record['verified_at'] ?? ''); ?>">
                                                <td class="py-4 px-6">
                                                    <span class="inline-flex items-center px-3 py-1 rounded-lg bg-gray-100 text-gray-900 text-sm font-medium">
                                                        <?php echo htmlspecialchars($record['form_number']); ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-6">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-6">
                                                    <span class="text-sm text-gray-600">
                                                        <?php echo htmlspecialchars($record['national_id']); ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-6">
                                                    <span class="text-sm text-gray-600">
                                                        <?php echo htmlspecialchars($record['gender'] ?? '-'); ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-6">
                                                    <span class="text-sm text-gray-600">
                                                        <?php echo htmlspecialchars($record['project'] ?? '-'); ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-6">
                                                    <span class="text-sm text-gray-600">
                                                        <?php echo htmlspecialchars($record['traditional_authority'] ?? '-'); ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-6">
                                                    <span class="text-sm text-gray-600">
                                                        <?php echo htmlspecialchars($record['village'] ?? '-'); ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-6">
                                                    <?php if($record['status'] === 'Verified'): ?>
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                                            <span class="material-icons text-sm mr-1">check_circle</span>
                                                            Verified
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                                            <span class="material-icons text-sm mr-1">pending</span>
                                                            Pending
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="py-4 px-6">
                                                    <span class="text-sm text-gray-600">
                                                        <?php echo htmlspecialchars(ucfirst($record['verification_type'] ?? '-')); ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-6">
                                                    <span class="text-sm text-gray-600">
                                                        <?php echo htmlspecialchars($record['verified_by'] ?? '-'); ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-6">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <button onclick="viewDetails(<?php echo $record['id']; ?>)" 
                                                                class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
                                                                title="View Details">
                                                            <span class="material-icons">visibility</span>
                                                        </button>
                                                        <?php if ($role === 'Admin'): ?>
                                                            <button onclick="editRecord(<?php echo $record['id']; ?>)" 
                                                                    class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
                                                                    title="Edit">
                                                                <span class="material-icons">edit</span>
                                                            </button>
                                                            <button onclick="showConfirmModal('delete_record', <?php echo $record['id']; ?>, 'delete')" 
                                                                    class="p-2 text-red-400 hover:text-red-600 rounded-lg hover:bg-gray-100"
                                                                    title="Delete">
                                                                <span class="material-icons">delete</span>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($record['status'] !== 'Verified'): ?>
                                                            <button onclick="showVerifyConfirmModal(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['national_id']); ?>')" 
                                                                    class="p-2 text-blue-400 hover:text-blue-600 rounded-lg hover:bg-gray-100"
                                                                    title="Verify">
                                                                <span class="material-icons">verified</span>
                                                            </button>
                                                        <?php elseif ($role === 'Admin' || $record['verified_by'] === $username): ?>
                                                            <button onclick="showUnverifyModal(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['national_id']); ?>')" 
                                                                    class="p-2 text-red-400 hover:text-red-600 rounded-lg hover:bg-gray-100"
                                                                    title="Unverify">
                                                                <span class="material-icons">cancel</span>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination -->
                        <div class="p-6 flex justify-between items-center border-t border-gray-100">
                            <div class="text-sm text-gray-600">
                                Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                            </div>
                            <div class="flex gap-2">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo ($page - 1); ?>" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-100">Previous</a>
                                <?php endif; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo ($page + 1); ?>" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-100">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Barcode Verification Modal -->
    <div id="barcodeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center modal">
        <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full">
            <h2 class="text-lg font-semibold text-gray-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">qr_code_scanner</span>
                Verify by Barcode
            </h2>
            <div id="scannerContainer"></div>
            <form id="barcodeForm" class="mt-4">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Or Enter Barcode Manually</label>
                    <div class="flex gap-2">
                        <input type="text" id="barcodeInput" 
                               class="flex-1 px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="Enter barcode">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <span class="material-icons">search</span>
                        </button>
                    </div>
                </div>
            </form>
            <div id="barcodeResult" class="mt-4 text-sm text-gray-600 hidden">
                <div class="flex items-center gap-2 p-4 rounded-lg">
                    <span id="barcodeResultIcon" class="material-icons"></span>
                    <span id="barcodeMessage" class="font-medium"></span>
                </div>
            </div>
            <div class="mt-4">
                <button onclick="stopScanner(); toggleModal('barcodeModal')" class="w-full px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg">
                    <span class="material-icons">close</span>
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Manual Verification Modal -->
    <div id="manualVerifyModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center modal">
        <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full">
            <h2 class="text-lg font-semibold text-gray-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">edit</span>
                Manual Verification
            </h2>
            <form id="manualVerifyForm">
                <input type="hidden" name="action" value="verify_record">
                <input type="hidden" name="verification_type" value="manual">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">National ID</label>
                    <input type="text" name="national_id" id="manualNationalId" 
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Enter National ID">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Comment (Optional)</label>
                    <textarea name="comment" rows="3" 
                              class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-500"
                              placeholder="Add verification notes..."></textarea>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 btn-primary py-2 rounded-lg">Verify Record</button>
                    <button type="button" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 rounded-lg" onclick="toggleModal('manualVerifyModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Record Modal -->
    <div id="addRecordModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center modal">
        <form id="addRecordForm" class="bg-white p-8 rounded-xl shadow-lg max-w-lg w-full max-h-[80vh] overflow-y-auto" method="post">
            <input type="hidden" name="action" value="add_record">
            <h2 class="text-lg font-semibold text-gray-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">add</span>
                Add Record
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Form Number</label>
                    <input type="text" name="form_number" placeholder="e.g., 20875144" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">National ID</label>
                    <input type="text" name="national_id" placeholder="e.g., CH41QENN" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                    <input type="text" name="first_name" placeholder="e.g., Pearson" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                    <input type="text" name="last_name" placeholder="e.g., Mgala" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                    <select name="gender" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Gender</option>
                        <option value="M">M</option>
                        <option value="F">F</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                    <input type="text" name="project" placeholder="e.g., Maposya P4" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Traditional Authority</label>
                    <input type="text" name="traditional_authority" placeholder="e.g., Mwenewenya" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Group Village Head</label>
                    <input type="text" name="group_village_head" placeholder="e.g., Chuba" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Village</label>
                    <input type="text" name="village" placeholder="e.g., Chobwe" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">SCTP UBR Number</label>
                    <input type="text" name="SCTP_UBR_NUMBER" placeholder="e.g., 20875144" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">HH Code</label>
                    <input type="text" name="HH_CODE" placeholder="e.g., HH12345" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">TA</label>
                    <input type="text" name="TA" placeholder="e.g., Mwenewenya" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cluster</label>
                    <input type="text" name="CLUSTER" placeholder="e.g., Mulembe Chuba" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Zone</label>
                    <input type="text" name="ZONE" placeholder="e.g., Makanthani" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4 md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Household Head Name</label>
                    <input type="text" name="household_head_name" placeholder="e.g., Pearson Mgala" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="submit" class="flex-1 btn-primary py-2 rounded-lg">Add Record</button>
                <button type="button" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 rounded-lg" onclick="toggleModal('addRecordModal')">Cancel</button>
            </div>
        </form>
    </div>

    <!-- Edit Record Modal -->
    <div id="editRecordModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center modal">
        <form id="editRecordForm" class="bg-white p-8 rounded-xl shadow-lg max-w-lg w-full max-h-[80vh] overflow-y-auto" method="post">
            <input type="hidden" name="action" value="edit_record">
            <input type="hidden" id="editRecordId" name="id">
            <h2 class="text-lg font-semibold text-gray-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">edit</span>
                Edit Record
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Form Number</label>
                    <input type="text" id="editFormNumber" name="form_number" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">National ID</label>
                    <input type="text" id="editNationalId" name="national_id" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                    <input type="text" id="editFirstName" name="first_name" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                    <input type="text" id="editLastName" name="last_name" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                    <select id="editGender" name="gender" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Gender</option>
                        <option value="M">M</option>
                        <option value="F">F</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                    <input type="text" id="editProject" name="project" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Traditional Authority</label>
                    <input type="text" id="editTraditionalAuthority" name="traditional_authority" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Group Village Head</label>
                    <input type="text" id="editGroupVillageHead" name="group_village_head" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Village</label>
                    <input type="text" id="editVillage" name="village" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">SCTP UBR Number</label>
                    <input type="text" id="editSctpUbrNumber" name="SCTP_UBR_NUMBER" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">HH Code</label>
                    <input type="text" id="editHhCode" name="HH_CODE" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">TA</label>
                    <input type="text" id="editTA" name="TA" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Cluster</label>
                    <input type="text" id="editCluster" name="CLUSTER" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Zone</label>
                    <input type="text" id="editZone" name="ZONE" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4 md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Household Head Name</label>
                    <input type="text" id="editHouseholdHeadName" name="household_head_name" class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="submit" class="flex-1 btn-primary py-2 rounded-lg">Save Changes</button>
                <button type="button" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 rounded-lg" onclick="toggleModal('editRecordModal')">Cancel</button>
            </div>
        </form>
    </div>

    <!-- View Record Modal -->
    <div id="viewRecordModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center modal">
        <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-lg max-h-[80vh] overflow-y-auto">
            <h2 class="text-lg font-semibold text-gray-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">visibility</span>
                Record Details
            </h2>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Form Number</label>
                    <p id="viewFormNumber" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">National ID</label>
                    <p id="viewNationalId" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <p id="viewName" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Gender</label>
                    <p id="viewGender" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Project</label>
                    <p id="viewProject" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Traditional Authority</label>
                    <p id="viewTraditionalAuthority" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Group Village Head</label>
                    <p id="viewGroupVillageHead" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Village</label>
                    <p id="viewVillage" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">SCTP UBR Number</label>
                    <p id="viewSctpUbrNumber" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">HH Code</label>
                    <p id="viewHhCode" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">TA</label>
                    <p id="viewTA" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Cluster</label>
                    <p id="viewCluster" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Zone</label>
                    <p id="viewZone" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Household Head Name</label>
                    <p id="viewHouseholdHeadName" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Created At</label>
                    <p id="viewCreatedAt" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Verified</label>
                    <p id="viewVerified" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Verified At</label>
                    <p id="viewVerifiedAt" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Verified By</label>
                    <p id="viewVerifiedBy" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Verification Type</label>
                    <p id="viewVerificationType" class="text-sm text-gray-600"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Comment</label>
                    <p id="viewComment" class="text-sm text-gray-600"></p>
                </div>
            </div>
            <div class="flex justify-end mt-6">
                <button class="btn-primary px-4 py-2 rounded-lg" onclick="toggleModal('viewRecordModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center modal">
        <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <span class="material-icons text-blue-600">help_outline</span>
                Confirm Action
            </h2>
            <p id="confirmMessage" class="text-sm text-gray-600 mb-6"></p>
            <div class="flex gap-3">
                <button id="confirmBtn" class="flex-1 btn-primary py-2 rounded-lg">Confirm</button>
                <button class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 rounded-lg" onclick="toggleModal('confirmModal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Verification Confirmation Modal -->
    <div id="verifyConfirmModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center modal">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <span class="material-icons text-blue-600">verified</span>
                    Confirm Verification
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-4" id="verifyDetails"></div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Comment (Optional)</label>
                    <textarea id="verifyComment" rows="3" 
                            class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-500"
                            placeholder="Add verification notes..."></textarea>
                </div>
            </div>
            <div class="p-6 border-t border-gray-100 bg-gray-50 flex justify-end gap-3">
                <button onclick="toggleModal('verifyConfirmModal')" 
                        class="px-4 py-2 text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-100">Cancel</button>
                <button onclick="confirmVerification()" 
                        class="px-6 py-2 btn-primary rounded-lg">Verify Record</button>
            </div>
        </div>
    </div>

    <!-- Unverify Confirmation Modal -->
    <div id="unverifyModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center modal">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <span class="material-icons text-red-600">cancel</span>
                    Confirm Unverification
                </h3>
            </div>
            <div class="p-6">
                <p class="text-sm text-gray-600 mb-4">Are you sure you want to unverify this record? This will remove the verification status and associated verification record.</p>
                <div class="space-y-4" id="unverifyDetails"></div>
            </div>
            <div class="p-6 border-t border-gray-100 bg-gray-50 flex justify-end gap-3">
                <button onclick="toggleModal('unverifyModal')" class="px-4 py-2 text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-100">
                    Cancel
                </button>
                <button onclick="confirmUnverification()" class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">
                    Unverify Record
                </button>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center modal">
        <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full">
            <div id="importModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center modal">
        <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full">
            <h2 class="text-lg font-semibold text-gray-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">upload_file</span>
                Import Records
            </h2>
            <form id="importForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_excel">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Upload Excel File</label>
                    <input type="file" name="excel_file" accept=".xlsx, .xls" required 
                           class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit" class="flex-1 btn-primary py-2 rounded-lg">Import</button>
                    <button type="button" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 rounded-lg" onclick="toggleModal('importModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let html5QrcodeScanner = null;
        let currentPage = <?php echo $page; ?>;
        let totalPages = <?php echo $totalPages; ?>;
        let currentRecordId = null;
        let currentNationalId = null;
        const role = '<?php echo $role; ?>';
        const username = '<?php echo htmlspecialchars($username); ?>';

        // Toggle modal visibility
        function toggleModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.toggle('hidden');
            if (modalId === 'barcodeModal' && !modal.classList.contains('hidden')) {
                startScanner();
            } else if (modalId === 'barcodeModal') {
                stopScanner();
            }
        }

        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const notificationMessage = document.getElementById('notificationMessage');
            const notificationIcon = document.getElementById('notificationIcon');
            notificationMessage.textContent = message;
            notificationIcon.textContent = type === 'success' ? 'check_circle' : 'error';
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg notification ${type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`;
            notification.classList.remove('hidden');
            setTimeout(() => {
                notification.classList.add('hidden');
            }, 3000);
        }

        // Start barcode scanner
        function startScanner() {
            if (html5QrcodeScanner) {
                stopScanner();
            }
            html5QrcodeScanner = new Html5QrcodeScanner(
                "scannerContainer",
                { fps: 10, qrbox: { width: 250, height: 250 }, formatsToSupport: ['CODE_128', 'CODE_39', 'EAN_13', 'UPC_A'] },
                false
            );
            html5QrcodeScanner.render(
                (decodedText) => {
                    document.getElementById('barcodeInput').value = decodedText;
                    verifyBarcode(decodedText);
                },
                (error) => {
                    console.warn(`QR Code scan error: ${error}`);
                }
            );
        }

        // Stop barcode scanner
        function stopScanner() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear();
                html5QrcodeScanner = null;
            }
        }

        // Verify barcode
        function verifyBarcode(barcode) {
            fetch('record_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=scan_barcode&barcode=${encodeURIComponent(barcode)}`
            })
            .then(response => response.json())
            .then(data => {
                const barcodeResult = document.getElementById('barcodeResult');
                const barcodeMessage = document.getElementById('barcodeMessage');
                const barcodeResultIcon = document.getElementById('barcodeResultIcon');
                barcodeResult.classList.remove('hidden');
                barcodeMessage.textContent = data.message;
                barcodeResultIcon.textContent = data.success ? 'check_circle' : 'error';
                barcodeResult.className = `mt-4 text-sm ${data.success ? 'text-green-600' : 'text-red-600'}`;
                if (data.success) {
                    searchRecords();
                    setTimeout(() => toggleModal('barcodeModal'), 1500);
                }
            })
            .catch(error => {
                console.error('Error verifying barcode:', error);
                showNotification('Failed to verify barcode.', 'error');
            });
        }

        // Search records
        function searchRecords(page = 1) {
            const search = document.getElementById('searchRecords').value;
            const status = document.getElementById('statusFilter')?.value || '';
            const ta = document.getElementById('taFilter')?.value || '';
            const gender = document.getElementById('genderFilter')?.value || '';
            const project = document.getElementById('projectFilter')?.value || '';
            const village = document.getElementById('villageFilter')?.value || '';
            const dateFrom = document.getElementById('dateFrom')?.value || '';
            const dateTo = document.getElementById('dateTo')?.value || '';
            const verifiedBy = document.getElementById('verifiedByFilter')?.value || '';
            const verificationType = document.getElementById('verificationTypeFilter')?.value || '';
            const body = `action=search_records&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}&ta=${encodeURIComponent(ta)}&gender=${encodeURIComponent(gender)}&project=${encodeURIComponent(project)}&village=${encodeURIComponent(village)}&dateFrom=${encodeURIComponent(dateFrom)}&dateTo=${encodeURIComponent(dateTo)}&verifiedBy=${encodeURIComponent(verifiedBy)}&verificationType=${encodeURIComponent(verificationType)}&page=${page}`;
            
            fetch('record_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateRecordsTable(data.records, data.totalRecords);
                    currentPage = page;
                    updatePagination(data.totalRecords);
                } else {
                    showNotification(data.message || 'Failed to fetch records.', 'error');
                }
            })
            .catch(error => {
                console.error('Error searching records:', error);
                showNotification('Failed to fetch records.', 'error');
            });
        }

        // Update records table
        function updateRecordsTable(records, totalRecords) {
            const tbody = document.getElementById('recordsTableBody');
            const recordCount = document.getElementById('recordCount');
            tbody.innerHTML = '';
            if (records.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="11" class="py-4 px-6 text-center text-gray-500">
                            No records found
                        </td>
                    </tr>`;
            } else {
                records.forEach(record => {
                    const row = document.createElement('tr');
                    row.className = 'table-row transition-colors';
                    row.dataset.verifiedAt = record.verified_at || '';
                    row.innerHTML = `
                        <td class="py-4 px-6">
                            <span class="inline-flex items-center px-3 py-1 rounded-lg bg-gray-100 text-gray-900 text-sm font-medium">
                                ${record.form_number}
                            </span>
                        </td>
                        <td class="py-4 px-6">
                            <div class="text-sm font-medium text-gray-900">${record.first_name} ${record.last_name}</div>
                        </td>
                        <td class="py-4 px-6">
                            <span class="text-sm text-gray-600">${record.national_id}</span>
                        </td>
                        <td class="py-4 px-6">
                            <span class="text-sm text-gray-600">${record.gender || '-'}</span>
                        </td>
                        <td class="py-4 px-6">
                            <span class="text-sm text-gray-600">${record.project || '-'}</span>
                        </td>
                        <td class="py-4 px-6">
                            <span class="text-sm text-gray-600">${record.traditional_authority || '-'}</span>
                        </td>
                        <td class="py-4 px-6">
                            <span class="text-sm text-gray-600">${record.village || '-'}</span>
                        </td>
                        <td class="py-4 px-6">
                            ${record.status === 'Verified' ? 
                                `<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                    <span class="material-icons text-sm mr-1">check_circle</span> Verified
                                </span>` : 
                                `<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                    <span class="material-icons text-sm mr-1">pending</span> Pending
                                </span>`}
                        </td>
                        <td class="py-4 px-6">
                            <span class="text-sm text-gray-600">${record.verification_type ? record.verification_type.charAt(0).toUpperCase() + record.verification_type.slice(1) : '-'}</span>
                        </td>
                        <td class="py-4 px-6">
                            <span class="text-sm text-gray-600">${record.verified_by || '-'}</span>
                        </td>
                        <td class="py-4 px-6">
                            <div class="flex items-center justify-end gap-2">
                                <button onclick="viewDetails(${record.id})" 
                                        class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100" 
                                        title="View Details">
                                    <span class="material-icons">visibility</span>
                                </button>
                                ${role === 'Admin' ? `
                                    <button onclick="editRecord(${record.id})" 
                                            class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100" 
                                            title="Edit">
                                        <span class="material-icons">edit</span>
                                    </button>
                                    <button onclick="showConfirmModal('delete_record', ${record.id}, 'delete')" 
                                            class="p-2 text-red-400 hover:text-red-600 rounded-lg hover:bg-gray-100" 
                                            title="Delete">
                                        <span class="material-icons">delete</span>
                                    </button>` : ''}
                                ${record.status !== 'Verified' ? `
                                    <button onclick="showVerifyConfirmModal(${record.id}, '${record.national_id}')" 
                                            class="p-2 text-blue-400 hover:text-blue-600 rounded-lg hover:bg-gray-100" 
                                            title="Verify">
                                        <span class="material-icons">verified</span>
                                    </button>` : 
                                    (role === 'Admin' || record.verified_by === username) ? `
                                    <button onclick="showUnverifyModal(${record.id}, '${record.national_id}')" 
                                            class="p-2 text-red-400 hover:text-red-600 rounded-lg hover:bg-gray-100" 
                                            title="Unverify">
                                        <span class="material-icons">cancel</span>
                                    </button>` : ''}
                            </div>
                        </td>`;
                    tbody.appendChild(row);
                });
            }
            recordCount.textContent = `Showing ${records.length} of ${totalRecords} records`;
        }

        // Update pagination
        function updatePagination(totalRecords) {
            totalPages = Math.ceil(totalRecords / <?php echo $recordsPerPage; ?>);
            const pagination = document.querySelector('.p-6.flex.justify-between.items-center.border-t.border-gray-100');
            pagination.innerHTML = `
                <div class="text-sm text-gray-600">
                    Page ${currentPage} of ${totalPages}
                </div>
                <div class="flex gap-2">
                    ${currentPage > 1 ? `<a href="?page=${currentPage - 1}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-100">Previous</a>` : ''}
                    ${currentPage < totalPages ? `<a href="?page=${currentPage + 1}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-100">Next</a>` : ''}
                </div>`;
        }

        // View record details
        function viewDetails(id) {
            fetch('record_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_record&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const record = data.record;
                    document.getElementById('viewFormNumber').textContent = record.form_number || '-';
                    document.getElementById('viewNationalId').textContent = record.national_id || '-';
                    document.getElementById('viewName').textContent = `${record.first_name || ''} ${record.last_name || ''}`.trim() || '-';
                    document.getElementById('viewGender').textContent = record.gender || '-';
                    document.getElementById('viewProject').textContent = record.project || '-';
                    document.getElementById('viewTraditionalAuthority').textContent = record.traditional_authority || '-';
                    document.getElementById('viewGroupVillageHead').textContent = record.group_village_head || '-';
                    document.getElementById('viewVillage').textContent = record.village || '-';
                    document.getElementById('viewSctpUbrNumber').textContent = record.SCTP_UBR_NUMBER || '-';
                    document.getElementById('viewHhCode').textContent = record.HH_CODE || '-';
                    document.getElementById('viewTA').textContent = record.TA || '-';
                    document.getElementById('viewCluster').textContent = record.CLUSTER || '-';
                    document.getElementById('viewZone').textContent = record.ZONE || '-';
                    document.getElementById('viewHouseholdHeadName').textContent = record.household_head_name || '-';
                    document.getElementById('viewCreatedAt').textContent = record.created_at || '-';
                    document.getElementById('viewVerified').textContent = record.status || '-';
                    document.getElementById('viewVerifiedAt').textContent = record.verified_at || '-';
                    document.getElementById('viewVerifiedBy').textContent = record.verified_by || '-';
                    document.getElementById('viewVerificationType').textContent = record.verification_type ? record.verification_type.charAt(0).toUpperCase() + record.verification_type.slice(1) : '-';
                    document.getElementById('viewComment').textContent = record.comment || '-';
                    toggleModal('viewRecordModal');
                } else {
                    showNotification(data.message || 'Failed to fetch record details.', 'error');
                }
            })
            .catch(error => {
                console.error('Error fetching record details:', error);
                showNotification('Failed to fetch record details.', 'error');
            });
        }

        // Edit record
        function editRecord(id) {
            fetch('record_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_record&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const record = data.record;
                    document.getElementById('editRecordId').value = record.id;
                    document.getElementById('editFormNumber').value = record.form_number || '';
                    document.getElementById('editNationalId').value = record.national_id || '';
                    document.getElementById('editFirstName').value = record.first_name || '';
                    document.getElementById('editLastName').value = record.last_name || '';
                    document.getElementById('editGender').value = record.gender || '';
                    document.getElementById('editProject').value = record.project || '';
                    document.getElementById('editTraditionalAuthority').value = record.traditional_authority || '';
                    document.getElementById('editGroupVillageHead').value = record.group_village_head || '';
                    document.getElementById('editVillage').value = record.village || '';
                    document.getElementById('editSctpUbrNumber').value = record.SCTP_UBR_NUMBER || '';
                    document.getElementById('editHhCode').value = record.HH_CODE || '';
                    document.getElementById('editTA').value = record.TA || '';
                    document.getElementById('editCluster').value = record.CLUSTER || '';
                    document.getElementById('editZone').value = record.ZONE || '';
                    document.getElementById('editHouseholdHeadName').value = record.household_head_name || '';
                    toggleModal('editRecordModal');
                } else {
                    showNotification(data.message || 'Failed to fetch record details.', 'error');
                }
            })
            .catch(error => {
                console.error('Error fetching record for edit:', error);
                showNotification('Failed to fetch record details.', 'error');
            });
        }

        // Show confirmation modal
        function showConfirmModal(action, id, type) {
            currentRecordId = id;
            const confirmMessage = document.getElementById('confirmMessage');
            confirmMessage.textContent = `Are you sure you want to ${type} this record?`;
            const confirmBtn = document.getElementById('confirmBtn');
            confirmBtn.onclick = () => {
                if (type === 'delete') {
                    deleteRecord(id);
                }
                toggleModal('confirmModal');
            };
            toggleModal('confirmModal');
        }

        // Delete record
        function deleteRecord(id) {
            fetch('record_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_record&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                showNotification(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    searchRecords(currentPage);
                }
            })
            .catch(error => {
                console.error('Error deleting record:', error);
                showNotification('Failed to delete record.', 'error');
            });
        }

        // Show verification confirmation modal
        function showVerifyConfirmModal(id, nationalId) {
            currentRecordId = id;
            currentNationalId = nationalId;
            fetch('record_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_record&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const record = data.record;
                    document.getElementById('verifyDetails').innerHTML = `
                        <div>
                            <span class="font-medium text-gray-700">Form Number:</span>
                            <span class="text-gray-600">${record.form_number || '-'}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Name:</span>
                            <span class="text-gray-600">${record.first_name || ''} ${record.last_name || ''}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">National ID:</span>
                            <span class="text-gray-600">${record.national_id || '-'}</span>
                        </div>`;
                    document.getElementById('verifyComment').value = '';
                    toggleModal('verifyConfirmModal');
                } else {
                    showNotification(data.message || 'Failed to fetch record details.', 'error');
                }
            })
            .catch(error => {
                console.error('Error fetching record for verification:', error);
                showNotification('Failed to fetch record details.', 'error');
            });
        }

        // Confirm verification
        function confirmVerification() {
            const comment = document.getElementById('verifyComment').value;
            fetch('record_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=verify_record&id=${currentRecordId}&comment=${encodeURIComponent(comment)}&verification_type=manual`
            })
            .then(response => response.json())
            .then(data => {
                showNotification(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    toggleModal('verifyConfirmModal');
                    searchRecords(currentPage);
                }
            })
            .catch(error => {
                console.error('Error verifying record:', error);
                showNotification('Failed to verify record.', 'error');
            });
        }

        // Show unverification modal
        function showUnverifyModal(id, nationalId) {
            currentRecordId = id;
            currentNationalId = nationalId;
            fetch('record_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_record&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const record = data.record;
                    document.getElementById('unverifyDetails').innerHTML = `
                        <div>
                            <span class="font-medium text-gray-700">Form Number:</span>
                            <span class="text-gray-600">${record.form_number || '-'}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Name:</span>
                            <span class="text-gray-600">${record.first_name || ''} ${record.last_name || ''}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">National ID:</span>
                            <span class="text-gray-600">${record.national_id || '-'}</span>
                        </div>`;
                    toggleModal('unverifyModal');
                } else {
                    showNotification(data.message || 'Failed to fetch record details.', 'error');
                }
            })
            .catch(error => {
                console.error('Error fetching record for unverification:', error);
                showNotification('Failed to fetch record details.', 'error');
            });
        }

        // Confirm unverification
        function confirmUnverification() {
            fetch('record_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=unverify_record&id=${currentRecordId}`
            })
            .then(response => response.json())
            .then(data => {
                showNotification(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    toggleModal('unverifyModal');
                    searchRecords(currentPage);
                }
            })
            .catch(error => {
                console.error('Error unverifying record:', error);
                showNotification('Failed to unverify record.', 'error');
            });
        }

        // Export records
        function exportRecords() {
            const search = document.getElementById('searchRecords').value;
            const status = document.getElementById('statusFilter')?.value || '';
            const ta = document.getElementById('taFilter')?.value || '';
            const gender = document.getElementById('genderFilter')?.value || '';
            const project = document.getElementById('projectFilter')?.value || '';
            const village = document.getElementById('villageFilter')?.value || '';
            const dateFrom = document.getElementById('dateFrom')?.value || '';
            const dateTo = document.getElementById('dateTo')?.value || '';
            const verifiedBy = document.getElementById('verifiedByFilter')?.value || '';
            const verificationType = document.getElementById('verificationTypeFilter')?.value || '';
            fetch('record_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=search_records&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}&ta=${encodeURIComponent(ta)}&gender=${encodeURIComponent(gender)}&project=${encodeURIComponent(project)}&village=${encodeURIComponent(village)}&dateFrom=${encodeURIComponent(dateFrom)}&dateTo=${encodeURIComponent(dateTo)}&verifiedBy=${encodeURIComponent(verifiedBy)}&verificationType=${encodeURIComponent(verificationType)}&export=true`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const records = data.records;
                    const headers = [
                        'Form Number', 'National ID', 'First Name', 'Last Name', 'Gender', 'Project',
                        'Traditional Authority', 'Group Village Head', 'Village', 'SCTP UBR Number',
                        'HH Code', 'TA', 'Cluster', 'Zone', 'Household Head Name', 'Status',
                        'Verified By', 'Verification Type', 'Verified At', 'Created At'
                    ];
                    let csvContent = headers.join(',') + '\n';
                    records.forEach(record => {
                        const row = [
                            `"${record.form_number || ''}"`,
                            `"${record.national_id || ''}"`,
                            `"${record.first_name || ''}"`,
                            `"${record.last_name || ''}"`,
                            `"${record.gender || ''}"`,
                            `"${record.project || ''}"`,
                            `"${record.traditional_authority || ''}"`,
                            `"${record.group_village_head || ''}"`,
                            `"${record.village || ''}"`,
                            `"${record.SCTP_UBR_NUMBER || ''}"`,
                            `"${record.HH_CODE || ''}"`,
                            `"${record.TA || ''}"`,
                            `"${record.CLUSTER || ''}"`,
                            `"${record.ZONE || ''}"`,
                            `"${record.household_head_name || ''}"`,
                            `"${record.status || ''}"`,
                            `"${record.verified_by || ''}"`,
                            `"${record.verification_type || ''}"`,
                            `"${record.verified_at || ''}"`,
                            `"${record.created_at || ''}"`
                        ];
                        csvContent += row.join(',') + '\n';
                    });
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = 'records_export.csv';
                    link.click();
                    URL.revokeObjectURL(link.href);
                } else {
                    showNotification(data.message || 'Failed to export records.', 'error');
                }
            })
            .catch(error => {
                console.error('Error exporting records:', error);
                showNotification('Failed to export records.', 'error');
            });
        }

        // Toggle advanced filters
        document.getElementById('advancedFiltersBtn').addEventListener('click', () => {
            const advancedFilters = document.getElementById('advancedFilters');
            advancedFilters.classList.toggle('hidden');
        });

        // Apply filters
        function applyFilters() {
            searchRecords(1);
            document.getElementById('advancedFilters').classList.add('hidden');
        }

        // Clear filters
        function clearFilters() {
            document.getElementById('searchRecords').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('taFilter').value = '';
            document.getElementById('genderFilter').value = '';
            document.getElementById('projectFilter').value = '';
            document.getElementById('villageFilter').value = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            document.getElementById('verifiedByFilter').value = '';
            document.getElementById('verificationTypeFilter').value = '';
            searchRecords(1);
            document.getElementById('advancedFilters').classList.add('hidden');
        }

        // Handle form submissions
        document.getElementById('addRecordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('record_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showNotification(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    toggleModal('addRecordModal');
                    searchRecords(currentPage);
                }
            })
            .catch(error => {
                console.error('Error adding record:', error);
                showNotification('Failed to add record.', 'error');
            });
        });

        document.getElementById('editRecordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('record_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showNotification(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    toggleModal('editRecordModal');
                    searchRecords(currentPage);
                }
            })
            .catch(error => {
                console.error('Error editing record:', error);
                showNotification('Failed to edit record.', 'error');
            });
        });

        document.getElementById('importForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('record_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showNotification(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    toggleModal('importModal');
                    searchRecords(currentPage);
                }
            })
            .catch(error => {
                console.error('Error importing records:', error);
                showNotification('Failed to import records.', 'error');
            });
        });

        document.getElementById('manualVerifyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const nationalId = document.getElementById('manualNationalId').value;
            const comment = this.querySelector('textarea[name="comment"]').value;
            fetch('record_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_record&national_id=${encodeURIComponent(nationalId)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentRecordId = data.record.id;
                    currentNationalId = nationalId;
                    showVerifyConfirmModal(data.record.id, nationalId);
                    toggleModal('manualVerifyModal');
                } else {
                    showNotification(data.message || 'No record found for the provided National ID.', 'error');
                }
            })
            .catch(error => {
                console.error('Error fetching record for manual verification:', error);
                showNotification('Failed to fetch record.', 'error');
            });
        });

        document.getElementById('barcodeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const barcode = document.getElementById('barcodeInput').value;
            if (barcode) {
                verifyBarcode(barcode);
            }
        });

        // Handle search input
        document.getElementById('searchRecords').addEventListener('input', () => {
            searchRecords(1);
        });

        // Toggle sidebar on mobile
        document.getElementById('menuBtn').addEventListener('click', () => {
            const sidebar = document.querySelector('aside');
            sidebar.classList.toggle('-translate-x-full');
            sidebar.classList.toggle('animate-slide-in');
        });

        // Initialize search on page load
        searchRecords(currentPage);
    </script>
</body>
</html>
