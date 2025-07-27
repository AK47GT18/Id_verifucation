<?php
session_start();
require_once __DIR__ . '/../includes/db_connection.php';

$error = '';
$role = $_SESSION['role'] ?? 'Admin'; // Default to Admin for styling if no role set

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ? AND enabled = 1 AND disabled = 0");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $hashed_password, $user_role);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $user_role;

                // Redirect based on role
                if ($user_role === 'Admin') {
                    header("Location: /id_verification/admin/index.php");
                } else {
                    header("Location: /id_verification/verifier/php");
                }
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password, or account is disabled.";
        }
        $stmt->close();
    } else {
        $error = "Please enter both username and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ID Verification Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        @keyframes slideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .login-container {
            animation: slideIn 0.5s ease-out;
        }
        .btn-primary {
            background-color: #3B82F6; /* Blue-500 */
            color: white;
        }
        .btn-primary:hover {
            background-color: #2563EB; /* Blue-600 */
            transform: translateY(-2px);
        }
        .btn-verifier {
            background-color: #16A34A; /* Green-600 */
            color: white;
        }
        .btn-verifier:hover {
            background-color: #15803D; /* Green-700 */
            transform: translateY(-2px);
        }
        input:focus {
            outline: none;
            ring: 2px;
            ring-opacity: 50;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="login-container max-w-md w-full mx-4 bg-white rounded-xl shadow-sm border border-gray-200 p-8">
        <div class="flex items-center justify-center gap-3 mb-6">
            <span class="material-icons text-3xl text-<?php echo $role === 'Admin' ? 'blue' : 'green'; ?>-600">verified_user</span>
            <h2 class="text-2xl font-bold text-gray-900">ID Verification Portal</h2>
        </div>
        <?php if ($error): ?>
            <div class="error bg-black text-white text-sm text-center p-3 rounded-lg mb-6"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                <div class="relative">
                    <span class="material-icons text-sm text-gray-500 absolute left-3 top-1/2 transform -translate-y-1/2">person</span>
                    <input type="text" name="username" required
                           class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-<?php echo $role === 'Admin' ? 'blue' : 'green'; ?>-500 bg-white text-gray-900"
                           placeholder="Enter username">
                </div>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <div class="relative">
                    <span class="material-icons text-sm text-gray-500 absolute left-3 top-1/2 transform -translate-y-1/2">lock</span>
                    <input type="password" name="password" required
                           class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 focus:ring-2 focus:ring-<?php echo $role === 'Admin' ? 'blue' : 'green'; ?>-500 bg-white text-gray-900"
                           placeholder="Enter password">
                </div>
            </div>
           <button type="submit"
    class="w-full py-3 rounded-lg font-semibold text-white transition-transform duration-200 <?php echo $role === 'Admin' ? 'btn-primary' : 'btn-verifier'; ?>">
    Login
</button>

            </div>
        </form>
    </div>
</body>
</html>