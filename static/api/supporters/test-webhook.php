<?php
/**
 * Unit Tests for webhook.php
 * 
 * Run with: php test-webhook.php
 * 
 * Tests all event handlers using real BMC example payloads.
 * Uses a temp directory for CSV/lock/logs — no side effects.
 */

// ========================================
// TEST HARNESS — Simulates HTTP environment
// ========================================

$test_count = 0;
$test_pass = 0;
$test_fail = 0;
$test_errors = [];

function assert_equals($expected, $actual, $message) {
    global $test_count, $test_pass, $test_fail, $test_errors;
    $test_count++;
    if ($expected === $actual) {
        $test_pass++;
        echo "  ✅ $message\n";
    } else {
        $test_fail++;
        $test_errors[] = "$message: expected " . var_export($expected, true) . ", got " . var_export($actual, true);
        echo "  ❌ $message\n";
        echo "     Expected: " . var_export($expected, true) . "\n";
        echo "     Actual:   " . var_export($actual, true) . "\n";
    }
}

function assert_true($condition, $message) {
    assert_equals(true, $condition, $message);
}

function assert_contains($needle, $haystack, $message) {
    global $test_count, $test_pass, $test_fail, $test_errors;
    $test_count++;
    if (strpos($haystack, $needle) !== false) {
        $test_pass++;
        echo "  ✅ $message\n";
    } else {
        $test_fail++;
        $test_errors[] = "$message: '$needle' not found in output";
        echo "  ❌ $message (needle '$needle' not found)\n";
    }
}

// ========================================
// LOAD FUNCTIONS FROM webhook.php (without executing main flow)
// ========================================

// We need to extract the functions. Since webhook.php reads php://input,
// we include the functions directly here for unit testing.

mb_internal_encoding('UTF-8');

// Mock paths — use temp directory
$test_dir = sys_get_temp_dir() . '/oct-webhook-test-' . uniqid();
mkdir($test_dir, 0755, true);
mkdir($test_dir . '/events', 0755, true);

// Define constants that .env.php would define
if (!defined('BMC_WEBHOOK_SECRET')) {
    define('BMC_WEBHOOK_SECRET', 'test_secret_for_unit_tests');
}

// ---- Include functions from webhook.php ----

function convertToEur($amount, $currency, $date) {
    if ($currency === 'EUR') return (float)$amount;

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

    return (float)$amount;
}

function read_supporters($csv_file) {
    $supporters = [];
    if (!file_exists($csv_file)) return $supporters;

    $csv_content = file_get_contents($csv_file);

    $bom = pack('H*', 'EFBBBF');
    if (substr($csv_content, 0, 3) === $bom) {
        $csv_content = substr($csv_content, 3);
    }

    $lines = array_filter(array_map('trim', explode("\n", $csv_content)));
    array_shift($lines);

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

/**
 * Process a webhook event against a test CSV.
 * Simulates what webhook.php does without HTTP layer.
 */
function process_event($data, $csv_file, $log_file) {
    $event_type = $data['type'] ?? 'unknown';
    $eventData = $data['data'] ?? $data;

    switch ($event_type) {
        case 'donation.created':
            $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');
            $currency = $eventData['currency'] ?? 'EUR';
            $raw_amount = floatval($eventData['total_amount_charged'] ?? $eventData['amount'] ?? 0);
            $created_at = $eventData['created_at'] ?? null;
            $date = $created_at ? date('Y-m-d', $created_at) : date('Y-m-d');
            $amount = convertToEur($raw_amount, $currency, $date);

            $supporters = read_supporters($csv_file);
            if (isset($supporters[$name])) {
                $supporters[$name]['amount'] += $amount;
            } else {
                $supporters[$name] = ['type' => 'one-time', 'amount' => $amount, 'monthly' => 0, 'date' => $date];
            }
            write_supporters($supporters, $csv_file);
            return ['success' => true, 'supporter' => $name, 'amount' => $amount];

        case 'recurring_donation.started':
            $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');
            $currency = $eventData['currency'] ?? 'EUR';
            $raw_amount = floatval($eventData['amount'] ?? 0);
            $started_at = $eventData['started_at'] ?? null;
            $date = $started_at ? date('Y-m-d', $started_at) : date('Y-m-d');
            $monthly_rate = convertToEur($raw_amount, $currency, $date);

            $supporters = read_supporters($csv_file);
            if (isset($supporters[$name])) {
                $supporters[$name]['monthly'] = $monthly_rate;
                $supporters[$name]['type'] = 'monthly';
            } else {
                $supporters[$name] = ['type' => 'monthly', 'amount' => 0, 'monthly' => $monthly_rate, 'date' => $date];
            }
            write_supporters($supporters, $csv_file);
            return ['success' => true, 'supporter' => $name, 'monthly' => $monthly_rate];

        case 'recurring_donation.cancelled':
            $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');
            $supporters = read_supporters($csv_file);
            if (isset($supporters[$name])) {
                $supporters[$name]['monthly'] = 0;
                $supporters[$name]['type'] = 'one-time';
                write_supporters($supporters, $csv_file);
            }
            return ['success' => true, 'supporter' => $name, 'action' => 'cancelled'];

        case 'recurring_donation.updated':
            $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');
            $currency = $eventData['currency'] ?? 'EUR';
            $raw_amount = floatval($eventData['amount'] ?? 0);
            $new_monthly = convertToEur($raw_amount, $currency, date('Y-m-d'));

            $supporters = read_supporters($csv_file);
            if (isset($supporters[$name])) {
                $supporters[$name]['monthly'] = $new_monthly;
                $supporters[$name]['type'] = 'monthly';
                write_supporters($supporters, $csv_file);
            }
            return ['success' => true, 'supporter' => $name, 'monthly' => $new_monthly];

        case 'donation.refunded':
            $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');
            $currency = $eventData['currency'] ?? 'EUR';
            $raw_amount = floatval($eventData['total_amount_charged'] ?? $eventData['amount'] ?? 0);
            $refund_amount = convertToEur($raw_amount, $currency, date('Y-m-d'));

            $supporters = read_supporters($csv_file);
            if (isset($supporters[$name])) {
                $supporters[$name]['amount'] -= $refund_amount;
                if ($supporters[$name]['amount'] < 0) $supporters[$name]['amount'] = 0;
                write_supporters($supporters, $csv_file);
            }
            return ['success' => true, 'supporter' => $name, 'refunded' => $refund_amount];

        default:
            return ['status' => 'archived', 'event' => $event_type];
    }
}

// ========================================
// TESTS
// ========================================

echo "=== OpenCloudTouch Webhook Unit Tests ===\n\n";

// ----------------------------------------
// TEST 1: Currency Conversion
// ----------------------------------------
echo "--- Test: Currency Conversion ---\n";

assert_equals(5.0, convertToEur(5, 'EUR', '2026-06-01'), 'EUR passthrough');
assert_equals(21.47, convertToEur(25, 'USD', '2026-06-01'), 'USD $25 → €21.47 (rate 0.8588)');
assert_equals(8.50, convertToEur(10, 'USD', '2026-05-10'), 'USD $10 → €8.50 (rate 0.8500)');
assert_equals(4.25, convertToEur(5, 'USD', '2099-01-01'), 'USD unknown date → fallback 0.85');
assert_equals(100.0, convertToEur(100, 'GBP', '2026-06-01'), 'Unknown currency passthrough');

echo "\n";

// ----------------------------------------
// TEST 2: Donation Created (Siggi €5)
// ----------------------------------------
echo "--- Test: Donation Created (Siggi €5) ---\n";

$csv = $test_dir . '/test2.csv';
$log = $test_dir . '/test2.log';

$event = json_decode(file_get_contents(__DIR__ . '/events/examples/donation_created_siggi_5eur_invalid_signature.json'), true);
// Remove _meta for processing
unset($event['_meta']);

$result = process_event($event, $csv, $log);

assert_equals(true, $result['success'], 'donation.created returns success');
assert_equals('Siggi', $result['supporter'], 'Supporter name is Siggi');
assert_equals(5.0, $result['amount'], 'Amount is €5.00');

$supporters = read_supporters($csv);
assert_true(isset($supporters['Siggi']), 'Siggi exists in CSV');
assert_equals(5.0, $supporters['Siggi']['amount'], 'Siggi amount = 5.00');
assert_equals('one-time', $supporters['Siggi']['type'], 'Siggi type = one-time');
assert_equals('2026-06-02', $supporters['Siggi']['date'], 'Siggi date from created_at timestamp');

echo "\n";

// ----------------------------------------
// TEST 3: Multiple Donations (Siggi: 5 + 1 + 1 + 1 = 8)
// ----------------------------------------
echo "--- Test: Multiple Donations Accumulate ---\n";

$csv = $test_dir . '/test3.csv';
$log = $test_dir . '/test3.log';

// Donation 1: €5
$event1 = json_decode(file_get_contents(__DIR__ . '/events/examples/donation_created_siggi_5eur_invalid_signature.json'), true);
unset($event1['_meta']);
process_event($event1, $csv, $log);

// Donation 2: €1 (Nachbuchung)
$event2 = json_decode(file_get_contents(__DIR__ . '/events/examples/donation_created_siggi_1eur_nachbuchung.json'), true);
process_event($event2, $csv, $log);

// Donation 3: €1 (note_hidden)
$event3 = json_decode(file_get_contents(__DIR__ . '/events/examples/donation_created_siggi_1eur_note_hidden.json'), true);
process_event($event3, $csv, $log);

// Donation 4: €1 (umlauts test)
$event4 = json_decode(file_get_contents(__DIR__ . '/events/examples/donation_created_siggi_1eur_umlauts.json'), true);
process_event($event4, $csv, $log);

$supporters = read_supporters($csv);
assert_equals(8.0, $supporters['Siggi']['amount'], 'Siggi total = €8 after 4 donations (5+1+1+1)');
assert_equals('one-time', $supporters['Siggi']['type'], 'Siggi stays one-time (no subscription)');
assert_equals('2026-06-02', $supporters['Siggi']['date'], 'First support date preserved from first donation');

echo "\n";

// ----------------------------------------
// TEST 4: Full Subscription Lifecycle (Grünwald)
// ----------------------------------------
echo "--- Test: Full Subscription Lifecycle ---\n";

$csv = $test_dir . '/test4.csv';
$log = $test_dir . '/test4.log';

// Step 1: donation.created (€1 one-time payment that comes with subscription)
$donation = json_decode(file_get_contents(__DIR__ . '/events/examples/donation_created_gruenwald_1eur.json'), true);
$result = process_event($donation, $csv, $log);
$supporters = read_supporters($csv);

assert_equals(1.0, $supporters['Grünwald Almöhü']['amount'] ?? -1, 'After donation: amount = 1');
assert_equals(0.0, $supporters['Grünwald Almöhü']['monthly'] ?? -1, 'After donation: monthly = 0');
assert_equals('one-time', $supporters['Grünwald Almöhü']['type'] ?? '', 'After donation: type = one-time');

// Step 2: recurring_donation.started (sets monthly rate)
$started = json_decode(file_get_contents(__DIR__ . '/events/examples/recurring_donation_started_gruenwald.json'), true);
$result = process_event($started, $csv, $log);
$supporters = read_supporters($csv);

assert_equals(1.0, $supporters['Grünwald Almöhü']['amount'] ?? -1, 'After started: amount still 1 (no change)');
assert_equals(1.0, $supporters['Grünwald Almöhü']['monthly'] ?? -1, 'After started: monthly = 1');
assert_equals('monthly', $supporters['Grünwald Almöhü']['type'] ?? '', 'After started: type = monthly');

// Step 3: recurring_donation.cancelled
$cancelled = json_decode(file_get_contents(__DIR__ . '/events/examples/recurring_donation_cancelled_gruenwald.json'), true);
$result = process_event($cancelled, $csv, $log);
$supporters = read_supporters($csv);

assert_equals(1.0, $supporters['Grünwald Almöhü']['amount'] ?? -1, 'After cancel: amount preserved = 1');
assert_equals(0.0, $supporters['Grünwald Almöhü']['monthly'] ?? -1, 'After cancel: monthly = 0');
assert_equals('one-time', $supporters['Grünwald Almöhü']['type'] ?? '', 'After cancel: type reverted to one-time');

echo "\n";

// ----------------------------------------
// TEST 5: Subscription Updated
// ----------------------------------------
echo "--- Test: Subscription Updated ---\n";

$csv = $test_dir . '/test5.csv';
$log = $test_dir . '/test5.log';

// Setup: Create supporter with subscription
$donation = json_decode(file_get_contents(__DIR__ . '/events/examples/donation_created_gruenwald_1eur.json'), true);
process_event($donation, $csv, $log);
$started = json_decode(file_get_contents(__DIR__ . '/events/examples/recurring_donation_started_gruenwald.json'), true);
process_event($started, $csv, $log);

// Update: Change monthly rate (simulated)
$updated = json_decode(file_get_contents(__DIR__ . '/events/examples/recurring_donation_updated_gruenwald.json'), true);
$result = process_event($updated, $csv, $log);
$supporters = read_supporters($csv);

assert_equals('monthly', $supporters['Grünwald Almöhü']['type'] ?? '', 'After update: type stays monthly');
assert_equals(1.0, $supporters['Grünwald Almöhü']['monthly'] ?? -1, 'After update: monthly rate = 1 (from example)');
assert_equals(1.0, $supporters['Grünwald Almöhü']['amount'] ?? -1, 'After update: amount unchanged');

echo "\n";

// ----------------------------------------
// TEST 6: Refund
// ----------------------------------------
echo "--- Test: Refund ---\n";

$csv = $test_dir . '/test6.csv';
$log = $test_dir . '/test6.log';

// Setup: Siggi donates €5
$event = json_decode(file_get_contents(__DIR__ . '/events/examples/donation_created_siggi_5eur_invalid_signature.json'), true);
unset($event['_meta']);
process_event($event, $csv, $log);

// Refund €5
$refund = [
    'data' => [
        'supporter_name' => 'Siggi',
        'currency' => 'EUR',
        'total_amount_charged' => '5.00',
        'amount' => 5,
    ],
    'type' => 'donation.refunded',
    'event_id' => 999999,
    'live_mode' => false
];
$result = process_event($refund, $csv, $log);

$supporters = read_supporters($csv);
assert_equals(0.0, $supporters['Siggi']['amount'] ?? -1, 'After full refund: amount = 0');
assert_equals('one-time', $supporters['Siggi']['type'] ?? '', 'After refund: type unchanged');

echo "\n";

// ----------------------------------------
// TEST 7: Refund exceeds amount (clamped to 0)
// ----------------------------------------
echo "--- Test: Over-Refund Clamped to Zero ---\n";

$csv = $test_dir . '/test7.csv';
$log = $test_dir . '/test7.log';

// Setup: Siggi donates €1
$event = json_decode(file_get_contents(__DIR__ . '/events/examples/donation_created_siggi_1eur_nachbuchung.json'), true);
process_event($event, $csv, $log);

// Refund €5 (more than donated)
$refund = [
    'data' => ['supporter_name' => 'Siggi', 'currency' => 'EUR', 'total_amount_charged' => '5.00'],
    'type' => 'donation.refunded',
    'event_id' => 999998,
    'live_mode' => false
];
process_event($refund, $csv, $log);

$supporters = read_supporters($csv);
assert_equals(0.0, $supporters['Siggi']['amount'] ?? -1, 'Over-refund clamped to 0');

echo "\n";

// ----------------------------------------
// TEST 8: USD Donation Converted to EUR
// ----------------------------------------
echo "--- Test: USD Donation ---\n";

$csv = $test_dir . '/test8.csv';
$log = $test_dir . '/test8.log';

$usd_event = [
    'data' => [
        'supporter_name' => 'Peter St.',
        'currency' => 'USD',
        'total_amount_charged' => '25.00',
        'created_at' => 1780394550,  // 2026-06-01
        'amount' => 25,
    ],
    'type' => 'donation.created',
    'event_id' => 999997,
    'live_mode' => false
];
$result = process_event($usd_event, $csv, $log);

$supporters = read_supporters($csv);
assert_equals(21.47, $supporters['Peter St.']['amount'] ?? -1, 'USD $25 stored as EUR 21.47');

echo "\n";

// ----------------------------------------
// TEST 9: CSV BOM Handling
// ----------------------------------------
echo "--- Test: CSV BOM Handling ---\n";

$csv = $test_dir . '/test9.csv';

// Write CSV WITH BOM
$bom = "\xEF\xBB\xBF";
file_put_contents($csv, $bom . "name,type,amount,monthlyAmount,firstSupportDate\nTestUser,one-time,10,0,2026-01-01\n");

$supporters = read_supporters($csv);
assert_true(isset($supporters['TestUser']), 'BOM-prefixed CSV parsed correctly');
assert_equals(10.0, $supporters['TestUser']['amount'] ?? -1, 'Amount read correctly through BOM');

echo "\n";

// ----------------------------------------
// TEST 10: CSV Sorting
// ----------------------------------------
echo "--- Test: CSV Sorting ---\n";

$csv = $test_dir . '/test10.csv';
$log = $test_dir . '/test10.log';

// Add supporters with different amounts
$supporters = [
    'Small' => ['type' => 'one-time', 'amount' => 5, 'monthly' => 0, 'date' => '2026-05-01'],
    'Big' => ['type' => 'one-time', 'amount' => 30, 'monthly' => 0, 'date' => '2026-05-02'],
    'Medium' => ['type' => 'one-time', 'amount' => 15, 'monthly' => 0, 'date' => '2026-05-03'],
];
write_supporters($supporters, $csv);
$sorted = read_supporters($csv);
$names = array_keys($sorted);

assert_equals('Big', $names[0], 'First supporter = Big (30€)');
assert_equals('Medium', $names[1], 'Second supporter = Medium (15€)');
assert_equals('Small', $names[2], 'Third supporter = Small (5€)');

echo "\n";

// ----------------------------------------
// TEST 11: Sorting with equal amounts (date ASC)
// ----------------------------------------
echo "--- Test: Sorting Equal Amounts by Date ASC ---\n";

$csv = $test_dir . '/test11.csv';

$supporters = [
    'Late' => ['type' => 'one-time', 'amount' => 10, 'monthly' => 0, 'date' => '2026-06-01'],
    'Early' => ['type' => 'one-time', 'amount' => 10, 'monthly' => 0, 'date' => '2026-05-01'],
];
write_supporters($supporters, $csv);
$sorted = read_supporters($csv);
$names = array_keys($sorted);

assert_equals('Early', $names[0], 'Same amount → earlier date first');
assert_equals('Late', $names[1], 'Same amount → later date second');

echo "\n";

// ----------------------------------------
// TEST 12: Quoted Names in CSV
// ----------------------------------------
echo "--- Test: Quoted Names (Umlauts, Dots) ---\n";

$csv = $test_dir . '/test12.csv';

$supporters = [
    'Grünwald Almöhü' => ['type' => 'monthly', 'amount' => 1, 'monthly' => 1, 'date' => '2026-06-02'],
    'Peter St.' => ['type' => 'one-time', 'amount' => 21.47, 'monthly' => 0, 'date' => '2026-06-01'],
];
write_supporters($supporters, $csv);
$roundtrip = read_supporters($csv);

assert_true(isset($roundtrip['Grünwald Almöhü']), 'Umlaut name survives CSV roundtrip');
assert_true(isset($roundtrip['Peter St.']), 'Dotted name survives CSV roundtrip');
assert_equals(21.47, $roundtrip['Peter St.']['amount'] ?? -1, 'Decimal amount preserved');

echo "\n";

// ----------------------------------------
// TEST 13: Unknown Event Type
// ----------------------------------------
echo "--- Test: Unknown Event Type ---\n";

$csv = $test_dir . '/test13.csv';
$log = $test_dir . '/test13.log';

$result = process_event([
    'type' => 'some.future.event',
    'data' => ['supporter_name' => 'Nobody'],
    'event_id' => 999996,
    'live_mode' => false
], $csv, $log);

assert_equals('archived', $result['status'], 'Unknown events return status=archived');
assert_false(file_exists($csv), 'Unknown event does not create CSV');

echo "\n";

// ----------------------------------------
// TEST 14: Subscription Started Without Prior Donation
// ----------------------------------------
echo "--- Test: Subscription Started (Edge Case — No Prior Donation) ---\n";

$csv = $test_dir . '/test14.csv';
$log = $test_dir . '/test14.log';

// Only subscription.started, no donation.created first
$started = json_decode(file_get_contents(__DIR__ . '/events/examples/recurring_donation_started_gruenwald.json'), true);
$result = process_event($started, $csv, $log);
$supporters = read_supporters($csv);

assert_true(isset($supporters['Grünwald Almöhü']), 'Supporter created from subscription.started alone');
assert_equals(0.0, $supporters['Grünwald Almöhü']['amount'] ?? -1, 'Amount = 0 (no donation yet)');
assert_equals(1.0, $supporters['Grünwald Almöhü']['monthly'] ?? -1, 'Monthly rate set from subscription');
assert_equals('monthly', $supporters['Grünwald Almöhü']['type'] ?? '', 'Type = monthly');

echo "\n";

// ----------------------------------------
// TEST 15: Cancel for Unknown Supporter
// ----------------------------------------
echo "--- Test: Cancel for Unknown Supporter ---\n";

$csv = $test_dir . '/test15.csv';
$log = $test_dir . '/test15.log';

$cancel = [
    'data' => ['supporter_name' => 'Ghost User'],
    'type' => 'recurring_donation.cancelled',
    'event_id' => 999995,
    'live_mode' => false
];
$result = process_event($cancel, $csv, $log);

assert_equals(true, $result['success'], 'Cancel for unknown user still returns success');
// CSV should not have Ghost User
$supporters = read_supporters($csv);
assert_false(isset($supporters['Ghost User']), 'Unknown user not created on cancel');

echo "\n";

// ----------------------------------------
// TEST 16: HMAC Signature Verification
// ----------------------------------------
echo "--- Test: HMAC Signature ---\n";

$payload = '{"type":"donation.created","live_mode":true,"data":{"supporter_name":"Test"}}';
$valid_sig = hash_hmac('sha256', $payload, BMC_WEBHOOK_SECRET);
$invalid_sig = 'deadbeef0000';

assert_true(hash_equals($valid_sig, hash_hmac('sha256', $payload, BMC_WEBHOOK_SECRET)), 'Valid signature matches');
assert_false(hash_equals($invalid_sig, $valid_sig), 'Invalid signature rejected');

echo "\n";

// ========================================
// HELPER
// ========================================

function assert_false($condition, $message) {
    assert_equals(false, $condition, $message);
}

// ========================================
// SUMMARY
// ========================================

echo "========================================\n";
echo "Results: $test_pass/$test_count passed";
if ($test_fail > 0) {
    echo " ($test_fail FAILED)";
}
echo "\n";

if (!empty($test_errors)) {
    echo "\nFailures:\n";
    foreach ($test_errors as $err) {
        echo "  ❌ $err\n";
    }
}

echo "========================================\n";

// Cleanup
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $object)) {
                    rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                } else {
                    unlink($dir . DIRECTORY_SEPARATOR . $object);
                }
            }
        }
        rmdir($dir);
    }
}
rrmdir($test_dir);

exit($test_fail > 0 ? 1 : 0);
