<?php
/**
 * DEBUG VERSION - Download supporters.csv with BOM diagnostic
 */

require_once __DIR__ . '/.env.php';

// Validate Basic Auth
$user = $_SERVER['PHP_AUTH_USER'] ?? '';
$pass = $_SERVER['PHP_AUTH_PW'] ?? '';

if ($user !== API_USER || $pass !== API_PASS) {
    header('WWW-Authenticate: Basic realm="OpenCloudTouch Supporters API"');
    http_response_code(401);
    die('Unauthorized');
}

// Read CSV
$csv_file = __DIR__ . '/supporters.csv';
if (!file_exists($csv_file)) {
    http_response_code(404);
    die('No supporters data yet');
}

$content = file_get_contents($csv_file);
$original_length = strlen($content);
$original_first_bytes = bin2hex(substr($content, 0, 10));

// Remove BOM
$bom = pack('H*', 'EFBBBF');
$has_bom = (substr($content, 0, 3) === $bom);
if ($has_bom) {
    $content = substr($content, 3);
}

$final_length = strlen($content);
$final_first_bytes = bin2hex(substr($content, 0, 10));

// Output diagnostic JSON
header('Content-Type: application/json');
echo json_encode([
    'original_length' => $original_length,
    'original_first_bytes' => $original_first_bytes,
    'has_bom' => $has_bom,
    'final_length' => $final_length,
    'final_first_bytes' => $final_first_bytes,
    'csv_preview' => substr($content, 0, 100)
], JSON_PRETTY_PRINT);
