<?php
session_start();
$role = $_SESSION['role'] ?? 'Admin'; // Use session logic for role
$username = $_SESSION['username'] ?? 'verifier1';

// Sample records data (for Verifier role)
$records = [
    [
        'id' => 1,
        'form_number' => '20875144',
        'name' => 'PEARSON MGALA',
        'national_id' => 'CH41QENN',
        'village' => 'Chobwe',
        'ta' => 'Wenya',
        'verified_at' => '2025-07-24 12:00:00',
        'verified_by' => $username,
        'status' => 'Verified',
        'verification_method' => 'QR Scan',
        'notes' => 'Documents verified successfully'
    ],
    [
        'id' => 2,
        'form_number' => '20875147',
        'name' => 'CHRISTINA MWANDIRA',
        'national_id' => 'SB399M5Z',
        'village' => 'Makanthani',
        'ta' => 'Wenya',
        'verified_at' => null,
        'verified_by' => null,
        'status' => 'Unverified',
        'verification_method' => null,
        'notes' => ''
    ],
    [
        'id' => 3,
        'form_number' => '20875148',
        'name' => 'JOHN KAMWENDO',
        'national_id' => 'CH42XYZA',
        'village' => 'Chobwe',
        'ta' => 'Wenya',
        'verified_at' => '2025-07-24 14:30:00',
        'verified_by' => $username,
        'status' => 'Verified',
        'verification_method' => 'Manual',
        'notes' => 'Manually verified ID'
    ]
];

// Filter records for Verifier role to show only those verified by the logged-in user
$verifiedRecords = ($role === 'Verifier') ? array_filter($records, fn($rec) => $rec['status'] === 'Verified' && $rec['verified_by'] === $username) : [];
$totalVerified = count($verifiedRecords);

// Sample stats for Admin role
$adminStats = [
    'users' => 24,
    'records' => 120,
    'verified' => 85
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Verification Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
    <div class="flex flex-col min-h-screen">
        <!-- Header -->
        <header class="bg-white border-b border-gray-200 px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="material-icons text-3xl <?php echo $role === 'Admin' ? 'text-blue-600' : 'text-green-600'; ?>">verified_user</span>
                <span class="font-bold text-2xl tracking-wide">ID Verification Portal</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="inline-flex items-center gap-2 bg-gray-100 px-3 py-1 rounded text-gray-700 text-sm">
                    <span class="material-icons text-base">person</span>
                    <?php echo htmlspecialchars($role); ?>
                </span>
                <a href="/auth/logout.php" class="text-sm text-red-500 hover:text-red-600">Sign out</a>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col items-center justify-center px-4 py-12">
            <div class="max-w-4xl w-full">
                <div class="text-center mb-10">
                    <h1 class="text-3xl md:text-4xl font-bold mb-2">Welcome to the ID Verification System</h1>
                    <p class="text-gray-500 text-lg">Manage, verify, and track records securely and efficiently.</p>
                </div>

                <?php if ($role === 'Admin'): ?>
                <!-- Admin Dashboard Links and Stats -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
                    <a href="/id_verification/admin/index.php" class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow card flex flex-col items-center justify-center p-8 transition">
                        <span class="material-icons text-5xl mb-2">admin_panel_settings</span>
                        <span class="font-semibold text-lg">Admin Dashboard</span>
                        <span class="text-sm mt-1 text-blue-100">User, record & system management</span>
                    </a>
                    <a href="/id_verification/verifier/index.php" class="bg-green-600 hover:bg-green-700 text-white rounded-xl shadow card flex flex-col items-center justify-center p-8 transition">
                        <span class="material-icons text-5xl mb-2">fact_check</span>
                        <span class="font-semibold text-lg">Verifier Panel</span>
                        <span class="text-sm mt-1 text-green-100">Scan & verify records</span>
                    </a>
                </div>
                <div class="bg-white rounded-xl shadow p-6">
                    <div class="flex flex-col md:flex-row items-center justify-center gap-6">
                        <div class="flex flex-col items-center">
                            <span class="material-icons text-4xl text-blue-600 mb-1">group</span>
                            <div class="font-bold text-xl"><?php echo $adminStats['users']; ?></div>
                            <div class="text-gray-500 text-sm">Users</div>
                        </div>
                        <div class="flex flex-col items-center">
                            <span class="material-icons text-4xl text-blue-600 mb-1">folder</span>
                            <div class="font-bold text-xl"><?php echo $adminStats['records']; ?></div>
                            <div class="text-gray-500 text-sm">Records</div>
                        </div>
                        <div class="flex flex-col items-center">
                            <span class="material-icons text-4xl text-blue-600 mb-1">check_circle</span>
                            <div class="font-bold text-xl"><?php echo $adminStats['verified']; ?></div>
                            <div class="text-gray-500 text-sm">Verified</div>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <!-- Verifier Verified Records -->
                <div class="bg-white rounded-xl shadow p-6 mb-10">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-icons text-green-600">folder</span>
                        My Verified Records
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200">
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-900">Form #</th>
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-900">Name</th>
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-900">National ID</th>
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-900">Village</th>
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-900">Verified At</th>
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-900">Method</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach($verifiedRecords as $record): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="py-3 px-4 text-sm text-gray-900"><?php echo htmlspecialchars($record['form_number']); ?></td>
                                    <td class="py-3 px-4 text-sm text-gray-900"><?php echo htmlspecialchars($record['name']); ?></td>
                                    <td class="py-3 px-4 text-sm text-gray-600"><?php echo htmlspecialchars($record['national_id']); ?></td>
                                    <td class="py-3 px-4 text-sm text-gray-600"><?php echo htmlspecialchars($record['village']); ?></td>
                                    <td class="py-3 px-4 text-sm text-gray-600">
                                        <?php echo $record['verified_at'] ? date('M d, Y H:i', strtotime($record['verified_at'])) : '-'; ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-gray-600"><?php echo $record['verification_method'] ?? '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($verifiedRecords)): ?>
                                <tr>
                                    <td colspan="6" class="py-3 px-4 text-center text-sm text-gray-500">
                                        No verified records found.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Footer -->
                <div class="text-center text-gray-400 text-xs">
                    © <?php echo date('Y'); ?> ID Verification System. All rights reserved.
                </div>
            </div>
        </main>
    </div>
</body>
</html>