<?php
session_start();
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/user_manager.php';

// Restrict access to Admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: /id_verification/record_management.php");
    exit;
}

$username = $_SESSION['username'] ?? 'admin';
$userManager = new UserManager($conn);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $result = $userManager->addUser(
            trim($_POST['username']),
            trim($_POST['password']),
            trim($_POST['role']),
            trim($_POST['first_name']),
            trim($_POST['last_name'])
        );
        $response = $result;
    } elseif ($action === 'edit_user') {
        $result = $userManager->updateUser(
            trim($_POST['username']),
            trim($_POST['role']),
            trim($_POST['first_name']),
            trim($_POST['last_name'])
        );
        $response = $result;
    } elseif ($action === 'toggle_status') {
        $result = $userManager->toggleUserStatus(trim($_POST['username']));
        $response = $result;
    } elseif ($action === 'delete_user') {
        $result = $userManager->deleteUser(trim($_POST['username']));
        $response = $result;
    } elseif ($action === 'reset_password') {
        $result = $userManager->resetPassword(trim($_POST['username']), trim($_POST['new_password']));
        $response = $result;
    } elseif ($action === 'get_user') {
        $user = $userManager->getUserDetails(trim($_POST['username']));
        $response = $user ? ['success' => true, 'user' => $user] : ['success' => false, 'message' => 'User not found.'];
    }

    if (isset($response)) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Fetch users
$users = $userManager->getUsers();
$totalUsers = count($users);
$enabledUsers = count(array_filter($users, fn($u) => $u['status'] === 'Enabled'));
$disabledUsers = $totalUsers - $enabledUsers;

// Roles for dropdown (aligned with users table ENUM)
$roles = ['Admin', 'Verifier'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - ID Verification Portal</title>
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
                        <div class="font-bold text-gray-900">Admin Panel</div>
                        <div class="text-sm text-gray-500">ID Verification Portal</div>
                    </div>
                </div>
                <nav class="flex-1 p-4 space-y-2">
                    <a href="index.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors <?php if(basename($_SERVER['PHP_SELF'])=='index.php') echo 'bg-blue-50 text-blue-600 font-medium'; ?>">
                        <span class="material-icons text-lg">dashboard</span>
                        Dashboard
                    </a>
                    <a href="user_management.php" class="flex items-center gap-3 p-3 rounded-lg bg-blue-50 text-blue-600 font-medium">
                        <span class="material-icons text-lg">group</span>
                        User Management
                    </a>
                    <a href="record_management.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors <?php if(basename($_SERVER['PHP_SELF'])=='record_management.php') echo 'bg-blue-50 text-blue-600 font-medium'; ?>">
                        <span class="material-icons text-lg">folder</span>
                        Record Management
                    </a>
                    <a href="reports.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors <?php if(basename($_SERVER['PHP_SELF'])=='reports.php') echo 'bg-blue-50 text-blue-600 font-medium'; ?>">
                        <span class="material-icons text-lg">bar_chart</span>
                        Reports
                    </a>
                    <a href="configurations.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors <?php if(basename($_SERVER['PHP_SELF'])=='configurations.php') echo 'bg-blue-50 text-blue-600 font-medium'; ?>">
                        <span class="material-icons text-lg">settings</span>
                        Configurations
                    </a>
                </nav>
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
                        <h1 class="text-xl font-semibold text-gray-900">User Management</h1>
                    </div>
                    <div class="flex items-center gap-3">
                        <button id="advancedFiltersBtn" class="flex items-center gap-2 px-4 py-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                            <span class="material-icons">tune</span>
                            Filters
                        </button>
                        <button class="btn-primary px-4 py-2 rounded-lg flex items-center gap-2 transition-colors" onclick="toggleModal('addUserModal')">
                            <span class="material-icons text-sm">person_add</span>
                            Add User
                        </button>
                    </div>
                </div>
                <div class="px-4 pb-4">
                    <div class="flex flex-col md:flex-row md:items-center gap-4">
                        <div class="flex-1 w-full">
                            <div class="relative">
                                <input id="searchUsers" type="text" placeholder="Search by username..." class="w-full pl-12 pr-4 py-3 text-base rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <span class="material-icons absolute left-4 top-3.5 text-gray-400">search</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <select id="roleFilter" class="px-4 py-3 rounded-xl border border-gray-200 text-base focus:ring-2 focus:ring-blue-500">
                                <option value="">All Roles</option>
                                <?php foreach($roles as $role): ?>
                                <option value="<?php echo htmlspecialchars($role); ?>"><?php echo htmlspecialchars($role); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="statusFilter" class="px-4 py-3 rounded-xl border border-gray-200 text-base focus:ring-2 focus:ring-blue-500">
                                <option value="">All Statuses</option>
                                <option value="Enabled">Enabled</option>
                                <option value="Disabled">Disabled</option>
                            </select>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-6 bg-gray-50">
                <div class="max-w-7xl mx-auto">
                    <!-- Statistics Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Total Users</p>
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $totalUsers; ?></h3>
                                </div>
                                <div class="bg-blue-50 p-3 rounded-xl">
                                    <span class="material-icons text-2xl text-blue-600">group</span>
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
                                    <p class="text-sm font-medium text-gray-500">Enabled Users</p>
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $enabledUsers; ?></h3>
                                </div>
                                <div class="bg-blue-50 p-3 rounded-xl">
                                    <span class="material-icons text-2xl text-blue-600">check_circle</span>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center text-sm text-blue-600">
                                <span class="material-icons text-base mr-1">trending_up</span>
                                <?php echo $totalUsers ? round(($enabledUsers/$totalUsers) * 100) : 0; ?>% active
                            </div>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Disabled Users</p>
                                    <h3 class="text-3xl font-bold text-gray-900 mt-1"><?php echo $disabledUsers; ?></h3>
                                </div>
                                <div class="bg-orange-50 p-3 rounded-xl">
                                    <span class="material-icons text-2xl text-orange-600">block</span>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center text-sm text-orange-600">
                                <span class="material-icons text-base mr-1">warning</span>
                                Inactive accounts
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Filters Panel -->
                    <div id="advancedFilters" class="hidden bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
                        <div class="p-6 border-b border-gray-100">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                <span class="material-icons text-blue-600">tune</span>
                                Advanced Filters
                            </h2>
                        </div>
                        <div class="p-6 bg-gray-50 rounded-b-xl">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Last Login Date Range</label>
                                    <div class="grid grid-cols-2 gap-4">
                                        <input type="date" id="loginDateFrom" class="w-full px-4 py-2 rounded-lg border border-gray-200">
                                        <input type="date" id="loginDateTo" class="w-full px-4 py-2 rounded-lg border border-gray-200">
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

                    <!-- Users Table -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div class="p-6 border-b border-gray-100">
                            <div class="flex items-center justify-between">
                                <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                    <span class="material-icons text-blue-600">group</span>
                                    Users
                                </h2>
                                <span id="userCount" class="text-sm text-gray-600">
                                    Showing <?php echo $totalUsers; ?> of <?php echo $totalUsers; ?> users
                                </span>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-200">
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Username</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Role</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Status</th>
                                        <th class="text-left py-4 px-6 text-sm font-semibold text-gray-900">Last Login</th>
                                        <th class="text-right py-4 px-6 text-sm font-semibold text-gray-900">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach($users as $user): ?>
                                    <tr class="table-row transition-colors">
                                        <td class="py-4 px-6">
                                            <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['username']); ?></span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="text-sm text-gray-600"><?php echo htmlspecialchars($user['role']); ?></span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <?php if($user['status'] === 'Enabled'): ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                                <span class="material-icons text-base mr-1">check_circle</span> Enabled
                                            </span>
                                            <?php else: ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                                <span class="material-icons text-base mr-1">block</span> Disabled
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-6">
                                            <span class="text-sm text-gray-600">
                                                <?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : '-'; ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-6">
                                            <div class="flex items-center justify-end gap-2">
                                                <button onclick="showConfirmModal('toggle_status', '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo $user['status'] === 'Enabled' ? 'disable' : 'enable'; ?>')"
                                                        class="p-2 <?php echo $user['status'] === 'Enabled' ? 'text-orange-400 hover:text-orange-600' : 'text-blue-400 hover:text-blue-600'; ?> rounded-lg hover:bg-gray-100"
                                                        title="<?php echo $user['status'] === 'Enabled' ? 'Disable' : 'Enable'; ?>">
                                                    <span class="material-icons"><?php echo $user['status'] === 'Enabled' ? 'block' : 'check_circle'; ?></span>
                                                </button>
                                                <button onclick="editUser('<?php echo htmlspecialchars($user['username']); ?>')"
                                                        class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
                                                        title="Edit">
                                                    <span class="material-icons">edit</span>
                                                </button>
                                                <button onclick="showConfirmModal('delete_user', '<?php echo htmlspecialchars($user['username']); ?>', 'delete')"
                                                        class="p-2 text-red-400 hover:text-red-600 rounded-lg hover:bg-gray-100"
                                                        title="Delete">
                                                    <span class="material-icons">delete</span>
                                                </button>
                                                <button onclick="showResetPasswordModal('<?php echo htmlspecialchars($user['username']); ?>')"
                                                        class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
                                                        title="Reset Password">
                                                    <span class="material-icons">lock_reset</span>
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

    <!-- Add User Modal -->
    <div id="addUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center modal">
        <form id="addUserForm" class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full" method="post">
            <input type="hidden" name="action" value="add_user">
            <h2 class="text-lg font-semibold text-gray-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">person_add</span>
                Add User
            </h2>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                <input type="text" name="username" placeholder="Enter username" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                <input type="text" name="first_name" placeholder="Enter first name" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                <input type="text" name="last_name" placeholder="Enter last name" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                <select name="role" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Role</option>
                    <?php foreach($roles as $role): ?>
                    <option value="<?php echo htmlspecialchars($role); ?>"><?php echo htmlspecialchars($role); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <input type="password" name="password" placeholder="Enter password" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 btn-primary py-2 rounded-lg">Add User</button>
                <button type="button" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 rounded-lg" onclick="toggleModal('addUserModal')">Cancel</button>
            </div>
        </form>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center modal">
        <form id="editUserForm" class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full" method="post">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" id="editUsername" name="username">
            <h2 class="text-lg font-semibold text-gray-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">edit</span>
                Edit User
            </h2>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                <input type="text" id="editUsernameDisplay" disabled class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-100">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                <input type="text" id="editFirstName" name="first_name" placeholder="Enter first name" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                <input type="text" id="editLastName" name="last_name" placeholder="Enter last name" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                <select id="editRole" name="role" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">Select Role</option>
                    <?php foreach($roles as $role): ?>
                    <option value="<?php echo htmlspecialchars($role); ?>"><?php echo htmlspecialchars($role); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 btn-primary py-2 rounded-lg">Save Changes</button>
                <button type="button" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 rounded-lg" onclick="toggleModal('editUserModal')">Cancel</button>
            </div>
        </form>
    </div>

    <!-- Notification Modal -->
    <div id="notificationModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center modal">
        <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <span id="notificationIcon" class="material-icons text-blue-600"></span>
                <span id="notificationTitle">Notification</span>
            </h2>
            <p id="notificationMessage" class="text-sm text-gray-600 mb-6"></p>
            <div class="flex justify-end">
                <button class="btn-primary px-4 py-2 rounded-lg" onclick="toggleModal('notificationModal')">OK</button>
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
                <button id="confirmAction" class="flex-1 btn-primary py-2 rounded-lg">Confirm</button>
                <button class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 rounded-lg" onclick="toggleModal('confirmModal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center modal">
        <form id="resetPasswordForm" class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full" method="post">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" id="resetUsername" name="username">
            <h2 class="text-lg font-semibold text-gray-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">lock_reset</span>
                Reset Password
            </h2>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                <input type="text" id="resetUsernameDisplay" disabled class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-100">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                <input type="password" id="newPassword" name="new_password" placeholder="Enter new password" required class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 btn-primary py-2 rounded-lg">Reset Password</button>
                <button type="button" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 rounded-lg" onclick="toggleModal('resetPasswordModal')">Cancel</button>
            </div>
        </form>
    </div>

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

        // Modal toggle
        function toggleModal(modalId) {
            document.getElementById(modalId).classList.toggle('hidden');
        }

        // Show notification modal
        function showNotification(message, isSuccess = true) {
            const icon = document.getElementById('notificationIcon');
            const title = document.getElementById('notificationTitle');
            const messageEl = document.getElementById('notificationMessage');
            icon.textContent = isSuccess ? 'check_circle' : 'error';
            icon.className = `material-icons text-${isSuccess ? 'blue' : 'red'}-600`;
            title.textContent = isSuccess ? 'Success' : 'Error';
            messageEl.textContent = message;
            toggleModal('notificationModal');
        }

        // Show confirmation modal
        function showConfirmModal(action, username, actionType) {
            const messageEl = document.getElementById('confirmMessage');
            const confirmBtn = document.getElementById('confirmAction');
            messageEl.textContent = `Are you sure you want to ${actionType} the user ${username}?`;
            confirmBtn.onclick = () => {
                if (action === 'toggle_status') {
                    toggleUserStatus(username, actionType);
                } else if (action === 'delete_user') {
                    deleteUser(username);
                }
                toggleModal('confirmModal');
            };
            toggleModal('confirmModal');
        }

        // Show reset password modal
        function showResetPasswordModal(username) {
            document.getElementById('resetUsername').value = username;
            document.getElementById('resetUsernameDisplay').value = username;
            document.getElementById('newPassword').value = '';
            toggleModal('resetPasswordModal');
        }

        // Filter users
        function filterUsers() {
            const search = document.getElementById('searchUsers').value.toLowerCase();
            const role = document.getElementById('roleFilter').value;
            const status = document.getElementById('statusFilter').value;
            const loginDateFrom = document.getElementById('loginDateFrom').value;
            const loginDateTo = document.getElementById('loginDateTo').value;

            const rows = document.querySelectorAll('tbody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const roleCell = row.querySelector('td:nth-child(2)').textContent;
                const statusCell = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                const loginCell = row.querySelector('td:nth-child(4)').textContent;

                let show = text.includes(search);

                if (role && roleCell !== role) show = false;
                if (status && !statusCell.includes(status.toLowerCase())) show = false;

                if (loginDateFrom || loginDateTo) {
                    const loginDate = loginCell !== '-' ? new Date(loginCell) : null;
                    if (loginDateFrom && loginDate && loginDate < new Date(loginDateFrom)) show = false;
                    if (loginDateTo && loginDate && loginDate > new Date(loginDateTo)) show = false;
                    if (!loginDate && (loginDateFrom || loginDateTo)) show = false;
                }

                row.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            updateUserCount(visibleCount);
        }

        function clearFilters() {
            const inputs = ['searchUsers', 'roleFilter', 'statusFilter', 'loginDateFrom', 'loginDateTo'];
            inputs.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            filterUsers();
        }

        function updateUserCount(visible) {
            const total = document.querySelectorAll('tbody tr').length;
            document.getElementById('userCount').textContent = `Showing ${visible} of ${total} users`;
        }

        // Attach event listeners
        const filterInputs = ['searchUsers', 'roleFilter', 'statusFilter', 'loginDateFrom', 'loginDateTo'];
        filterInputs.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', filterUsers);
                if (id === 'searchUsers') el.addEventListener('input', filterUsers);
            }
        });

        // User actions via AJAX
        async function toggleUserStatus(username, actionType) {
            const response = await fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=toggle_status&username=${encodeURIComponent(username)}`
            });
            const result = await response.json();
            showNotification(result.message, result.success);
            if (result.success) setTimeout(() => location.reload(), 1000);
        }

        async function editUser(username) {
            const response = await fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_user&username=${encodeURIComponent(username)}`
            });
            const result = await response.json();
            if (result.success) {
                document.getElementById('editUsername').value = result.user.username;
                document.getElementById('editUsernameDisplay').value = result.user.username;
                document.getElementById('editFirstName').value = result.user.first_name;
                document.getElementById('editLastName').value = result.user.last_name;
                document.getElementById('editRole').value = result.user.role;
                toggleModal('editUserModal');
            } else {
                showNotification(result.message, false);
            }
        }

        async function deleteUser(username) {
            const response = await fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=delete_user&username=${encodeURIComponent(username)}`
            });
            const result = await response.json();
            showNotification(result.message, result.success);
            if (result.success) setTimeout(() => location.reload(), 1000);
        }

        // Form submissions via AJAX
        document.getElementById('addUserForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add_user');
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            showNotification(result.message, result.success);
            if (result.success) {
                toggleModal('addUserModal');
                setTimeout(() => location.reload(), 1000);
            }
        });

        document.getElementById('editUserForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'edit_user');
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            showNotification(result.message, result.success);
            if (result.success) {
                toggleModal('editUserModal');
                setTimeout(() => location.reload(), 1000);
            }
        });

        document.getElementById('resetPasswordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'reset_password');
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            showNotification(result.message, result.success);
            if (result.success) {
                toggleModal('resetPasswordModal');
                setTimeout(() => location.reload(), 1000);
            }
        });
    </script>
</body>
</html>