<?php
require_once __DIR__ . '/db_connection.php';

// Only start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class RecordManager {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    private function getUserIdByUsername($username) {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user ? $user['id'] : null;
    }

    public function getRecords($role, $username, $offset = 0, $limit = 20) {
        $user_id = $this->getUserIdByUsername($username);
        if (!$user_id && $role === 'Verifier') {
            return [];
        }

        $query = "
            SELECT r.id, r.form_number, r.national_id, r.first_name, r.last_name, r.gender, r.project, 
                   r.traditional_authority, r.group_village_head, r.village, r.SCTP_UBR_NUMBER, 
                   r.HH_CODE, r.TA, r.CLUSTER, r.ZONE, r.HOUSEHOLD_HEAD_NAME, r.created_at, 
                   r.status, r.updated_at, r.verified_by, r.verified_at, u.username AS verified_by_username
            FROM records r
            LEFT JOIN verifications v ON r.id = v.record_id
            LEFT JOIN users u ON r.verified_by = u.id
        ";
        $params = [];
        $types = '';

        try {
            if ($role === 'Verifier') {
                $query .= " WHERE (r.status = 'Unverified' OR r.status = 'Verified') AND (r.verified_by = ? OR v.verified_by = ?)";
                $params[] = $user_id;
                $params[] = $user_id;
                $types .= 'ii';
            }
            $query .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;
            $types .= 'ii';

            $stmt = $this->conn->prepare($query);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $records = [];
            while ($row = $result->fetch_assoc()) {
                $row['verified'] = ($row['status'] === 'Verified') ? 1 : 0;
                $row['verified_by'] = $row['verified_by_username'] ?? null;
                $row['verified_at'] = $row['verified_at'] ?? $row['updated_at'] ?? null;
                unset($row['verified_by_username']);
                $records[] = $row;
            }
            $stmt->close();
            return $records;
        } catch (mysqli_sql_exception $e) {
            error_log("Database error in getRecords: " . $e->getMessage());
            return [];
        }
    }

    public function countRecords($filters, $role, $username) {
        $user_id = $this->getUserIdByUsername($username);
        if (!$user_id && $role === 'Verifier') {
            return 0;
        }

        $query = "SELECT COUNT(*) FROM records r LEFT JOIN verifications v ON r.id = v.record_id";
        $conditions = [];
        $params = [];
        $types = '';

        if ($role === 'Verifier') {
            $conditions[] = "(r.status = 'Unverified' OR r.status = 'Verified') AND (r.verified_by = ? OR v.verified_by = ?)";
            $params[] = $user_id;
            $params[] = $user_id;
            $types .= 'ii';
        }

        if (!empty($filters['search'])) {
            $conditions[] = "(r.form_number LIKE ? OR r.national_id LIKE ? OR r.first_name LIKE ? OR r.last_name LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$search, $search, $search, $search]);
            $types .= 'ssss';
        }

        if (!empty($filters['status'])) {
            $conditions[] = "r.status = ?";
            $params[] = $filters['status'] === 'verified' ? 'Verified' : 'Unverified';
            $types .= 's';
        }

        if (!empty($filters['ta'])) {
            $conditions[] = "r.TA LIKE ?";
            $params[] = '%' . $filters['ta'] . '%';
            $types .= 's';
        }

        if (!empty($filters['gender'])) {
            $conditions[] = "r.gender = ?";
            $params[] = $filters['gender'];
            $types .= 's';
        }

        if (!empty($filters['project'])) {
            $conditions[] = "r.project = ?";
            $params[] = $filters['project'];
            $types .= 's';
        }

        if (!empty($filters['village'])) {
            $conditions[] = "r.village = ?";
            $params[] = $filters['village'];
            $types .= 's';
        }

        if (!empty($filters['dateFrom'])) {
            $conditions[] = "r.created_at >= ?";
            $params[] = $filters['dateFrom'] . ' 00:00:00';
            $types .= 's';
        }

        if (!empty($filters['dateTo'])) {
            $conditions[] = "r.created_at <= ?";
            $params[] = $filters['dateTo'] . ' 23:59:59';
            $types .= 's';
        }

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        try {
            $stmt = $this->conn->prepare($query);
            if ($params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();
            return $count;
        } catch (mysqli_sql_exception $e) {
            error_log("Database error in countRecords: " . $e->getMessage());
            return 0;
        }
    }

    public function searchRecords($filters, $role, $username, $offset, $recordsPerPage) {
        $query = "
            SELECT 
                r.*, 
                u.username as verified_by,
                v.verified_at,
                v.verification_type,
                v.comment
            FROM records r
            LEFT JOIN verifications v ON r.id = v.record_id
            LEFT JOIN users u ON v.verified_by = u.id
            WHERE 1=1
        ";
        
        $params = [];
        $types = "";

        if (!empty($filters['search'])) {
            $search = "%{$filters['search']}%";
            $query .= " AND (r.form_number LIKE ? OR r.national_id LIKE ? OR 
                           r.first_name LIKE ? OR r.last_name LIKE ?)";
            $params = array_merge($params, [$search, $search, $search, $search]);
            $types .= "ssss";
        }

        if (!empty($filters['status'])) {
            $query .= " AND r.status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }

        if (!empty($filters['ta'])) {
            $query .= " AND r.traditional_authority = ?";
            $params[] = $filters['ta'];
            $types .= "s";
        }

        if (!empty($filters['gender'])) {
            $query .= " AND r.gender = ?";
            $params[] = $filters['gender'];
            $types .= "s";
        }

        if (!empty($filters['dateFrom'])) {
            $query .= " AND DATE(r.created_at) >= ?";
            $params[] = $filters['dateFrom'];
            $types .= "s";
        }

        if (!empty($filters['dateTo'])) {
            $query .= " AND DATE(r.created_at) <= ?";
            $params[] = $filters['dateTo'];
            $types .= "s";
        }

        $query .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $recordsPerPage;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function addRecord($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO records (
                form_number, national_id, first_name, last_name, gender, project, 
                traditional_authority, group_village_head, village, SCTP_UBR_NUMBER, 
                HH_CODE, TA, CLUSTER, ZONE, HOUSEHOLD_HEAD_NAME, created_at, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Unverified')
        ");
        $stmt->bind_param(
            "sssssssssssssss",
            $data['form_number'], $data['national_id'], $data['first_name'], 
            $data['last_name'], $data['gender'], $data['project'], 
            $data['traditional_authority'], $data['group_village_head'], 
            $data['village'], $data['SCTP_UBR_NUMBER'], $data['HH_CODE'], 
            $data['TA'], $data['CLUSTER'], $data['ZONE'], 
            $data['HOUSEHOLD_HEAD_NAME']
        );

        try {
            $stmt->execute();
            $stmt->close();
            return ['success' => true, 'message' => 'Record added successfully.'];
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            return ['success' => false, 'message' => 'Error adding record: ' . ($e->getCode() == 1062 ? 'Form number, national ID, or SCTP UBR number already exists.' : $e->getMessage())];
        }
    }

    public function updateRecord($id, $data) {
        $stmt = $this->conn->prepare("
            UPDATE records
            SET form_number = ?, national_id = ?, first_name = ?, last_name = ?, 
                gender = ?, project = ?, traditional_authority = ?, 
                group_village_head = ?, village = ?, SCTP_UBR_NUMBER = ?, 
                HH_CODE = ?, TA = ?, CLUSTER = ?, ZONE = ?, 
                HOUSEHOLD_HEAD_NAME = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param(
            "sssssssssssssssi",
            $data['form_number'], $data['national_id'], $data['first_name'], 
            $data['last_name'], $data['gender'], $data['project'], 
            $data['traditional_authority'], $data['group_village_head'], 
            $data['village'], $data['SCTP_UBR_NUMBER'], $data['HH_CODE'], 
            $data['TA'], $data['CLUSTER'], $data['ZONE'], 
            $data['HOUSEHOLD_HEAD_NAME'], $id
        );

        try {
            $stmt->execute();
            $stmt->close();
            return ['success' => true, 'message' => 'Record updated successfully.'];
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            return ['success' => false, 'message' => 'Error updating record: ' . ($e->getCode() == 1062 ? 'Form number, national ID, or SCTP UBR number already exists.' : $e->getMessage())];
        }
    }

    public function deleteRecord($id) {
        $stmt = $this->conn->prepare("DELETE FROM records WHERE id = ?");
        $stmt->bind_param("i", $id);

        try {
            $stmt->execute();
            $stmt->close();
            return ['success' => true, 'message' => 'Record deleted successfully.'];
        } catch (mysqli_sql_exception $e) {
            $stmt->close();
            return ['success' => false, 'message' => 'Error deleting record: ' . ($e->getCode() == 1451 ? 'Record has associated verifications.' : $e->getMessage())];
        }
    }

    public function verifyRecord($id, $username, $comment = null) {
        $user_id = $this->getUserIdByUsername($username);
        if (!$user_id) {
            return ['success' => false, 'message' => 'Invalid user.'];
        }

        $stmt = $this->conn->prepare("
            UPDATE records
            SET status = 'Verified', updated_at = NOW(), verified_by = ?, verified_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ii", $user_id, $id);

        try {
            $this->conn->begin_transaction();
            $stmt->execute();

            $verifyStmt = $this->conn->prepare("
                INSERT INTO verifications (record_id, verified_by, verified_at, comment)
                VALUES (?, ?, NOW(), ?)
            ");
            $verifyStmt->bind_param("iis", $id, $user_id, $comment);
            $verifyStmt->execute();
            $verifyStmt->close();

            $this->conn->commit();
            $stmt->close();
            return ['success' => true, 'message' => 'Record verified successfully.'];
        } catch (mysqli_sql_exception $e) {
            $this->conn->rollback();
            $stmt->close();
            error_log("Error in verifyRecord: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error verifying record: ' . $e->getMessage()];
        }
    }

    public function getRecordByBarcode($barcode) {
        $stmt = $this->conn->prepare("
            SELECT id
            FROM records
            WHERE form_number = ? OR national_id = ?
        ");
        $stmt->bind_param("ss", $barcode, $barcode);
        try {
            $stmt->execute();
            $result = $stmt->get_result();
            $record = $result->fetch_assoc();
            $stmt->close();
            return $record ? $record['id'] : null;
        } catch (mysqli_sql_exception $e) {
            error_log("Database error in getRecordByBarcode: " . $e->getMessage());
            $stmt->close();
            return null;
        }
    }

    public function getRecordDetails($id) {
        $stmt = $this->conn->prepare("
            SELECT r.id, r.form_number, r.national_id, r.first_name, r.last_name, r.gender, r.project, 
                   r.traditional_authority, r.group_village_head, r.village, r.SCTP_UBR_NUMBER, 
                   r.HH_CODE, r.TA, r.CLUSTER, r.ZONE, r.HOUSEHOLD_HEAD_NAME, r.created_at, 
                   r.status, r.updated_at, r.verified_by, r.verified_at, u.username AS verified_by_username
            FROM records r
            LEFT JOIN verifications v ON r.id = v.record_id
            LEFT JOIN users u ON r.verified_by = u.id
            WHERE r.id = ?
        ");
        $stmt->bind_param("i", $id);
        try {
            $stmt->execute();
            $result = $stmt->get_result();
            $record = $result->fetch_assoc();
            if ($record) {
                $record['verified'] = ($record['status'] === 'Verified') ? 1 : 0;
                $record['verified_by'] = $record['verified_by_username'] ?? null;
                $record['verified_at'] = $record['verified_at'] ?? $record['updated_at'] ?? null;
                unset($record['verified_by_username']);
            }
            $stmt->close();
            return $record ?: null;
        } catch (mysqli_sql_exception $e) {
            error_log("Database error in getRecordDetails: " . $e->getMessage());
            $stmt->close();
            return null;
        }
    }

    // Update the record fetching to include status
    public function getAllRecords() {
        try {
            $query = "
                SELECT r.*, u.username as verified_by_username 
                FROM records r 
                LEFT JOIN users u ON r.verified_by = u.id 
                ORDER BY r.created_at DESC";
            
            $result = $this->conn->query($query);
            
            if (!$result) {
                throw new Exception("Error fetching records: " . $this->conn->error);
            }

            $records = [];
            while ($row = $result->fetch_assoc()) {
                // Ensure all necessary fields are set
                $row['status'] = $row['status'] ?? 'Unverified';
                $row['verified'] = ($row['status'] === 'Verified') ? 1 : 0;
                $row['verified_by'] = $row['verified_by_username'] ?? null;
                unset($row['verified_by_username']);
                $records[] = $row;
            }
            
            return $records;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    public function importRecords($records) {
        $success = 0;
        $failed = 0;
        $errors = [];

        try {
            $this->conn->begin_transaction();

            $stmt = $this->conn->prepare("
                INSERT INTO records (
                    form_number, national_id, first_name, last_name, 
                    gender, project, traditional_authority, village, 
                    SCTP_UBR_NUMBER, HH_CODE, TA, CLUSTER, ZONE, 
                    HOUSEHOLD_HEAD_NAME
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($records as $index => $record) {
                try {
                    $stmt->bind_param(
                        'ssssssssssssss',
                        $record['form_number'],
                        $record['national_id'],
                        $record['first_name'],
                        $record['last_name'],
                        $record['gender'],
                        $record['project'],
                        $record['traditional_authority'],
                        $record['village'],
                        $record['SCTP_UBR_NUMBER'],
                        $record['HH_CODE'],
                        $record['TA'],
                        $record['CLUSTER'],
                        $record['ZONE'],
                        $record['HOUSEHOLD_HEAD_NAME']
                    );
                    $stmt->execute();
                    $success++;
                } catch (Exception $e) {
                    $failed++;
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                }
            }

            $this->conn->commit();
            return [
                'success' => true,
                'message' => "Import complete: $success added, $failed failed",
                'errors' => $errors
            ];
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }
}
?>