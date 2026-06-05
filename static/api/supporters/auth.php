<?php
/**
 * Shared authentication for the OpenCloudTouch Supporters API.
 * Used by get.php and upload.php.
 */

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
