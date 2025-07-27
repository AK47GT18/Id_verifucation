<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if (!isset($_SESSION['user_id'])) {
    header("Location: /id_verification/login.php");
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Fetch verified records
    $recordsQuery = "
        SELECT 
            r.form_number,
            r.national_id,
            r.first_name,
            r.last_name,
            r.village,
            r.traditional_authority,
            r.gender,
            r.project,
            v.verified_at,
            v.verification_type,
            v.comment
        FROM records r
        JOIN verifications v ON r.id = v.record_id
        WHERE v.verified_by = ?
        ORDER BY v.verified_at DESC";

    $stmt = $conn->prepare($recordsQuery);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Fetch user stats
    $userStatsQuery = "
        SELECT 
            u.username,
            COUNT(v.id) as verification_count
        FROM users u
        LEFT JOIN verifications v ON u.id = v.verified_by
        WHERE u.enabled = 1
        GROUP BY u.id, u.username
        ORDER BY verification_count DESC";

    $userStats = $conn->query($userStatsQuery)->fetch_all(MYSQLI_ASSOC);

    // Create spreadsheet
    $spreadsheet = new Spreadsheet();
    
    // Records sheet
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Verified Records');

    // Headers
    $headers = [
        'Form Number', 'National ID', 'First Name', 'Last Name', 'Village', 
        'Traditional Authority', 'Gender', 'Project', 'Verified At', 
        'Verification Type', 'Comment'
    ];
    
    // Style the headers
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'E3E3E3']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ];
    
    $sheet->fromArray($headers, NULL, 'A1');
    $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);

    // Data
    $row = 2;
    foreach ($records as $record) {
        $sheet->setCellValue("A$row", $record['form_number']);
        $sheet->setCellValue("B$row", $record['national_id']);
        $sheet->setCellValue("C$row", $record['first_name']);
        $sheet->setCellValue("D$row", $record['last_name']);
        $sheet->setCellValue("E$row", $record['village'] ?? '');
        $sheet->setCellValue("F$row", $record['traditional_authority'] ?? '');
        $sheet->setCellValue("G$row", $record['gender'] ?? '');
        $sheet->setCellValue("H$row", $record['project'] ?? '');
        $sheet->setCellValue("I$row", date('Y-m-d H:i:s', strtotime($record['verified_at'])));
        $sheet->setCellValue("J$row", ucfirst($record['verification_type']));
        $sheet->setCellValue("K$row", $record['comment'] ?? '');
        $row++;
    }

    // Auto-size columns
    foreach (range('A', 'K') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // User Stats sheet
    $userSheet = $spreadsheet->createSheet();
    $userSheet->setTitle('User Stats');

    // Headers for user stats
    $userHeaders = ['Username', 'Verification Count'];
    $userSheet->fromArray($userHeaders, NULL, 'A1');
    $userSheet->getStyle('A1:B1')->applyFromArray($headerStyle);

    // Data for user stats
    $row = 2;
    foreach ($userStats as $user) {
        $userSheet->setCellValue("A$row", $user['username']);
        $userSheet->setCellValue("B$row", $user['verification_count']);
        $row++;
    }

    // Auto-size columns for user stats
    foreach (range('A', 'B') as $col) {
        $userSheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Set active sheet to first sheet
    $spreadsheet->setActiveSheetIndex(0);

    // Save to output
    $writer = new Xlsx($spreadsheet);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="verified_records_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    ob_end_clean(); // Clear output buffer
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    header('Location: records.php?error=' . urlencode('Error generating export: ' . $e->getMessage()));
    exit;
} finally {
    $conn->close();
}
?>
