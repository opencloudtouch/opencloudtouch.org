<?php
// BuyMeACoffee Webhook Handler for OpenCloudTouch Supporters
// Handles all BMC event types, archives events, updates supporters.csv

require_once __DIR__ . '/.env.php';

mb_internal_encoding('UTF-8');

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
$archive_filename = sprintf(
    '%s/%s_%s_%s.json',
    $events_dir,
    date('Y-m-d_H-i-s'),
    $event_id,
    $event_type
);
file_put_contents($archive_filename, json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'event_id' => $event_id,
    'event_type' => $event_type,
    'live_mode' => $live_mode,
    'headers' => getallheaders(),
    'payload' => $data
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Debug log
file_put_contents($debug_log, date('Y-m-d H:i:s') . " === NEW REQUEST ===\n", FILE_APPEND);
file_put_contents($debug_log, "Event: $event_type (ID: $event_id)\n", FILE_APPEND);
file_put_contents($debug_log, "Archived: $archive_filename\n", FILE_APPEND);

// Signature verification (skip for test events)
if (!$is_test) {
    $signature = $_SERVER['HTTP_X_SIGNATURE_SHA256'] ?? '';
    $expected_signature = hash_hmac('sha256', $payload, BMC_WEBHOOK_SECRET);

    if (empty($signature) || !hash_equals($expected_signature, $signature)) {
        http_response_code(403);
        file_put_contents($log_file, date('Y-m-d H:i:s') . " [ERROR] Invalid signature for event $event_id\n", FILE_APPEND);
        die(json_encode(['error' => 'Invalid signature'], JSON_UNESCAPED_UNICODE));
    }
}

// ========================================
// CURRENCY CONVERSION
// ========================================

function convertToEur($amount, $currency, $date) {
    if ($currency === 'EUR') return $amount;

    // Historical USD to EUR rates (ECB)
    $usdToEur = [
        '2026-06-02' => 0.8588,
        '2026-06-01' => 0.8588,
        '2026-05-28' => 0.8560,
        '2026-05-10' => 0.8500,
    ];

    if ($currency === 'USD') {
        $rate = $usdToEur[$date] ?? 0.85;
        return round($amount * $rate, 2);
    }

    // Unknown currency - store as-is, log warning
    return $amount;
}

// ========================================
// CSV HELPERS
// ========================================

function read_supporters($csv_file) {
    $supporters = [];
    if (!file_exists($csv_file)) return $supporters;

    $csv_content = file_get_contents($csv_file);

    // Remove UTF-8 BOM if present
    $bom = pack('H*', 'EFBBBF');
    if (substr($csv_content, 0, 3) === $bom) {
        $csv_content = substr($csv_content, 3);
    }

    $lines = array_filter(array_map('trim', explode("\n", $csv_content)));
    array_shift($lines); // Remove header

    foreach ($lines as $line) {
        $row = str_getcsv($line);
        if (count($row) >= 5) {
            $supporters[$row[0]] = [
                'type' => $row[1],
                'amount' => (float)$row[2],
                'monthly' => (float)$row[3],
                'date' => $row[4]
            ];
        }
    }

    return $supporters;
}

function write_supporters($supporters, $csv_file) {
    // Sort by total DESC, then date ASC
    uasort($supporters, function($a, $b) {
        $totalA = $a['amount'] + $a['monthly'];
        $totalB = $b['amount'] + $b['monthly'];
        if ($totalA !== $totalB) {
            return $totalB <=> $totalA;
        }
        return $a['date'] <=> $b['date'];
    });

    $fp = fopen($csv_file, 'w');
    fputcsv($fp, ['name', 'type', 'amount', 'monthlyAmount', 'firstSupportDate']);

    foreach ($supporters as $n => $s) {
        fputcsv($fp, [
            $n,
            $s['type'],
            $s['amount'],
            $s['monthly'],
            $s['date']
        ]);
    }

    fclose($fp);
}

// ========================================
// EVENT ROUTING
// ========================================

switch ($event_type) {
    case 'donation.created':
        handle_donation($data, $csv_file, $lock_file, $log_file);
        break;

    case 'recurring_donation.started':
        handle_subscription_started($data, $csv_file, $lock_file, $log_file);
        break;

    case 'recurring_donation.cancelled':
        handle_subscription_cancelled($data, $csv_file, $lock_file, $log_file);
        break;

    case 'recurring_donation.updated':
        handle_subscription_updated($data, $csv_file, $lock_file, $log_file);
        break;

    case 'donation.refunded':
    case 'extra_purchase.refunded':
    case 'wishlist_payment.refunded':
        handle_refund($data, $csv_file, $lock_file, $log_file);
        break;

    default:
        file_put_contents($log_file, date('Y-m-d H:i:s') . " [INFO] Archived unhandled event: $event_type\n", FILE_APPEND);
        http_response_code(200);
        echo json_encode(['status' => 'archived', 'event' => $event_type], JSON_UNESCAPED_UNICODE);
        exit;
}

// ========================================
// HANDLERS
// ========================================

function handle_donation($data, $csv_file, $lock_file, $log_file) {
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

    file_put_contents($log_file, date('Y-m-d H:i:s') . " [INFO] Donation: $name (EUR $amount, was $currency $raw_amount)\n", FILE_APPEND);

    // donation.created is ALWAYS treated as a one-time amount addition.
    // BMC sends this for both one-time AND first recurring payments.
    // The recurring_donation.started event sets the monthly rate separately.
    with_lock($lock_file, function() use ($name, $amount, $date, $csv_file, $log_file) {
        $supporters = read_supporters($csv_file);

        if (isset($supporters[$name])) {
            $supporters[$name]['amount'] += $amount;
            file_put_contents($log_file, date('Y-m-d H:i:s') . " [INFO] Updated: $name (total: EUR " . $supporters[$name]['amount'] . ")\n", FILE_APPEND);
        } else {
            $supporters[$name] = [
                'type' => 'one-time',
                'amount' => $amount,
                'monthly' => 0,
                'date' => $date
            ];
            file_put_contents($log_file, date('Y-m-d H:i:s') . " [INFO] Added: $name\n", FILE_APPEND);
        }

        write_supporters($supporters, $csv_file);

        http_response_code(200);
        echo json_encode(['success' => true, 'supporter' => $name, 'amount' => $amount], JSON_UNESCAPED_UNICODE);
    });
}

function handle_subscription_started($data, $csv_file, $lock_file, $log_file) {
    $eventData = $data['data'] ?? $data;
    $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');
    $currency = $eventData['currency'] ?? 'EUR';
    $raw_amount = floatval($eventData['amount'] ?? 0);

    // Extract date from started_at timestamp
    $started_at = $eventData['started_at'] ?? null;
    $date = $started_at ? date('Y-m-d', $started_at) : date('Y-m-d');

    $monthly_rate = convertToEur($raw_amount, $currency, $date);

    file_put_contents($log_file, date('Y-m-d H:i:s') . " [INFO] Subscription started: $name (EUR $monthly_rate/month)\n", FILE_APPEND);

    with_lock($lock_file, function() use ($name, $monthly_rate, $date, $csv_file, $log_file) {
        $supporters = read_supporters($csv_file);

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

        write_supporters($supporters, $csv_file);

        file_put_contents($log_file, date('Y-m-d H:i:s') . " [INFO] Updated: $name (monthly: EUR $monthly_rate)\n", FILE_APPEND);
        http_response_code(200);
        echo json_encode(['success' => true, 'supporter' => $name, 'monthly' => $monthly_rate], JSON_UNESCAPED_UNICODE);
    });
}

function handle_subscription_cancelled($data, $csv_file, $lock_file, $log_file) {
    $eventData = $data['data'] ?? $data;
    $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');

    file_put_contents($log_file, date('Y-m-d H:i:s') . " [INFO] Subscription cancelled: $name\n", FILE_APPEND);

    with_lock($lock_file, function() use ($name, $csv_file, $log_file) {
        $supporters = read_supporters($csv_file);

        if (isset($supporters[$name])) {
            $supporters[$name]['monthly'] = 0;
            $supporters[$name]['type'] = 'one-time';
            write_supporters($supporters, $csv_file);
            file_put_contents($log_file, date('Y-m-d H:i:s') . " [INFO] Updated: $name (monthly cancelled, kept amount: EUR " . $supporters[$name]['amount'] . ")\n", FILE_APPEND);
        } else {
            file_put_contents($log_file, date('Y-m-d H:i:s') . " [WARN] Cancel for unknown supporter: $name\n", FILE_APPEND);
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'supporter' => $name, 'action' => 'cancelled'], JSON_UNESCAPED_UNICODE);
    });
}

function handle_subscription_updated($data, $csv_file, $lock_file, $log_file) {
    $eventData = $data['data'] ?? $data;
    $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');
    $currency = $eventData['currency'] ?? 'EUR';
    $raw_amount = floatval($eventData['amount'] ?? 0);
    $date = date('Y-m-d');

    $new_monthly = convertToEur($raw_amount, $currency, $date);

    file_put_contents($log_file, date('Y-m-d H:i:s') . " [INFO] Subscription updated: $name (new rate: EUR $new_monthly/month)\n", FILE_APPEND);

    with_lock($lock_file, function() use ($name, $new_monthly, $csv_file, $log_file) {
        $supporters = read_supporters($csv_file);

        if (isset($supporters[$name])) {
            $supporters[$name]['monthly'] = $new_monthly;
            $supporters[$name]['type'] = 'monthly';
            write_supporters($supporters, $csv_file);
        } else {
            file_put_contents($log_file, date('Y-m-d H:i:s') . " [WARN] Update for unknown supporter: $name\n", FILE_APPEND);
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'supporter' => $name, 'monthly' => $new_monthly], JSON_UNESCAPED_UNICODE);
    });
}

function handle_refund($data, $csv_file, $lock_file, $log_file) {
    $eventData = $data['data'] ?? $data;
    $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');
    $currency = $eventData['currency'] ?? 'EUR';
    $date = date('Y-m-d');

    // Use total_amount_charged if available, fallback to amount
    $raw_amount = floatval($eventData['total_amount_charged'] ?? $eventData['amount'] ?? 0);
    $refund_amount = convertToEur($raw_amount, $currency, $date);

    file_put_contents($log_file, date('Y-m-d H:i:s') . " [INFO] Refund: $name (EUR $refund_amount)\n", FILE_APPEND);

    with_lock($lock_file, function() use ($name, $refund_amount, $csv_file, $log_file) {
        $supporters = read_supporters($csv_file);

        if (isset($supporters[$name])) {
            $supporters[$name]['amount'] -= $refund_amount;
            if ($supporters[$name]['amount'] < 0) {
                $supporters[$name]['amount'] = 0;
            }
            write_supporters($supporters, $csv_file);
            file_put_contents($log_file, date('Y-m-d H:i:s') . " [INFO] Updated: $name (refunded EUR $refund_amount, total now: EUR " . $supporters[$name]['amount'] . ")\n", FILE_APPEND);
        } else {
            file_put_contents($log_file, date('Y-m-d H:i:s') . " [WARN] Refund for unknown supporter: $name\n", FILE_APPEND);
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'supporter' => $name, 'refunded' => $refund_amount], JSON_UNESCAPED_UNICODE);
    });
}

// ========================================
// LOCKING
// ========================================

function with_lock($lock_file, $callback) {
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
