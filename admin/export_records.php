<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/record_manager.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Adjust for PHPSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Restrict access
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || !isset($_SESSION['user_id'])) {
    header("Location: /id_verification/login.php");
    exit;
}

$recordManager = new RecordManager($conn);
$role = $_SESSION['role'];
$username = $_SESSION['username'];

// Get filters
$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'status' => trim($_GET['status'] ?? ''),
    'ta' => trim($_GET['ta'] ?? ''),
    'project' => trim($_GET['project'] ?? ''),
    'village' => trim($_GET['village'] ?? ''),
    'dateFrom' => trim($_GET['dateFrom'] ?? ''),
    'dateTo' => trim($_GET['dateTo'] ?? '')
];

// Fetch records
$records = $recordManager->searchRecords($filters, $role, $username, 0, PHP_INT_MAX);

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Verification Records');

// Headers
$headers = ['Form Number', 'National ID', 'First Name', 'Last Name', 'Gender', 'Project', 'Traditional Authority', 'Village', 'Status', 'Verified At', 'Verified By', 'Verification Type'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    $sheet->getStyle($col . '1')->getFont()->setBold(true);
    $col++;
}

// Data
$row = 2;
foreach ($records as $record) {
    $col = 'A';
    $sheet->setCellValue($col++ . $row, $record['form_number'] ?? '-');
    $sheet->setCellValue($col++ . $row, $record['national_id'] ?? '-');
    $sheet->setCellValue($col++ . $row, $record['first_name'] ?? '-');
    $sheet->setCellValue($col++ . $row, $record['last_name'] ?? '-');
    $sheet->setCellValue($col++ . $row, $record['gender'] ?? '-');
    $sheet->setCellValue($col++ . $row, $record['project'] ?? '-');
    $sheet->setCellValue($col++ . $row, $record['traditional_authority'] ?? '-');
    $sheet->setCellValue($col++ . $row, $record['village'] ?? '-');
    $sheet->setCellValue($col++ . $row, $record['status'] ?? '-');
    $sheet->setCellValue($col++ . $row, $record['verified_at'] ?? '-');
    $sheet->setCellValue($col++ . $row, $record['verified_by'] ?? '-');
    $sheet->setCellValue($col++ . $row, $record['verification_type'] ?? '-');
    $row++;
}

// Auto-size columns
foreach (range('A', 'L') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Output
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="records_' . date('Ymd_His') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;