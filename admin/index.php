<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Admin') {
    header("Location: /id_verification/login.php");
    exit;
}

$username = $_SESSION['username'];

// Example activity data - In production, fetch from database
$recentActivity = [
    [
        'type' => 'verified',
        'record_id' => '20875144',
        'time' => '5 min ago',
        'icon' => 'check_circle',
        'icon_color' => 'text-blue-600'
    ],
    [
        'type' => 'user_added',
        'record_id' => 'jane',
        'time' => '30 min ago',
        'icon' => 'person_add',
        'icon_color' => 'text-blue-600'
    ],
    [
        'type' => 'record_deleted',
        'record_id' => '20875148',
        'time' => '1 hour ago',
        'icon' => 'delete',
        'icon_color' => 'text-red-600'
    ]
];

// Example statistics - In production, fetch from database
$stats = [
    'total_users' => 24,
    'total_records' => 120,
    'verified_records' => 85
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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
        .sidebar {
            background: #FFFFFF; /* White primary color */
        }
        .btn-primary {
            background-color: #3B82F6; /* Blue-500 */
            color: white;
        }
        .btn-primary:hover {
            background-color: #2563EB; /* Blue-600 */
        }
        .card {
            transition: transform 0.2s, border-color 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
            border-color: #3B82F6; /* Blue accent on hover */
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg fixed h-full transition-transform duration-300 ease-in-out transform -translate-x-full md:translate-x-0 z-40">
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
            <nav class="p-4 space-y-2">
                <a href="index.php" class="flex items-center gap-3 p-3 rounded-lg bg-blue-50 text-blue-600 font-medium">
                    <span class="material-icons">dashboard</span>
                    Dashboard
                </a>
                <a href="user_management.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors">
                    <span class="material-icons">group</span>
                    User Management
                </a>
                <a href="record_management.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors">
                    <span class="material-icons">folder</span>
                    Record Management
                </a>
                <a href="reports.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors">
                    <span class="material-icons">bar_chart</span>
                    Reports
                </a>
                <a href="configurations.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors">
                    <span class="material-icons">settings</span>
                    Configurations
                </a>
            </nav>
            
            <!-- User Section -->
            <div class="absolute bottom-0 left-0 right-0 border-t border-gray-100 p-4">
                <div class="flex items-center gap-3 p-2">
                    <div class="bg-gray-100 p-2 rounded-full">
                        <span class="material-icons text-gray-600">person</span>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($username); ?></div>
                        <a href="/auth/logout.php" class="text-xs text-red-500 hover:text-red-600">Sign out</a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 md:ml-64">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b sticky top-0 z-30">
                <div class="flex items-center justify-between p-4">
                    <div class="flex items-center gap-4">
                        <button id="menuBtn" class="md:hidden p-2 rounded-lg hover:bg-gray-100">
                            <span class="material-icons text-gray-600">menu</span>
                        </button>
                        <h1 class="text-xl font-semibold text-gray-900">Admin Dashboard</h1>
                    </div>
                    <a href="record_management.php" class="btn-primary px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
                        <span class="material-icons text-sm">folder</span>
                        Manage Records
                    </a>
                </div>
            </header>

            <!-- Main Content Area -->
            <div class="p-6">
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Total Users</p>
                                <h3 class="text-3xl font-bold text-gray-900 mt-1" id="totalUsers">-</h3>
                            </div>
                            <div class="bg-blue-50 p-3 rounded-xl">
                                <span class="material-icons text-2xl text-blue-600">group</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Total Records</p>
                                <h3 class="text-3xl font-bold text-gray-900 mt-1" id="totalRecords">-</h3>
                            </div>
                            <div class="bg-blue-50 p-3 rounded-xl">
                                <span class="material-icons text-2xl text-blue-600">folder</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Verified Records</p>
                                <h3 class="text-3xl font-bold text-gray-900 mt-1" id="verifiedRecords">-</h3>
                            </div>
                            <div class="bg-blue-50 p-3 rounded-xl">
                                <span class="material-icons text-2xl text-blue-600">verified</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border border-gray-200">
                    <div class="font-semibold text-lg mb-4 flex items-center gap-2 text-gray-900">
                        <span class="material-icons text-blue-600">flash_on</span>
                        Quick Actions
                    </div>
                    <div class="flex flex-wrap gap-4">
                        <a href="user_management.php" class="btn-primary px-4 py-2 rounded flex items-center gap-2">
                            <span class="material-icons">group</span> Manage Users
                        </a>
                        <a href="record_management.php" class="btn-primary px-4 py-2 rounded flex items-center gap-2">
                            <span class="material-icons">folder</span> Manage Records
                        </a>
                        <a href="reports.php" class="btn-primary px-4 py-2 rounded flex items-center gap-2">
                            <span class="material-icons">bar_chart</span> View Reports
                        </a>
                        <a href="configurations.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-4 py-2 rounded flex items-center gap-2">
                            <span class="material-icons">settings</span> Configurations
                        </a>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                            <span class="material-icons text-blue-600">history</span>
                            Recent Activity
                        </h2>
                    </div>
                    <div class="p-6">
                        <ul id="activityList" class="divide-y divide-gray-100">
                            <li class="py-3 px-3 text-gray-500 text-center">Loading...</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>

    <script>
        // Mobile menu functionality
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

        function updateDashboard() {
            fetch('fetch_dashboard_data.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update statistics
                        document.getElementById('totalUsers').textContent = data.stats.total_users;
                        document.getElementById('totalRecords').textContent = data.stats.total_records;
                        document.getElementById('verifiedRecords').textContent = data.stats.verified_records;

                        // Update activity list
                        const activityList = document.getElementById('activityList');
                        activityList.innerHTML = data.recentActivity.map(activity => `
                            <li class="py-3 flex items-center gap-3 hover:bg-gray-50 px-3 rounded-lg transition-colors">
                                <span class="material-icons ${activity.icon_color}">
                                    ${activity.icon}
                                </span>
                                ${activity.type === 'verified' ? 'Verified record' : 'Added user'}
                                <b class="mx-1">${activity.record_id}</b>
                                <span class="ml-auto text-xs text-gray-400">${activity.time}</span>
                            </li>
                        `).join('');
                    }
                })
                .catch(error => {
                    console.error('Error fetching dashboard data:', error);
                });
        }

        // Update dashboard immediately and every 30 seconds
        updateDashboard();
        setInterval(updateDashboard, 30000);
    </script>
</body>
</html>