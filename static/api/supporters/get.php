<?php
/**
 * Supporters CSV Download Endpoint
 * Protected with HTTP Basic Auth
 * Used by GitHub Actions to fetch supporters for builds
 */

// Auth (shared config + Basic Auth validation)
require_once __DIR__ . '/auth.php';

// Return CSV WITHOUT BOM
$csv_file = __DIR__ . '/supporters.csv';

if (!file_exists($csv_file)) {
    http_response_code(404);
    die('No supporters data yet');
}

header('Content-Type: application/octet-stream'); // Force binary mode
header('Content-Disposition: attachment; filename="supporters.csv"');
header('Content-Length: ' . filesize($csv_file));

// Output file as raw bytes (no encoding conversion)
$fp = fopen($csv_file, 'rb');
fpassthru($fp);
fclose($fp);
