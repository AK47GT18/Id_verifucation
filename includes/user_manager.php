<?php
require_once __DIR__ . '/db_connection.php';

class UserManager {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getUsers() {
        $stmt = $this->conn->prepare("
            SELECT username, role, enabled, updated_at
            FROM users
            ORDER BY username
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                'username' => $row['username'],
                'role' => $row['role'],
                'status' => $row['enabled'] ? 'Enabled' : 'Disabled',
                'last_login' => $row['updated_at']
            ];
        }
        $stmt->close();
        return $users;
    }

    public function addUser($username, $password, $role, $first_name, $last_name) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $enabled = 1;
        $disabled = 0;

        $stmt = $this->conn->prepare("
            INSERT INTO users (username, password, role, first_name, last_name, enabled, disabled)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssssii",
            $username, $hashed_password, $role, $first_name, $last_name, $enabled, $disabled
        );

        try {
            $stmt->execute();
            $stmt->close();
            return ['success' => true, 'message' => 'User added successfully.'];
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            return ['success' => false, 'message' => 'Error adding user: ' . ($e->getCode() == 1062 ? 'Username already exists.' : $e->getMessage())];
        }
    }

    public function updateUser($username, $role, $first_name, $last_name) {
        $stmt = $this->conn->prepare("
            UPDATE users
            SET role = ?, first_name = ?, last_name = ?
            WHERE username = ?
        ");
        $stmt->bind_param("ssss", $role, $first_name, $last_name, $username);

        try {
            $stmt->execute();
            $stmt->close();
            return ['success' => true, 'message' => 'User updated successfully.'];
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            return ['success' => false, 'message' => 'Error updating user: ' . $e->getMessage()];
        }
    }

    public function toggleUserStatus($username) {
        $stmt = $this->conn->prepare("
            SELECT enabled FROM users WHERE username = ?
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($enabled);
        $stmt->fetch();
        $stmt->close();

        $new_enabled = $enabled ? 0 : 1;
        $new_disabled = $enabled ? 1 : 0;

        $stmt = $this->conn->prepare("
            UPDATE users
            SET enabled = ?, disabled = ?
            WHERE username = ?
        ");
        $stmt->bind_param("iis", $new_enabled, $new_disabled, $username);

        try {
            $stmt->execute();
            $stmt->close();
            return ['success' => true, 'message' => 'User status toggled successfully.'];
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            return ['success' => false, 'message' => 'Error toggling user status: ' . $e->getMessage()];
        }
    }

    public function deleteUser($username) {
        $stmt = $this->conn->prepare("
            DELETE FROM users WHERE username = ?
        ");
        $stmt->bind_param("s", $username);

        try {
            $stmt->execute();
            $stmt->close();
            return ['success' => true, 'message' => 'User deleted successfully.'];
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            return ['success' => false, 'message' => 'Error deleting user: ' . ($e->getCode() == 1451 ? 'User has associated verifications.' : $e->getMessage())];
        }
    }

    public function resetPassword($username, $new_password) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $this->conn->prepare("
            UPDATE users
            SET password = ?
            WHERE username = ?
        ");
        $stmt->bind_param("ss", $hashed_password, $username);

        try {
            $stmt->execute();
            $stmt->close();
            return ['success' => true, 'message' => "Password reset successfully. New password: $new_password"];
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            return ['success' => false, 'message' => 'Error resetting password: ' . $e->getMessage()];
        }
    }

    public function getUserDetails($username) {
        $stmt = $this->conn->prepare("
            SELECT username, role, first_name, last_name
            FROM users
            WHERE username = ?
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user ?: null;
    }
}
?>