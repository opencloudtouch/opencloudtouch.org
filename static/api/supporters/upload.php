<?php
/**
 * Supporters CSV Upload Endpoint
 * Protected with HTTP Basic Auth
 * Used for initial CSV upload or manual updates
 */

// Auth (shared config + Basic Auth validation)
require_once __DIR__ . '/auth.php';

// Handle POST upload
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed. Use POST with CSV file.');
}

// Get CSV content from POST body
$csv_content = file_get_contents('php://input');

if (empty($csv_content)) {
    http_response_code(400);
    die('Empty CSV content');
}

// Validate CSV format (must have header: name,type,amount,monthlyAmount,firstSupportDate)
$lines = explode("\n", trim($csv_content));
if (empty($lines)) {
    http_response_code(400);
    die('Invalid CSV: no header');
}

$header = str_getcsv($lines[0]);
$expected_header = ['name', 'type', 'amount', 'monthlyAmount', 'firstSupportDate'];

if ($header !== $expected_header) {
    http_response_code(400);
    die('Invalid CSV header. Expected: ' . implode(',', $expected_header));
}

// Validate all rows
for ($i = 1; $i < count($lines); $i++) {
    if (empty(trim($lines[$i]))) {
        continue;
    }
    
    $row = str_getcsv($lines[$i]);
    if (count($row) !== 5) {
        http_response_code(400);
        die(sprintf('Invalid CSV: row %d has %d columns (expected 5)', $i + 1, count($row)));
    }
    
    // Validate type
    if (!in_array($row[1], ['monthly', 'one-time'])) {
        http_response_code(400);
        die(sprintf('Invalid CSV: row %d has invalid type "%s" (must be "monthly" or "one-time")', $i + 1, $row[1]));
    }
    
    // Validate amounts are numeric
    if (!is_numeric($row[2]) || !is_numeric($row[3])) {
        http_response_code(400);
        die(sprintf('Invalid CSV: row %d has non-numeric amounts', $i + 1));
    }
    
    // Validate date format (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $row[4])) {
        http_response_code(400);
        die(sprintf('Invalid CSV: row %d has invalid date format (expected YYYY-MM-DD)', $i + 1));
    }
}

// Write CSV
$csv_file = __DIR__ . '/supporters.csv';
$backup_file = $csv_file . '.backup.' . date('Y-m-d_His');

// Backup existing file
if (file_exists($csv_file)) {
    copy($csv_file, $backup_file);
}

// Write new CSV
if (file_put_contents($csv_file, $csv_content) === false) {
    http_response_code(500);
    die('Failed to write CSV file');
}

// Count supporters
$supporter_count = count($lines) - 1; // Exclude header

http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'supporters_uploaded' => $supporter_count,
    'backup_created' => basename($backup_file)
]);
