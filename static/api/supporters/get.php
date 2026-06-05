<?php
/**
 * Supporters CSV Download Endpoint
 * Protected with HTTP Basic Auth
 * Used by GitHub Actions to fetch supporters for builds
 */

// Load config
$config_file = __DIR__ . '/.env.php';
if (!file_exists($config_file)) {
    http_response_code(500);
    die('Configuration missing');
}
require_once $config_file;

if (!defined('API_USER') || !defined('API_PASS')) {
    http_response_code(500);
    die('Auth credentials not configured');
}

// Validate Basic Auth
$user = $_SERVER['PHP_AUTH_USER'] ?? '';
$pass = $_SERVER['PHP_AUTH_PW'] ?? '';

if ($user !== API_USER || $pass !== API_PASS) {
    header('WWW-Authenticate: Basic realm="OpenCloudTouch Supporters API"');
    http_response_code(401);
    die('Unauthorized');
}

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
