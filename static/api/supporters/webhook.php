<?php
// BuyMeACoffee Webhook Handler for OpenCloudTouch Supporters
// Handles all BMC event types, archives events, updates supporters.csv

// Buffer output to prevent BOM/whitespace from included files leaking into response
ob_start();
require_once __DIR__ . '/.env.php';
ob_end_clean();

// Set response headers
header('Content-Type: application/json; charset=utf-8');

// Fix PHP 8 json_encode float precision (serialize_precision=-1 outputs full IEEE 754)
ini_set('serialize_precision', 14);

mb_internal_encoding('UTF-8');

require_once __DIR__ . '/functions.php';

const DATETIME_FORMAT = 'Y-m-d H:i:s';

// Paths
$log_file = __DIR__ . '/webhook.log';
$debug_log = __DIR__ . '/webhook-debug.log';
$events_dir = __DIR__ . '/events';
$csv_file = __DIR__ . '/supporters.csv';
$lock_file = __DIR__ . '/supporters.lock';

// Create events directory
if (!is_dir($events_dir)) {
    mkdir($events_dir, 0755, true);
}

// Get raw payload
$payload = file_get_contents('php://input');

// Parse JSON
$data = json_decode($payload, true);
if (!$data) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid JSON'], JSON_UNESCAPED_UNICODE));
}

// Extract event info
$event_type = $data['type'] ?? 'unknown';
$event_id = $data['event_id'] ?? uniqid();
$live_mode = $data['live_mode'] ?? true;
$is_test = $live_mode === false;

// Archive event (before any processing)
$safeEventId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$event_id);
$safeEventType = preg_replace('/[^a-zA-Z0-9_.-]/', '', $event_type);
$archive_filename = sprintf(
    '%s/%s_%s_%s.json',
    $events_dir,
    date('Y-m-d_H-i-s'),
    $safeEventId,
    $safeEventType
);
file_put_contents($archive_filename, json_encode([
    'timestamp' => date(DATETIME_FORMAT),
    'event_id' => $event_id,
    'event_type' => $event_type,
    'live_mode' => $live_mode,
    'headers' => getallheaders(),
    'payload' => $data
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Debug log
file_put_contents($debug_log, date(DATETIME_FORMAT) . " === NEW REQUEST ===\n", FILE_APPEND);
file_put_contents($debug_log, "Event: $event_type (ID: $event_id)\n", FILE_APPEND);
file_put_contents($debug_log, "Archived: $archive_filename\n", FILE_APPEND);

// Signature verification (skip for test events)
if (!$is_test) {
    $signature = $_SERVER['HTTP_X_SIGNATURE_SHA256'] ?? '';
    $expected_signature = hash_hmac('sha256', $payload, BMC_WEBHOOK_SECRET);

    if (empty($signature) || !hash_equals($expected_signature, $signature)) {
        http_response_code(403);
        file_put_contents($log_file, date(DATETIME_FORMAT) . " [ERROR] Invalid signature for event $event_id\n", FILE_APPEND);
        die(json_encode(['error' => 'Invalid signature'], JSON_UNESCAPED_UNICODE));
    }
}

// ========================================
// CURRENCY CONVERSION (loaded from functions.php)
// ========================================

// ========================================
// CSV HELPERS (loaded from functions.php)
// ========================================

// ========================================
// EVENT ROUTING
// ========================================

switch ($event_type) {
    case 'donation.created':
        handleDonation($data, $csv_file, $lock_file, $log_file);
        break;

    case 'recurring_donation.started':
        handleSubscriptionStarted($data, $csv_file, $lock_file, $log_file);
        break;

    case 'recurring_donation.cancelled':
        handleSubscriptionCancelled($data, $csv_file, $lock_file, $log_file);
        break;

    case 'recurring_donation.updated':
        handleSubscriptionUpdated($data, $csv_file, $lock_file, $log_file);
        break;

    case 'donation.refunded':
    case 'extra_purchase.refunded':
    case 'wishlist_payment.refunded':
        handleRefund($data, $csv_file, $lock_file, $log_file);
        break;

    default:
        file_put_contents($log_file, date(DATETIME_FORMAT) . " [INFO] Archived unhandled event: $event_type\n", FILE_APPEND);
        http_response_code(200);
        echo json_encode(['status' => 'archived', 'event' => $event_type], JSON_UNESCAPED_UNICODE);
        exit;
}

// ========================================
// HANDLERS
// ========================================

function handleDonation($data, $csv_file, $lock_file, $log_file) {
    $eventData = $data['data'] ?? $data;
    $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');
    $currency = $eventData['currency'] ?? 'EUR';

    // Use total_amount_charged (string like "5.00") - more reliable than coffeeCount * coffeePrice
    $raw_amount = floatval($eventData['total_amount_charged'] ?? $eventData['amount'] ?? 0);

    // Extract date from created_at timestamp
    $created_at = $eventData['created_at'] ?? null;
    $date = $created_at ? date('Y-m-d', $created_at) : date('Y-m-d');

    // Currency conversion
    $amount = convertToEur($raw_amount, $currency, $date);

    file_put_contents($log_file, date(DATETIME_FORMAT) . " [INFO] Donation: $name (EUR $amount, was $currency $raw_amount)\n", FILE_APPEND);

    // donation.created is ALWAYS treated as a one-time amount addition.
    // BMC sends this for both one-time AND first recurring payments.
    // The recurring_donation.started event sets the monthly rate separately.
    withLock($lock_file, function() use ($name, $amount, $date, $csv_file, $log_file) {
        $supporters = readSupporters($csv_file);

        if (isset($supporters[$name])) {
            $supporters[$name]['amount'] += $amount;
            file_put_contents($log_file, date(DATETIME_FORMAT) . " [INFO] Updated: $name (total: EUR " . $supporters[$name]['amount'] . ")\n", FILE_APPEND);
        } else {
            $supporters[$name] = [
                'type' => 'one-time',
                'amount' => $amount,
                'monthly' => 0,
                'date' => $date
            ];
            file_put_contents($log_file, date(DATETIME_FORMAT) . " [INFO] Added: $name\n", FILE_APPEND);
        }

        writeSupporters($supporters, $csv_file);

        http_response_code(200);
        echo json_encode(['success' => true, 'supporter' => $name, 'amount' => round($amount, 2)], JSON_UNESCAPED_UNICODE);
    });
}

function handleSubscriptionStarted($data, $csv_file, $lock_file, $log_file) {
    $eventData = $data['data'] ?? $data;
    $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');
    $currency = $eventData['currency'] ?? 'EUR';
    $raw_amount = floatval($eventData['amount'] ?? 0);

    // Extract date from started_at timestamp
    $started_at = $eventData['started_at'] ?? null;
    $date = $started_at ? date('Y-m-d', $started_at) : date('Y-m-d');

    $monthly_rate = convertToEur($raw_amount, $currency, $date);

    file_put_contents($log_file, date(DATETIME_FORMAT) . " [INFO] Subscription started: $name (EUR $monthly_rate/month)\n", FILE_APPEND);

    withLock($lock_file, function() use ($name, $monthly_rate, $date, $csv_file, $log_file) {
        $supporters = readSupporters($csv_file);

        if (isset($supporters[$name])) {
            // Supporter already exists (donation.created arrived first) - set monthly rate + upgrade type
            $supporters[$name]['monthly'] = $monthly_rate;
            $supporters[$name]['type'] = 'monthly';
        } else {
            // Edge case: subscription event arrived before donation.created
            $supporters[$name] = [
                'type' => 'monthly',
                'amount' => 0,
                'monthly' => $monthly_rate,
                'date' => $date
            ];
        }

        writeSupporters($supporters, $csv_file);

        file_put_contents($log_file, date(DATETIME_FORMAT) . " [INFO] Updated: $name (monthly: EUR $monthly_rate)\n", FILE_APPEND);
        http_response_code(200);
        echo json_encode(['success' => true, 'supporter' => $name, 'monthly' => round($monthly_rate, 2)], JSON_UNESCAPED_UNICODE);
    });
}

function handleSubscriptionCancelled($data, $csv_file, $lock_file, $log_file) {
    $eventData = $data['data'] ?? $data;
    $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');

    file_put_contents($log_file, date(DATETIME_FORMAT) . " [INFO] Subscription cancelled: $name\n", FILE_APPEND);

    withLock($lock_file, function() use ($name, $csv_file, $log_file) {
        $supporters = readSupporters($csv_file);

        if (isset($supporters[$name])) {
            $supporters[$name]['monthly'] = 0;
            $supporters[$name]['type'] = 'one-time';
            writeSupporters($supporters, $csv_file);
            file_put_contents($log_file, date(DATETIME_FORMAT) . " [INFO] Updated: $name (monthly cancelled, kept amount: EUR " . $supporters[$name]['amount'] . ")\n", FILE_APPEND);
        } else {
            file_put_contents($log_file, date(DATETIME_FORMAT) . " [WARN] Cancel for unknown supporter: $name\n", FILE_APPEND);
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'supporter' => $name, 'action' => 'cancelled'], JSON_UNESCAPED_UNICODE);
    });
}

function handleSubscriptionUpdated($data, $csv_file, $lock_file, $log_file) {
    $eventData = $data['data'] ?? $data;
    $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');
    $currency = $eventData['currency'] ?? 'EUR';
    $raw_amount = floatval($eventData['amount'] ?? 0);
    $date = date('Y-m-d');

    $new_monthly = convertToEur($raw_amount, $currency, $date);

    file_put_contents($log_file, date(DATETIME_FORMAT) . " [INFO] Subscription updated: $name (new rate: EUR $new_monthly/month)\n", FILE_APPEND);

    withLock($lock_file, function() use ($name, $new_monthly, $csv_file, $log_file) {
        $supporters = readSupporters($csv_file);

        if (isset($supporters[$name])) {
            $supporters[$name]['monthly'] = $new_monthly;
            $supporters[$name]['type'] = 'monthly';
            writeSupporters($supporters, $csv_file);
        } else {
            file_put_contents($log_file, date(DATETIME_FORMAT) . " [WARN] Update for unknown supporter: $name\n", FILE_APPEND);
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'supporter' => $name, 'monthly' => round($new_monthly, 2)], JSON_UNESCAPED_UNICODE);
    });
}

function handleRefund($data, $csv_file, $lock_file, $log_file) {
    $eventData = $data['data'] ?? $data;
    $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');
    $currency = $eventData['currency'] ?? 'EUR';
    $date = date('Y-m-d');

    // Use total_amount_charged if available, fallback to amount
    $raw_amount = floatval($eventData['total_amount_charged'] ?? $eventData['amount'] ?? 0);
    $refund_amount = convertToEur($raw_amount, $currency, $date);

    file_put_contents($log_file, date(DATETIME_FORMAT) . " [INFO] Refund: $name (EUR $refund_amount)\n", FILE_APPEND);

    withLock($lock_file, function() use ($name, $refund_amount, $csv_file, $log_file) {
        $supporters = readSupporters($csv_file);

        if (isset($supporters[$name])) {
            $supporters[$name]['amount'] -= $refund_amount;
            if ($supporters[$name]['amount'] < 0) {
                $supporters[$name]['amount'] = 0;
            }
            writeSupporters($supporters, $csv_file);
            file_put_contents($log_file, date(DATETIME_FORMAT) . " [INFO] Updated: $name (refunded EUR $refund_amount, total now: EUR " . $supporters[$name]['amount'] . ")\n", FILE_APPEND);
        } else {
            file_put_contents($log_file, date(DATETIME_FORMAT) . " [WARN] Refund for unknown supporter: $name\n", FILE_APPEND);
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'supporter' => $name, 'refunded' => round($refund_amount, 2)], JSON_UNESCAPED_UNICODE);
    });
}

// ========================================
// LOCKING
// ========================================

function withLock($lock_file, $callback) {
    $lock_fp = fopen($lock_file, 'c');
    if (!flock($lock_fp, LOCK_EX)) {
        http_response_code(500);
        die(json_encode(['error' => 'Could not acquire lock'], JSON_UNESCAPED_UNICODE));
    }

    try {
        $callback();
    } finally {
        flock($lock_fp, LOCK_UN);
        fclose($lock_fp);
    }
}
