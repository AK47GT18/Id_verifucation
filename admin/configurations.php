<?php
session_start();
$username = $_SESSION['username'] ?? 'admin';

// Example configuration data (for demonstration)
$config = [
    'scanner' => [
        'barcode_enabled' => true,
        'camera_enabled' => false,
    ],
    'printer' => [
        'selected_printer' => 'Printer 1',
        'paper_size' => 'A4',
    ],
    'system' => [
        'session_timeout' => 30, // minutes
        'log_retention' => 90, // days
    ],
    'notifications' => [
        'email_enabled' => true,
        'sms_enabled' => false,
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurations</title>
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
            border-color: #3B82F6; /* Blue accent */
        }
        .btn-primary {
            background-color: #3B82F6; /* Blue-500 */
            color: white;
        }
        .btn-primary:hover {
            background-color: #2563EB; /* Blue-600 */
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
                    <a href="reports.php" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition-colors <?php if(basename($_SERVER['PHP_SELF'])=='reports.php') echo 'bg-blue-50 text-blue-600 font-medium'; ?>">
                        <span class="material-icons text-lg">bar_chart</span>
                        Reports
                    </a>
                    <a href="configurations.php" class="flex items-center gap-3 p-3 rounded-lg bg-blue-50 text-blue-600 font-medium">
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
                        <h1 class="text-xl font-semibold text-gray-900">Configurations</h1>
                    </div>
                    <div class="flex items-center gap-3">
                        <button class="btn-primary px-4 py-2 rounded-lg flex items-center gap-2 transition-colors" onclick="saveAllConfigs()">
                            <span class="material-icons text-sm">save</span>
                            Save All
                        </button>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-6 bg-gray-50">
                <div class="max-w-4xl mx-auto space-y-6">
                    <!-- Notification -->
                    <div id="notification" class="hidden fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg">
                        <div class="flex items-center gap-2">
                            <span id="notificationIcon" class="material-icons"></span>
                            <span id="notificationMessage"></span>
                        </div>
                    </div>

                    <!-- Scanner Settings -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="material-icons text-blue-600">qr_code_scanner</span>
                            Scanner Settings
                        </h2>
                        <form id="scannerForm">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Barcode Scanning</label>
                                <select name="barcode_enabled" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500">
                                    <option value="1" <?php echo $config['scanner']['barcode_enabled'] ? 'selected' : ''; ?>>Enabled</option>
                                    <option value="0" <?php echo !$config['scanner']['barcode_enabled'] ? 'selected' : ''; ?>>Disabled</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Camera Scanning</label>
                                <select name="camera_enabled" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500">
                                    <option value="1" <?php echo $config['scanner']['camera_enabled'] ? 'selected' : ''; ?>>Enabled</option>
                                    <option value="0" <?php echo !$config['scanner']['camera_enabled'] ? 'selected' : ''; ?>>Disabled</option>
                                </select>
                            </div>
                        </form>
                    </div>

                    <!-- Printer Settings -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="material-icons text-blue-600">print</span>
                            Printer Settings
                        </h2>
                        <form id="printerForm">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Printer</label>
                                <select name="selected_printer" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500">
                                    <option value="Printer 1" <?php echo $config['printer']['selected_printer'] === 'Printer 1' ? 'selected' : ''; ?>>Printer 1</option>
                                    <option value="Printer 2" <?php echo $config['printer']['selected_printer'] === 'Printer 2' ? 'selected' : ''; ?>>Printer 2</option>
                                    <option value="Printer 3" <?php echo $config['printer']['selected_printer'] === 'Printer 3' ? 'selected' : ''; ?>>Printer 3</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Paper Size</label>
                                <select name="paper_size" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500">
                                    <option value="A4" <?php echo $config['printer']['paper_size'] === 'A4' ? 'selected' : ''; ?>>A4</option>
                                    <option value="A5" <?php echo $config['printer']['paper_size'] === 'A5' ? 'selected' : ''; ?>>A5</option>
                                    <option value="Letter" <?php echo $config['printer']['paper_size'] === 'Letter' ? 'selected' : ''; ?>>Letter</option>
                                </select>
                            </div>
                        </form>
                    </div>

                    <!-- Admin Password Management -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="material-icons text-blue-600">lock</span>
                            Admin Password Management
                        </h2>
                        <form id="passwordForm">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                <input type="password" name="current_password" required class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                <input type="password" name="new_password" required class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                <input type="password" name="confirm_password" required class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500">
                            </div>
                        </form>
                    </div>

                    <!-- System Settings -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="material-icons text-blue-600">settings</span>
                            System Settings
                        </h2>
                        <form id="systemForm">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Session Timeout (minutes)</label>
                                <input type="number" name="session_timeout" value="<?php echo $config['system']['session_timeout']; ?>" min="5" max="120" required class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Log Retention (days)</label>
                                <input type="number" name="log_retention" value="<?php echo $config['system']['log_retention']; ?>" min="30" max="365" required class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500">
                            </div>
                        </form>
                    </div>

                    <!-- Notification Settings -->
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200 card">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                            <span class="material-icons text-blue-600">notifications</span>
                            Notification Settings
                        </h2>
                        <form id="notificationForm">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Notifications</label>
                                <select name="email_enabled" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500">
                                    <option value="1" <?php echo $config['notifications']['email_enabled'] ? 'selected' : ''; ?>>Enabled</option>
                                    <option value="0" <?php echo !$config['notifications']['email_enabled'] ? 'selected' : ''; ?>>Disabled</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">SMS Notifications</label>
                                <select name="sms_enabled" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500">
                                    <option value="1" <?php echo $config['notifications']['sms_enabled'] ? 'selected' : ''; ?>>Enabled</option>
                                    <option value="0" <?php echo !$config['notifications']['sms_enabled'] ? 'selected' : ''; ?>>Disabled</option>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 z-30 hidden md:hidden"></div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white p-8 rounded-xl shadow-lg max-w-md w-full">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <span class="material-icons text-blue-600">save</span>
                Confirm Changes
            </h2>
            <p class="text-sm text-gray-600 mb-6">Are you sure you want to save all configuration changes?</p>
            <div class="flex gap-3">
                <button id="confirmSave" class="flex-1 btn-primary py-2 rounded-lg">Save</button>
                <button id="cancelSave" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 rounded-lg">Cancel</button>
            </div>
        </div>
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
                setTimeout(() => notification.classList.add('hidden'), 500);
            }, 3000);
        }

        // Modal handling
        function toggleModal(modalId) {
            document.getElementById(modalId).classList.toggle('hidden');
        }

        // Form validation
        function validatePasswordForm() {
            const form = document.getElementById('passwordForm');
            const newPassword = form.querySelector('[name="new_password"]').value;
            const confirmPassword = form.querySelector('[name="confirm_password"]').value;

            if (newPassword !== confirmPassword) {
                showNotification('New password and confirmation do not match', 'error');
                return false;
            }
            if (newPassword.length < 8) {
                showNotification('New password must be at least 8 characters long', 'error');
                return false;
            }
            return true;
        }

        // Save all configurations
        async function saveAllConfigs() {
            if (!validatePasswordForm()) return;

            toggleModal('confirmModal');
            document.getElementById('confirmSave').onclick = async () => {
                const forms = ['scannerForm', 'printerForm', 'passwordForm', 'systemForm', 'notificationForm'];
                const configData = {};

                forms.forEach(formId => {
                    const form = document.getElementById(formId);
                    if (form) {
                        const category = formId.replace('Form', '');
                        configData[category] = Object.fromEntries(new FormData(form));
                    }
                });

                try {
                    const response = await fetch('save_configurations.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(configData)
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        showNotification(result.message, 'success');
                        if (configData.password) {
                            // Reload page after password change
                            setTimeout(() => window.location.reload(), 1500);
                        }
                    } else {
                        showNotification(result.message, 'error');
                    }
                } catch (error) {
                    showNotification('Error saving configurations', 'error');
                    console.error('Error:', error);
                }

                toggleModal('confirmModal');
            };
        }

        // Add configuration load function
        async function loadConfigurations() {
            try {
                const response = await fetch('get_configurations.php');
                const data = await response.json();
                
                if (data.success) {
                    Object.entries(data.configs).forEach(([category, settings]) => {
                        const form = document.getElementById(`${category}Form`);
                        if (form) {
                            Object.entries(settings).forEach(([name, value]) => {
                                const input = form.querySelector(`[name="${name}"]`);
                                if (input) {
                                    input.value = value;
                                }
                            });
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading configurations:', error);
                showNotification('Error loading configurations', 'error');
            }
        }

        // Call loadConfigurations when page loads
        document.addEventListener('DOMContentLoaded', loadConfigurations);
    </script>
</body>
</html>