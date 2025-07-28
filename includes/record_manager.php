<?php
class RecordManager {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getRecords($role, $username, $offset, $limit) {
        $query = "SELECT r.*, v.verified_by, v.verification_type, v.verified_at, v.comment, u.username as verified_by_username 
                  FROM records r 
                  LEFT JOIN verifications v ON r.id = v.record_id 
                  LEFT JOIN users u ON v.verified_by = u.id";
        if ($role !== 'Admin') {
            $query .= " WHERE v.verified_by = (SELECT id FROM users WHERE username = ?)";
        }
        $query .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($query);
        if ($role !== 'Admin') {
            $stmt->bind_param("sii", $username, $limit, $offset);
        } else {
            $stmt->bind_param("ii", $limit, $offset);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function countRecords($filters, $role, $username) {
        $query = "SELECT COUNT(*) as total 
                  FROM records r 
                  LEFT JOIN verifications v ON r.id = v.record_id 
                  LEFT JOIN users u ON v.verified_by = u.id";
        $params = [];
        $types = '';
        if ($role !== 'Admin') {
            $query .= " WHERE v.verified_by = (SELECT id FROM users WHERE username = ?)";
            $params[] = $username;
            $types .= 's';
        }
        $query .= $this->buildWhereClause($filters, $types, $params);
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['total'];
    }

    public function searchRecords($filters, $role, $username, $offset, $limit) {
        $query = "SELECT r.*, v.verified_by, v.verification_type, v.verified_at, v.comment, u.username as verified_by_username 
                  FROM records r 
                  LEFT JOIN verifications v ON r.id = v.record_id 
                  LEFT JOIN users u ON v.verified_by = u.id";
        $params = [];
        $types = '';
        if ($role !== 'Admin') {
            $query .= " WHERE v.verified_by = (SELECT id FROM users WHERE username = ?)";
            $params[] = $username;
            $types .= 's';
        }
        $query .= $this->buildWhereClause($filters, $types, $params);
        $query .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function buildWhereClause($filters, &$types, &$params) {
        $conditions = [];
        if (!empty($filters['search'])) {
            $conditions[] = "(r.form_number LIKE ? OR r.national_id LIKE ? OR r.first_name LIKE ? OR r.last_name LIKE ? OR u.username LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $types .= 'sssss';
        }
        if (!empty($filters['status'])) {
            $conditions[] = "r.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        if (!empty($filters['ta'])) {
            $conditions[] = "r.traditional_authority = ?";
            $params[] = $filters['ta'];
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
            $conditions[] = "v.verified_at >= ?";
            $params[] = $filters['dateFrom'];
            $types .= 's';
        }
        if (!empty($filters['dateTo'])) {
            $conditions[] = "v.verified_at <= ?";
            $params[] = $filters['dateTo'];
            $types .= 's';
        }
        if (!empty($filters['verifiedBy'])) {
            $conditions[] = "u.username = ?";
            $params[] = $filters['verifiedBy'];
            $types .= 's';
        }
        if (!empty($filters['verificationType'])) {
            $conditions[] = "v.verification_type = ?";
            $params[] = $filters['verificationType'];
            $types .= 's';
        }
        return empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions);
    }

    public function addRecord($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO records (
                form_number, national_id, first_name, last_name, gender, project, 
                traditional_authority, group_village_head, village, SCTP_UBR_NUMBER, 
                HH_CODE, TA, CLUSTER, ZONE, household_head_name
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssssssssssssss",
            $data['form_number'], $data['national_id'], $data['first_name'], 
            $data['last_name'], $data['gender'], $data['project'], 
            $data['traditional_authority'], $data['group_village_head'], 
            $data['village'], $data['SCTP_UBR_NUMBER'], $data['HH_CODE'], 
            $data['TA'], $data['CLUSTER'], $data['ZONE'], $data['household_head_name']
        );
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Record added successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to add record: ' . $stmt->error];
        }
    }

    public function updateRecord($id, $data) {
        $stmt = $this->conn->prepare("
            UPDATE records SET 
                form_number = ?, national_id = ?, first_name = ?, last_name = ?, 
                gender = ?, project = ?, traditional_authority = ?, 
                group_village_head = ?, village = ?, SCTP_UBR_NUMBER = ?, 
                HH_CODE = ?, TA = ?, CLUSTER = ?, ZONE = ?, household_head_name = ?
            WHERE id = ?
        ");
        $stmt->bind_param(
            "sssssssssssssssi",
            $data['form_number'], $data['national_id'], $data['first_name'], 
            $data['last_name'], $data['gender'], $data['project'], 
            $data['traditional_authority'], $data['group_village_head'], 
            $data['village'], $data['SCTP_UBR_NUMBER'], $data['HH_CODE'], 
            $data['TA'], $data['CLUSTER'], $data['ZONE'], $data['household_head_name'], 
            $id
        );
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Record updated successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to update record: ' . $stmt->error];
        }
    }

    public function deleteRecord($id) {
        $stmt = $this->conn->prepare("DELETE FROM records WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $this->conn->query("DELETE FROM verifications WHERE record_id = $id");
            return ['success' => true, 'message' => 'Record deleted successfully.'];
        } else {
            return ['success' => false, 'message' => 'Failed to delete record: ' . $stmt->error];
        }
    }

    public function verifyRecord($id, $verifiedBy, $comment, $verificationType) {
        $stmt = $this->conn->prepare("SELECT status FROM records WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $record = $result->fetch_assoc();
        if (!$record) {
            return ['success' => false, 'message' => 'Record not found.'];
        }
        if ($record['status'] === 'Verified') {
            return ['success' => false, 'message' => 'Record is already verified.'];
        }

        $stmt = $this->conn->prepare("
            UPDATE records SET status = 'Verified' WHERE id = ?
        ");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt = $this->conn->prepare("
                INSERT INTO verifications (record_id, verified_by, verification_type, comment, verified_at)
                SELECT ?, u.id, ?, ?, NOW()
                FROM users u WHERE u.username = ?
            ");
            $stmt->bind_param("isss", $id, $verificationType, $comment, $verifiedBy);
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Record verified successfully.'];
            } else {
                return ['success' => false, 'message' => 'Failed to save verification details: ' . $stmt->error];
            }
        } else {
            return ['success' => false, 'message' => 'Failed to verify record: ' . $stmt->error];
        }
    }

    public function unverifyRecord($id, $userId) {
        $stmt = $this->conn->prepare("
            SELECT v.verified_by FROM records r 
            LEFT JOIN verifications v ON r.id = v.record_id 
            WHERE r.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $record = $result->fetch_assoc();
        if (!$record) {
            return ['success' => false, 'message' => 'Record not found.'];
        }
        if ($record['verified_by'] !== $userId && $role !== 'Admin') {
            return ['success' => false, 'message' => 'You are not authorized to unverify this record.'];
        }

        $stmt = $this->conn->prepare("
            UPDATE records SET status = 'Unverified' WHERE id = ?
        ");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt = $this->conn->prepare("DELETE FROM verifications WHERE record_id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Record unverified successfully.'];
            } else {
                return ['success' => false, 'message' => 'Failed to remove verification details: ' . $stmt->error];
            }
        } else {
            return ['success' => false, 'message' => 'Failed to unverify record: ' . $stmt->error];
        }
    }

    public function getRecordByBarcode($barcode) {
        $stmt = $this->conn->prepare("SELECT id FROM records WHERE national_id = ? OR form_number = ?");
        $stmt->bind_param("ss", $barcode, $barcode);
        $stmt->execute();
        $result = $stmt->get_result();
        $record = $result->fetch_assoc();
        return $record ? $record['id'] : null;
    }

    public function getRecordDetails($id) {
        $stmt = $this->conn->prepare("
            SELECT r.*, v.verified_by, v.verification_type, v.verified_at, v.comment, u.username as verified_by_username 
            FROM records r 
            LEFT JOIN verifications v ON r.id = v.record_id 
            LEFT JOIN users u ON v.verified_by = u.id 
            WHERE r.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function importRecords($records) {
        $successCount = 0;
        $errorMessages = [];
        foreach ($records as $record) {
            $stmt = $this->conn->prepare("
                INSERT INTO records (
                    form_number, national_id, first_name, last_name, gender, project, 
                    traditional_authority, village, SCTP_UBR_NUMBER, HH_CODE, TA, 
                    CLUSTER, ZONE, household_head_name
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "ssssssssssssss",
                $record['form_number'], $record['national_id'], $record['first_name'], 
                $record['last_name'], $record['gender'], $record['project'], 
                $record['traditional_authority'], $record['village'], 
                $record['SCTP_UBR_NUMBER'], $record['HH_CODE'], $record['TA'], 
                $record['CLUSTER'], $record['ZONE'], $record['household_head_name']
            );
            if ($stmt->execute()) {
                $successCount++;
            } else {
                $errorMessages[] = "Failed to import record with form number {$record['form_number']}: {$stmt->error}";
            }
        }
        if ($successCount === count($records)) {
            return ['success' => true, 'message' => "Successfully imported $successCount records."];
        } else {
            return ['success' => false, 'message' => "Imported $successCount records. Errors: " . implode('; ', $errorMessages)];
        }
    }
}
?>