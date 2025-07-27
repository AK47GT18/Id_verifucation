<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/record_manager.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Composer autoload for PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Admin') {
    header("Location: /id_verification/login.php");
    exit;
}

$recordManager = new RecordManager($conn);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload failed: ' . $file['error'];
    } else {
        $allowedTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        if (!in_array($file['type'], $allowedTypes)) {
            $error = 'Invalid file type. Please upload an Excel file (.xls, .xlsx).';
        } else {
            try {
                $spreadsheet = IOFactory::load($file['tmp_name']);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();
                $header = array_shift($rows); // Remove header row

                $expectedHeaders = [
                    'form_number', 'national_id', 'first_name', 'last_name', 'gender', 'project',
                    'traditional_authority', 'group_village_head', 'village', 'SCTP_UBR_NUMBER',
                    'HH_CODE', 'TA', 'CLUSTER', 'ZONE', 'HOUSEHOLD_HEAD_NAME'
                ];
                if ($header !== $expectedHeaders) {
                    $error = 'Invalid Excel format. Expected headers: ' . implode(', ', $expectedHeaders);
                } else {
                    $successCount = 0;
                    $errorCount = 0;
                    foreach ($rows as $row) {
                        $data = array_combine($expectedHeaders, $row);
                        if (empty($data['form_number']) || empty($data['national_id']) || empty($data['first_name']) || empty($data['last_name']) || empty($data['gender'])) {
                            $errorCount++;
                            continue;
                        }
                        $result = $recordManager->addRecord($data);
                        if ($result['success']) {
                            $successCount++;
                        } else {
                            $errorCount++;
                            error_log("Import error for form_number {$data['form_number']}: " . $result['message']);
                        }
                    }
                    $success = "Imported $successCount records successfully. Failed: $errorCount.";
                }
            } catch (Exception $e) {
                $error = 'Error processing Excel file: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Excel - ID Verification Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto p-6">
        <h1 class="text-2xl font-semibold text-gray-900 mb-6">Import Excel</h1>
        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-4"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-4"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Upload Excel File</label>
                <input type="file" name="excel_file" accept=".xls,.xlsx" required class="w-full px-4 py-2 border border-gray-200 rounded-lg">
            </div>
            <button type="submit" class="btn-primary px-4 py-2 rounded-lg flex items-center gap-2">
                <span class="material-icons">upload_file</span> Import
            </button>
        </form>
        <a href="/id_verification/admin/record_management.php" class="mt-4 inline-block text-blue-600 hover:text-blue-800">Back to Record Management</a>
    </div>
</body>
</html>