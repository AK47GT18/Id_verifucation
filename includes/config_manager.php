<?php

class ConfigManager {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getConfigurations() {
        $configs = [];
        $query = "SELECT category, name, value FROM configurations";
        $result = $this->conn->query($query);

        while ($row = $result->fetch_assoc()) {
            if (!isset($configs[$row['category']])) {
                $configs[$row['category']] = [];
            }
            $configs[$row['category']][$row['name']] = $row['value'];
        }

        return $configs;
    }

    public function saveConfigurations($configs, $username) {
        try {
            $this->conn->begin_transaction();

            foreach ($configs as $category => $settings) {
                foreach ($settings as $name => $value) {
                    $stmt = $this->conn->prepare("
                        INSERT INTO configurations (category, name, value, updated_by) 
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        value = VALUES(value),
                        updated_by = VALUES(updated_by)
                    ");
                    $stmt->bind_param("ssss", $category, $name, $value, $username);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $this->conn->commit();
            return ['success' => true, 'message' => 'Configurations saved successfully'];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => 'Error saving configurations: ' . $e->getMessage()];
        }
    }
}