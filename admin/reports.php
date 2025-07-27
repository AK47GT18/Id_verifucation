<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Admin') {
    header("Location: /id_verification/login.php");
    exit;
}

$username = $_SESSION['username'];

// Fetch real statistics from database
$statsQuery = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE disabled = 0) as total_users,
        (SELECT COUNT(*) FROM records) as total_records,
        (SELECT COUNT(*) FROM records WHERE status = 'Verified') as verified_records
    FROM dual";

$result = $conn->query($statsQuery);
$stats = $result->fetch_assoc();

$totalUsers = $stats['total_users'];
$totalRecords = $stats['total_records'];
$verifiedUsers = $stats['verified_records'];
$unverifiedUsers = $totalRecords - $verifiedUsers;

// Fetch real user verifications from database
$verificationQuery = "
    SELECT 
        u.username,
        COUNT(v.id) as verified_count,
        MAX(v.verified_at) as last_verification
    FROM users u
    LEFT JOIN verifications v ON u.id = v.verified_by
    WHERE u.role = 'Verifier' AND u.disabled = 0
    GROUP BY u.id
    ORDER BY verified_count DESC";

$result = $conn->query($verificationQuery);
$userVerifications = [];
while ($row = $result->fetch_assoc()) {
    $userVerifications[] = [
        'username' => $row['username'],
        'verified_count' => (int)$row['verified_count'],
        'last_verification' => $row['last_verification']
    ];
}

// Get unique users for filter dropdown
$usersQuery = "SELECT DISTINCT username FROM users WHERE role = 'Verifier' AND disabled = 0 ORDER BY username";
$result = $conn->query($usersQuery);
$verifiers = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            border-color: #3B82F6; /* Blue accent */
        }
        .btn-primary {
            background-color: #3B82F6; /* Blue-500 */
            color: white;
        }
        .btn-primary:hover {
            background-color: #2563EB; /* Blue-600 */
        }
        .table-row:hover {
            background-color: #F9FAFB; /* Gray-50 */
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
                    <div class="bg-blue-50 p-2 rounded-lg">
                        <span class="material-icons text-2xl text-blue-600">admin_panel_settings</span>
                    </div>
                    <div>
                        <div class="font-bold text-gray-900">Admin Panel</div>
                        <div class="text-sm text-gray-500">Management System</div>
                    </div>
                </div>
                
                <!-- Navigation -->
                <nav class="flex-1 p-4 space-y-2">
                    <a href="index.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors <?php if(basename($_SERVER['PHP_SELF'])=='index.php') echo 'bg-blue-50 text-blue-600 font-medium'; ?>">
                        <span class="material-icons text-lg">dashboard</span>
                        Dashboard
                    </a>
                    <a href="user_management.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors <?php if(basename($_SERVER['PHP_SELF'])=='user_management.php') echo 'bg-blue-50 text-blue-600 font-medium'; ?>">
                        <span class="material-icons text-lg">group</span>
                        User Management
                    </a>
                    <a href="record_management.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors <?php if(basename($_SERVER['PHP_SELF'])=='record_management.php') echo 'bg-blue-50 text-blue-600 font-medium'; ?>">
                        <span class="material-icons text-lg">folder</span>
                        Record Management
                    </a>
                    <a href="reports.php" class="flex items-center gap-3 p-3 rounded-lg bg-blue-50 text-blue-600 font-medium">
                        <span class="material-icons text-lg">bar_chart</span>
                        Reports
                    </a>
                    <a href="configurations.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors <?php if(basename($_SERVER['PHP_SELF'])=='configurations.php') echo 'bg-blue-50 text-blue-600 font-medium'; ?>">
                        <span class="material-icons text-lg">settings</span>
                        Configurations
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
                            <a href="/id_verification/auth/logout.php" class="text-xs text-red-500 hover:text-red-600">Sign out</a>
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
                        <h1 class="text-xl font-semibold text-gray-900">Reports</h1>
                    </div>
                    <div class="flex items-center gap-3">
                        <button id="advancedFiltersBtn" class="flex items-center gap-2 px-4 py-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                            <span class="material-icons">tune</span>
                            Filters
                        </button>
                        <button class="btn-primary px-4 py-2 rounded flex items-center gap-2" onclick="exportReport()">
                            <span class="material-icons">download</span>
                            Export Report
                        </button>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-6 bg-gray-50">
                <div class="max-w-7xl mx-auto">
                    <!-- Filters Panel -->
                    <div id="advancedFilters" class="hidden bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
                        <div class="p-6 border-b border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                <span class="material-icons text-blue-600">tune</span>
                                Report Filters
                            </h2>
                        </div>
                        <div class="p-6 bg-gray-50 rounded-b-xl">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                                    <div class="grid grid-cols-2 gap-4">
                                        <input type="date" id="dateFrom" class="w-full px-4 py-2 rounded-lg border border-gray-300">
                                        <input type="date" id="dateTo" class="w-full px-4 py-2 rounded-lg border border-gray-300">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">User</label>
                                    <select id="userFilter" class="w-full px-4 py-2 rounded-lg border border-gray-300">
                                        <option value="">All Users</option>
                                        <?php foreach($verifiers as $verifier): ?>
                                            <option value="<?php echo htmlspecialchars($verifier['username']); ?>">
                                                <?php echo htmlspecialchars($verifier['username']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Verification Status</label>
                                    <select id="statusFilter" class="w-full px-4 py-2 rounded-lg border border-gray-300">
                                        <option value="">All Statuses</option>
                                        <option value="verified">Verified</option>
                                        <option value="unverified">Unverified</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Report Type</label>
                                    <select id="reportType" class="w-full px-4 py-2 rounded-lg border border-gray-300">
                                        <option value="full">Full Report</option>
                                        <option value="verified">Verified Records</option>
                                        <option value="unverified">Unverified Records</option>
                                        <option value="verifiers">Verifier Performance</option>
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

                    <!-- Statistics Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Total Users</p>
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1" id="totalUsers"><?php echo $totalUsers; ?></h3>
                                </div>
                                <div class="bg-blue-50 p-3 rounded-xl">
                                    <span class="material-icons text-2xl text-blue-600">group</span>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center text-sm text-gray-500">
                                <span class="material-icons text-base mr-1">history</span>
                                Updated: <?php echo date('Y-m-d H:i:s'); ?>
                            </div>
                        </div>
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
                                    <p class="text-sm font-medium text-gray-500">Verified Users</p>
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $verifiedUsers; ?></h3>
                                </div>
                                <div class="bg-blue-50 p-3 rounded-xl">
                                    <span class="material-icons text-2xl text-blue-600">verified</span>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center text-sm text-blue-600">
                                <span class="material-icons text-base mr-1">trending_up</span>
                                <?php echo round(($verifiedUsers/$totalUsers) * 100); ?>% verified
                            </div>
                        </div>
                    </div>

                    <!-- Charts -->
                    <div class="grid md:grid-cols-2 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <span class="material-icons text-blue-600">pie_chart</span>
                                Verified vs Unverified Users
                            </h2>
                            <canvas id="verifiedPie"></canvas>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <span class="material-icons text-blue-600">bar_chart</span>
                                Records Verified by User
                            </h2>
                            <canvas id="userBar"></canvas>
                        </div>
                    </div>

                    <!-- User Verifications Table -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                <span class="material-icons text-blue-600">table_chart</span>
                                User Verification Summary
                            </h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-200">
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Username</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Records Verified</th>
                                        <th class="text-right py-4 px-6 text-sm font-semibold text-gray-900">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach($userVerifications as $uv): ?>
                                    <tr class="table-row transition-colors">
                                        <td class="py-4 px-6">
                                            <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($uv['username']); ?></span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="text-sm text-gray-600"><?php echo $uv['verified_count']; ?></span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <div class="flex items-center justify-end gap-2">
                                                <button onclick="viewUserDetails('<?php echo htmlspecialchars($uv['username']); ?>')" 
                                                        class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
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

    <script>
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

        // Toggle filters panel
        document.getElementById('advancedFiltersBtn').addEventListener('click', function() {
            document.getElementById('advancedFilters').classList.toggle('hidden');
        });

        // Chart data (initial)
        let pieChartData = {
            labels: ['Verified', 'Unverified'],
            datasets: [{
                data: [<?php echo $verifiedUsers; ?>, <?php echo $unverifiedUsers; ?>],
                backgroundColor: ['#3B82F6', '#D1D5DB'],
                borderColor: ['#FFFFFF', '#FFFFFF'],
                borderWidth: 2
            }]
        };

        let barChartData = {
            labels: <?php echo json_encode(array_column($userVerifications, 'username')); ?>,
            datasets: [{
                label: 'Records Verified',
                data: <?php echo json_encode(array_column($userVerifications, 'verified_count')); ?>,
                backgroundColor: '#3B82F6',
                borderColor: '#2563EB',
                borderWidth: 1
            }]
        };

        // Initialize charts
        const pieChart = new Chart(document.getElementById('verifiedPie'), {
            type: 'pie',
            data: pieChartData,
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 14 } } },
                    tooltip: { enabled: true }
                }
            }
        });

        const barChart = new Chart(document.getElementById('userBar'), {
            type: 'bar',
            data: barChartData,
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: true }
                },
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Records Verified' } },
                    x: { title: { display: true, text: 'Users' } }
                }
            }
        });

        // Filter functionality (placeholder for server-side integration)
        function applyFilters() {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const user = document.getElementById('userFilter').value;
            const status = document.getElementById('statusFilter').value;
            const reportType = document.getElementById('reportType').value;

            // Example: Update charts (in a real app, fetch filtered data from server)
            if (user) {
                barChartData.datasets[0].data = barChartData.datasets[0].data.map((count, i) => 
                    barChartData.labels[i] === user ? count : 0
                );
                barChart.update();
            }
            console.log('Filters applied:', { dateFrom, dateTo, user, status, reportType });
            // Implement server-side filtering here
        }

        document.querySelectorAll('#dateFrom, #dateTo, #userFilter, #statusFilter').forEach(element => {
            element.addEventListener('change', updateReportData);
        });

        function clearFilters() {
            const inputs = ['dateFrom', 'dateTo', 'userFilter', 'statusFilter', 'reportType'];
            inputs.forEach(id => document.getElementById(id).value = '');
            updateReportData(); // Fetch fresh data after clearing filters
        }

        // Export report (placeholder)
        function exportReport() {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const user = document.getElementById('userFilter').value;
            const type = document.getElementById('reportType').value || 'full';
            
            const params = new URLSearchParams({
                type: type,
                format: 'csv',
                dateFrom: dateFrom,
                dateTo: dateTo,
                user: user
            });
            
            window.location.href = `export_report.php?${params.toString()}`;
        }

        // View user details (placeholder)
        function viewUserDetails(username) {
            console.log('Viewing details for user:', username);
            // Implement modal or redirect to user details page
        }

        // Update report data (AJAX)
        function updateReportData() {
            const filters = {
                dateFrom: document.getElementById('dateFrom').value,
                dateTo: document.getElementById('dateTo').value,
                user: document.getElementById('userFilter').value,
                status: document.getElementById('statusFilter').value
            };

            fetch('fetch_report_data.php?' + new URLSearchParams(filters))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update statistics
                        document.getElementById('totalUsers').textContent = data.stats.total_users;
                        document.getElementById('totalRecords').textContent = data.stats.total_records;
                        document.getElementById('verifiedRecords').textContent = data.stats.verified_records;

                        // Update charts
                        pieChart.data.datasets[0].data = [
                            data.stats.verified_records,
                            data.stats.total_records - data.stats.verified_records
                        ];
                        pieChart.update();

                        barChart.data.labels = data.userVerifications.map(u => u.username);
                        barChart.data.datasets[0].data = data.userVerifications.map(u => u.verified_count);
                        barChart.update();

                        // Update table
                        const tbody = document.querySelector('tbody');
                        tbody.innerHTML = data.userVerifications.map(user => `
                            <tr class="table-row transition-colors">
                                <td class="py-4 px-6">
                                    <span class="text-sm font-medium text-gray-900">${user.username}</span>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="text-sm text-gray-600">${user.verified_count}</span>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex items-center justify-end gap-2">
                                        <button onclick="viewUserDetails('${user.username}')" 
                                                class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                                            <span class="material-icons">visibility</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `).join('');
                    }
                })
                .catch(error => console.error('Error updating report:', error));
        }

        // Call updateReportData immediately and every 5 minutes
        updateReportData();
        setInterval(updateReportData, 300000);
    </script>
</body>
</html>