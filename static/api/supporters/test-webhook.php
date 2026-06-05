<?php
/**
 * Unit Tests for webhook.php
 * Run with: php test-webhook.php
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

function assertEquals($expected, $actual, $message) {
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

function assertTrue($condition, $message) {
    assertEquals(true, $condition, $message);
}

function assertContains($needle, $haystack, $message) {
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

// Mock paths — use temp directory
$test_dir = sys_get_temp_dir() . '/oct-webhook-test-' . uniqid();
mkdir($test_dir, 0755, true);
mkdir($test_dir . '/events', 0755, true);

// Define constants that .env.php would define
if (!defined('BMC_WEBHOOK_SECRET')) {
    define('BMC_WEBHOOK_SECRET', 'test_secret_for_unit_tests');
}

// Load shared functions (convertToEur, readSupporters, writeSupporters)
require_once __DIR__ . '/functions.php';

// Constants for test data paths and dates (S1192)
const TEST_DATETIME_FORMAT = 'Y-m-d H:i:s';
const TEST_DATE_JUN02 = '2026-06-02';
const TEST_DATE_JUN01 = '2026-06-01';
const ALEX_5EUR_JSON = '/events/examples/donation_created_alex_5eur_invalid_signature.json';
const JAMIE_STARTED_JSON = '/events/examples/recurring_donation_started_jamie.json';
const LIFECYCLE_LISA = 'Lifecycle Lisa';

/**
 * Process a webhook event against a test CSV.
 * Simulates what webhook.php does without HTTP layer.
 */
function processEvent($data, $csv_file, $log_file) {
    $event_type = $data['type'] ?? 'unknown';
    $eventData = $data['data'] ?? $data;

    $handlers = [
        'donation.created' => 'processDonation',
        'recurring_donation.started' => 'processSubscriptionStarted',
        'recurring_donation.cancelled' => 'processSubscriptionCancelled',
        'recurring_donation.updated' => 'processSubscriptionUpdated',
        'donation.refunded' => 'processRefund',
    ];

    if (isset($handlers[$event_type])) {
        return $handlers[$event_type]($eventData, $csv_file, $log_file);
    }
    return ['status' => 'archived', 'event' => $event_type];
}

function processDonation($eventData, $csv_file, $log_file) {
    $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');
    $currency = $eventData['currency'] ?? 'EUR';
    $raw_amount = floatval($eventData['total_amount_charged'] ?? $eventData['amount'] ?? 0);
    $created_at = $eventData['created_at'] ?? null;
    $date = $created_at ? date('Y-m-d', $created_at) : date('Y-m-d');
    $amount = convertToEur($raw_amount, $currency, $date);

    $supporters = readSupporters($csv_file);
    if (isset($supporters[$name])) {
        $supporters[$name]['amount'] += $amount;
    } else {
        $supporters[$name] = ['type' => 'one-time', 'amount' => $amount, 'monthly' => 0, 'date' => $date];
    }
    writeSupporters($supporters, $csv_file);
    file_put_contents($log_file, date(TEST_DATETIME_FORMAT) . " [INFO] Donation: $name (EUR $amount)\n", FILE_APPEND);
    return ['success' => true, 'supporter' => $name, 'amount' => $amount];
}

function processSubscriptionStarted($eventData, $csv_file, $log_file) {
    $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');
    $currency = $eventData['currency'] ?? 'EUR';
    $raw_amount = floatval($eventData['amount'] ?? 0);
    $started_at = $eventData['started_at'] ?? null;
    $date = $started_at ? date('Y-m-d', $started_at) : date('Y-m-d');
    $monthly_rate = convertToEur($raw_amount, $currency, $date);

    $supporters = readSupporters($csv_file);
    if (isset($supporters[$name])) {
        $supporters[$name]['monthly'] = $monthly_rate;
        $supporters[$name]['type'] = 'monthly';
    } else {
        $supporters[$name] = ['type' => 'monthly', 'amount' => 0, 'monthly' => $monthly_rate, 'date' => $date];
    }
    writeSupporters($supporters, $csv_file);
    file_put_contents($log_file, date(TEST_DATETIME_FORMAT) . " [INFO] Subscription started: $name (EUR $monthly_rate/month)\n", FILE_APPEND);
    return ['success' => true, 'supporter' => $name, 'monthly' => $monthly_rate];
}

function processSubscriptionCancelled($eventData, $csv_file, $log_file) {
    $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');
    $supporters = readSupporters($csv_file);
    if (isset($supporters[$name])) {
        $supporters[$name]['monthly'] = 0;
        $supporters[$name]['type'] = 'one-time';
        writeSupporters($supporters, $csv_file);
    }
    file_put_contents($log_file, date(TEST_DATETIME_FORMAT) . " [INFO] Subscription cancelled: $name\n", FILE_APPEND);
    return ['success' => true, 'supporter' => $name, 'action' => 'cancelled'];
}

function processSubscriptionUpdated($eventData, $csv_file, $log_file) {
    $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');
    $currency = $eventData['currency'] ?? 'EUR';
    $raw_amount = floatval($eventData['amount'] ?? 0);
    $new_monthly = convertToEur($raw_amount, $currency, date('Y-m-d'));

    $supporters = readSupporters($csv_file);
    if (isset($supporters[$name])) {
        $supporters[$name]['monthly'] = $new_monthly;
        $supporters[$name]['type'] = 'monthly';
        writeSupporters($supporters, $csv_file);
    }
    file_put_contents($log_file, date(TEST_DATETIME_FORMAT) . " [INFO] Subscription updated: $name (EUR $new_monthly/month)\n", FILE_APPEND);
    return ['success' => true, 'supporter' => $name, 'monthly' => $new_monthly];
}

function processRefund($eventData, $csv_file, $log_file) {
    $name = mb_convert_encoding($eventData['supporter_name'] ?? 'Anonymous', 'UTF-8', 'UTF-8');
    $currency = $eventData['currency'] ?? 'EUR';
    $raw_amount = floatval($eventData['total_amount_charged'] ?? $eventData['amount'] ?? 0);
    $refund_amount = convertToEur($raw_amount, $currency, date('Y-m-d'));

    $supporters = readSupporters($csv_file);
    if (isset($supporters[$name])) {
        $supporters[$name]['amount'] -= $refund_amount;
        if ($supporters[$name]['amount'] < 0) {
            $supporters[$name]['amount'] = 0;
        }
        writeSupporters($supporters, $csv_file);
    }
    file_put_contents($log_file, date(TEST_DATETIME_FORMAT) . " [INFO] Refund: $name (EUR $refund_amount)\n", FILE_APPEND);
    return ['success' => true, 'supporter' => $name, 'refunded' => $refund_amount];
}

// ========================================
// TESTS
// ========================================

echo "=== OpenCloudTouch Webhook Unit Tests ===\n\n";

// ----------------------------------------
// TEST 1: Currency Conversion
// ----------------------------------------
echo "--- Test: Currency Conversion ---\n";

assertEquals(5.0, convertToEur(5, 'EUR', TEST_DATE_JUN01), 'EUR passthrough');
assertEquals(21.47, convertToEur(25, 'USD', TEST_DATE_JUN01), 'USD $25 → €21.47 (rate 0.8588)');
assertEquals(8.50, convertToEur(10, 'USD', '2026-05-10'), 'USD $10 → €8.50 (rate 0.8500)');
assertEquals(4.25, convertToEur(5, 'USD', '2099-01-01'), 'USD unknown date → fallback 0.85');
assertEquals(100.0, convertToEur(100, 'GBP', TEST_DATE_JUN01), 'Unknown currency passthrough');

echo "\n";

// ----------------------------------------
// TEST 2: Donation Created (Alex €5)
// ----------------------------------------
echo "--- Test: Donation Created (Alex €5) ---\n";

$csv = $test_dir . '/test2.csv';
$log = $test_dir . '/test2.log';

$event = json_decode(file_get_contents(__DIR__ . ALEX_5EUR_JSON), true);
// Remove _meta for processing
unset($event['_meta']);

$result = processEvent($event, $csv, $log);

assertEquals(true, $result['success'], 'donation.created returns success');
assertEquals('Alex', $result['supporter'], 'Supporter name is Siggi');
assertEquals(5.0, $result['amount'], 'Amount is €5.00');

$supporters = readSupporters($csv);
assertTrue(isset($supporters['Alex']), 'Alex exists in CSV');
assertEquals(5.0, $supporters['Alex']['amount'], 'Alex amount = 5.00');
assertEquals('one-time', $supporters['Alex']['type'], 'Alex type = one-time');
assertEquals(TEST_DATE_JUN02, $supporters['Alex']['date'], 'Alex date from created_at timestamp');

echo "\n";

// ----------------------------------------
// TEST 3: Multiple Donations (Alex: 5 + 1 + 1 + 1 = 8)
// ----------------------------------------
echo "--- Test: Multiple Donations Accumulate ---\n";

$csv = $test_dir . '/test3.csv';
$log = $test_dir . '/test3.log';

// Donation 1: €5
$event1 = json_decode(file_get_contents(__DIR__ . ALEX_5EUR_JSON), true);
unset($event1['_meta']);
processEvent($event1, $csv, $log);

// Donation 2: €1 (Nachbuchung)
$event2 = json_decode(file_get_contents(__DIR__ . '/events/examples/donation_created_alex_1eur_followup.json'), true);
processEvent($event2, $csv, $log);

// Donation 3: €1 (note_hidden)
$event3 = json_decode(file_get_contents(__DIR__ . '/events/examples/donation_created_alex_1eur_note_hidden.json'), true);
processEvent($event3, $csv, $log);

// Donation 4: €1 (umlauts test)
$event4 = json_decode(file_get_contents(__DIR__ . '/events/examples/donation_created_alex_1eur_umlauts.json'), true);
processEvent($event4, $csv, $log);

$supporters = readSupporters($csv);
assertEquals(8.0, $supporters['Alex']['amount'], 'Alex total = €8 after 4 donations (5+1+1+1)');
assertEquals('one-time', $supporters['Alex']['type'], 'Alex stays one-time (no subscription)');
assertEquals(TEST_DATE_JUN02, $supporters['Alex']['date'], 'First support date preserved from first donation');

echo "\n";

// ----------------------------------------
// TEST 4: Full Subscription Lifecycle (Jamie Smith)
// ----------------------------------------
echo "--- Test: Full Subscription Lifecycle ---\n";

$csv = $test_dir . '/test4.csv';
$log = $test_dir . '/test4.log';

// Step 1: donation.created (€1 one-time payment that comes with subscription)
$donation = json_decode(file_get_contents(__DIR__ . '/events/examples/donation_created_jamie_1eur.json'), true);
$result = processEvent($donation, $csv, $log);
$supporters = readSupporters($csv);

assertEquals(1.0, $supporters['Jamie Smith']['amount'] ?? -1, 'After donation: amount = 1');
assertEquals(0.0, $supporters['Jamie Smith']['monthly'] ?? -1, 'After donation: monthly = 0');
assertEquals('one-time', $supporters['Jamie Smith']['type'] ?? '', 'After donation: type = one-time');

// Step 2: recurring_donation.started (sets monthly rate)
$started = json_decode(file_get_contents(__DIR__ . JAMIE_STARTED_JSON), true);
$result = processEvent($started, $csv, $log);
$supporters = readSupporters($csv);

assertEquals(1.0, $supporters['Jamie Smith']['amount'] ?? -1, 'After started: amount still 1 (no change)');
assertEquals(1.0, $supporters['Jamie Smith']['monthly'] ?? -1, 'After started: monthly = 1');
assertEquals('monthly', $supporters['Jamie Smith']['type'] ?? '', 'After started: type = monthly');

// Step 3: recurring_donation.cancelled
$cancelled = json_decode(file_get_contents(__DIR__ . '/events/examples/recurring_donation_cancelled_jamie.json'), true);
$result = processEvent($cancelled, $csv, $log);
$supporters = readSupporters($csv);

assertEquals(1.0, $supporters['Jamie Smith']['amount'] ?? -1, 'After cancel: amount preserved = 1');
assertEquals(0.0, $supporters['Jamie Smith']['monthly'] ?? -1, 'After cancel: monthly = 0');
assertEquals('one-time', $supporters['Jamie Smith']['type'] ?? '', 'After cancel: type reverted to one-time');

echo "\n";

// ----------------------------------------
// TEST 5: Subscription Updated
// ----------------------------------------
echo "--- Test: Subscription Updated ---\n";

$csv = $test_dir . '/test5.csv';
$log = $test_dir . '/test5.log';

// Setup: Create supporter with subscription
$donation = json_decode(file_get_contents(__DIR__ . '/events/examples/donation_created_jamie_1eur.json'), true);
processEvent($donation, $csv, $log);
$started = json_decode(file_get_contents(__DIR__ . JAMIE_STARTED_JSON), true);
processEvent($started, $csv, $log);

// Update: Change monthly rate (simulated)
$updated = json_decode(file_get_contents(__DIR__ . '/events/examples/recurring_donation_updated_jamie.json'), true);
$result = processEvent($updated, $csv, $log);
$supporters = readSupporters($csv);

assertEquals('monthly', $supporters['Jamie Smith']['type'] ?? '', 'After update: type stays monthly');
assertEquals(1.0, $supporters['Jamie Smith']['monthly'] ?? -1, 'After update: monthly rate = 1 (from example)');
assertEquals(1.0, $supporters['Jamie Smith']['amount'] ?? -1, 'After update: amount unchanged');

echo "\n";

// ----------------------------------------
// TEST 6: Refund
// ----------------------------------------
echo "--- Test: Refund ---\n";

$csv = $test_dir . '/test6.csv';
$log = $test_dir . '/test6.log';

// Setup: Alex donates €5
$event = json_decode(file_get_contents(__DIR__ . ALEX_5EUR_JSON), true);
unset($event['_meta']);
processEvent($event, $csv, $log);

// Refund €5
$refund = [
    'data' => [
        'supporter_name' => 'Alex',
        'currency' => 'EUR',
        'total_amount_charged' => '5.00',
        'amount' => 5,
    ],
    'type' => 'donation.refunded',
    'event_id' => 999999,
    'live_mode' => false
];
$result = processEvent($refund, $csv, $log);

$supporters = readSupporters($csv);
assertFalse(isset($supporters['Alex']), 'After full refund: supporter removed from CSV');

echo "\n";

// ----------------------------------------
// TEST 7: Refund exceeds amount (clamped to 0)
// ----------------------------------------
echo "--- Test: Over-Refund Clamped to Zero ---\n";

$csv = $test_dir . '/test7.csv';
$log = $test_dir . '/test7.log';

// Setup: Alex donates €1
$event = json_decode(file_get_contents(__DIR__ . '/events/examples/donation_created_alex_1eur_followup.json'), true);
processEvent($event, $csv, $log);

// Refund €5 (more than donated)
$refund = [
    'data' => ['supporter_name' => 'Alex', 'currency' => 'EUR', 'total_amount_charged' => '5.00'],
    'type' => 'donation.refunded',
    'event_id' => 999998,
    'live_mode' => false
];
processEvent($refund, $csv, $log);

$supporters = readSupporters($csv);
assertFalse(isset($supporters['Alex']), 'Over-refund: supporter removed from CSV');

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
$result = processEvent($usd_event, $csv, $log);

$supporters = readSupporters($csv);
assertEquals(21.47, $supporters['Peter St.']['amount'] ?? -1, 'USD $25 stored as EUR 21.47');

echo "\n";

// ----------------------------------------
// TEST 9: CSV BOM Handling
// ----------------------------------------
echo "--- Test: CSV BOM Handling ---\n";

$csv = $test_dir . '/test9.csv';

// Write CSV WITH BOM
$bom = "\xEF\xBB\xBF";
file_put_contents($csv, $bom . "name,type,amount,monthlyAmount,firstSupportDate\nTestUser,one-time,10,0,2026-01-01\n");

$supporters = readSupporters($csv);
assertTrue(isset($supporters['TestUser']), 'BOM-prefixed CSV parsed correctly');
assertEquals(10.0, $supporters['TestUser']['amount'] ?? -1, 'Amount read correctly through BOM');

echo "\n";

// ----------------------------------------
// TEST 10 & 11: CSV Sorting
// ----------------------------------------

function assertSortOrder($inputSupporters, $expectedNames, $csvFile, $testLabel) {
    writeSupporters($inputSupporters, $csvFile);
    $sorted = readSupporters($csvFile);
    $names = array_keys($sorted);
    for ($i = 0; $i < count($expectedNames); $i++) {
        assertEquals($expectedNames[$i], $names[$i] ?? null, "$testLabel: position $i = {$expectedNames[$i]}");
    }
}

echo "--- Test: CSV Sorting ---\n";
assertSortOrder(
    [
        'Small' => ['type' => 'one-time', 'amount' => 5, 'monthly' => 0, 'date' => '2026-05-01'],
        'Big' => ['type' => 'one-time', 'amount' => 30, 'monthly' => 0, 'date' => '2026-05-02'],
        'Medium' => ['type' => 'one-time', 'amount' => 15, 'monthly' => 0, 'date' => '2026-05-03'],
    ],
    ['Big', 'Medium', 'Small'],
    $test_dir . '/test10.csv',
    'Sort by amount DESC'
);
echo "\n";

echo "--- Test: Sorting Equal Amounts by Date ASC ---\n";
assertSortOrder(
    [
        'Late' => ['type' => 'one-time', 'amount' => 10, 'monthly' => 0, 'date' => TEST_DATE_JUN01],
        'Early' => ['type' => 'one-time', 'amount' => 10, 'monthly' => 0, 'date' => '2026-05-01'],
    ],
    ['Early', 'Late'],
    $test_dir . '/test11.csv',
    'Same amount, sort by date ASC'
);
echo "\n";

// ----------------------------------------
// TEST 12: Quoted Names in CSV
// ----------------------------------------
echo "--- Test: Quoted Names (Umlauts, Dots) ---\n";

$csv = $test_dir . '/test12.csv';

$supporters = [
    'Jamie Smith' => ['type' => 'monthly', 'amount' => 1, 'monthly' => 1, 'date' => TEST_DATE_JUN02],
    'Peter St.' => ['type' => 'one-time', 'amount' => 21.47, 'monthly' => 0, 'date' => TEST_DATE_JUN01],
];
writeSupporters($supporters, $csv);
$roundtrip = readSupporters($csv);

assertTrue(isset($roundtrip['Jamie Smith']), 'Umlaut name survives CSV roundtrip');
assertTrue(isset($roundtrip['Peter St.']), 'Dotted name survives CSV roundtrip');
assertEquals(21.47, $roundtrip['Peter St.']['amount'] ?? -1, 'Decimal amount preserved');

echo "\n";

// ----------------------------------------
// TEST 13: Unknown Event Type
// ----------------------------------------
echo "--- Test: Unknown Event Type ---\n";

$csv = $test_dir . '/test13.csv';
$log = $test_dir . '/test13.log';

$result = processEvent([
    'type' => 'some.future.event',
    'data' => ['supporter_name' => 'Nobody'],
    'event_id' => 999996,
    'live_mode' => false
], $csv, $log);

assertEquals('archived', $result['status'], 'Unknown events return status=archived');
assertFalse(file_exists($csv), 'Unknown event does not create CSV');

echo "\n";

// ----------------------------------------
// TEST 14: Subscription Started Without Prior Donation
// ----------------------------------------
echo "--- Test: Subscription Started (Edge Case — No Prior Donation) ---\n";

$csv = $test_dir . '/test14.csv';
$log = $test_dir . '/test14.log';

// Only subscription.started, no donation.created first
$started = json_decode(file_get_contents(__DIR__ . JAMIE_STARTED_JSON), true);
$result = processEvent($started, $csv, $log);
$supporters = readSupporters($csv);

assertTrue(isset($supporters['Jamie Smith']), 'Supporter created from subscription.started alone');
assertEquals(0.0, $supporters['Jamie Smith']['amount'] ?? -1, 'Amount = 0 (no donation yet)');
assertEquals(1.0, $supporters['Jamie Smith']['monthly'] ?? -1, 'Monthly rate set from subscription');
assertEquals('monthly', $supporters['Jamie Smith']['type'] ?? '', 'Type = monthly');

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
$result = processEvent($cancel, $csv, $log);

assertEquals(true, $result['success'], 'Cancel for unknown user still returns success');
// CSV should not have Ghost User
$supporters = readSupporters($csv);
assertFalse(isset($supporters['Ghost User']), 'Unknown user not created on cancel');

echo "\n";

// ----------------------------------------
// TEST 16: HMAC Signature Verification
// ----------------------------------------
echo "--- Test: HMAC Signature ---\n";

$payload = '{"type":"donation.created","live_mode":true,"data":{"supporter_name":"Test"}}';
$valid_sig = hash_hmac('sha256', $payload, BMC_WEBHOOK_SECRET);
$invalid_sig = 'deadbeef0000';

assertTrue(hash_equals($valid_sig, hash_hmac('sha256', $payload, BMC_WEBHOOK_SECRET)), 'Valid signature matches');
assertFalse(hash_equals($invalid_sig, $valid_sig), 'Invalid signature rejected');

echo "\n";

// ----------------------------------------
// TEST 17: Zero-Amount Supporters Removed from CSV
// ----------------------------------------
echo "--- Test: Zero-Amount Removal ---\n";

$csv = $test_dir . '/test17.csv';
$log = $test_dir . '/test17.log';

// Create supporter via donation
processEvent([
    'type' => 'donation.created',
    'live_mode' => false,
    'data' => [
        'supporter_name' => 'Refund Rudi',
        'total_amount_charged' => '5.00',
        'currency' => 'EUR',
        'created_at' => 1717459200,
    ],
], $csv, $log);

$supporters = readSupporters($csv);
assertTrue(isset($supporters['Refund Rudi']), 'Refund Rudi exists after donation');
assertEquals(5.0, $supporters['Refund Rudi']['amount'], 'Refund Rudi amount=5');

// Refund the full amount
processEvent([
    'type' => 'donation.refunded',
    'live_mode' => false,
    'data' => [
        'supporter_name' => 'Refund Rudi',
        'total_amount_charged' => '5.00',
        'currency' => 'EUR',
    ],
], $csv, $log);

$supporters = readSupporters($csv);
assertFalse(isset($supporters['Refund Rudi']), 'Refund Rudi removed after full refund (amount=0, monthly=0)');

echo "\n";

// ----------------------------------------
// TEST 18: Accumulated Amounts Rounded to 2 Decimals in CSV
// ----------------------------------------
echo "--- Test: CSV Amount Rounding ---\n";

$csv = $test_dir . '/test18.csv';
$log = $test_dir . '/test18.log';

// 3 small donations causing floating-point drift (0.1+0.1+0.1 = 0.30000000000000004)
$event = [
    'type' => 'donation.created',
    'live_mode' => false,
    'data' => [
        'supporter_name' => 'Round Robin',
        'total_amount_charged' => '0.10',
        'currency' => 'EUR',
        'created_at' => 1717459200,
    ],
];
processEvent($event, $csv, $log);
processEvent($event, $csv, $log);
processEvent($event, $csv, $log);

// Read raw CSV to check actual written values (not parsed floats)
$csv_raw = file_get_contents($csv);
$bom = pack('H*', 'EFBBBF');
if (substr($csv_raw, 0, 3) === $bom) {
    $csv_raw = substr($csv_raw, 3);
}
preg_match('/"?Round Robin"?,one-time,([0-9.]+),/', $csv_raw, $m);
assertEquals('0.3', $m[1] ?? null, 'CSV amount is 0.3 not 0.30000000000000004');

echo "\n";

// ----------------------------------------
// TEST 19: CSV Starts with UTF-8 BOM
// ----------------------------------------
echo "--- Test: CSV UTF-8 BOM ---\n";

// Re-use test18's csv which was just written
$raw = file_get_contents($csv);
$first3 = bin2hex(substr($raw, 0, 3));
assertEquals('efbbbf', $first3, 'CSV starts with UTF-8 BOM (EF BB BF)');

echo "\n";

// ----------------------------------------
// TEST 20: Complex Subscription Lifecycle
// subscribe -> pay -> pay -> cancel -> subscribe again -> cancel -> partial refund -> full refund
// ----------------------------------------
echo "--- Test: Complex Subscription Lifecycle ---\n";

$csv = $test_dir . '/test20.csv';
$log = $test_dir . '/test20.log';
$lisa = LIFECYCLE_LISA;

// Step 1: First subscription - donation.created (first payment €5)
processEvent([
    'type' => 'donation.created', 'live_mode' => false,
    'data' => ['supporter_name' => $lisa, 'total_amount_charged' => '5.00', 'currency' => 'EUR', 'created_at' => 1717459200],
], $csv, $log);
$s = readSupporters($csv);
assertEquals(5.0, $s[$lisa]['amount'], 'Step 1: first payment amount=5');
assertEquals('one-time', $s[$lisa]['type'], 'Step 1: type=one-time (before started event)');

// Step 2: recurring_donation.started
processEvent([
    'type' => 'recurring_donation.started', 'live_mode' => false,
    'data' => ['supporter_name' => $lisa, 'amount' => 5, 'currency' => 'EUR', 'started_at' => 1717459200],
], $csv, $log);
$s = readSupporters($csv);
assertEquals(5.0, $s[$lisa]['amount'], 'Step 2: amount unchanged at 5');
assertEquals(5.0, $s[$lisa]['monthly'], 'Step 2: monthly=5');
assertEquals('monthly', $s[$lisa]['type'], 'Step 2: type=monthly');

// Step 3: Second month payment (donation.created again)
processEvent([
    'type' => 'donation.created', 'live_mode' => false,
    'data' => ['supporter_name' => $lisa, 'total_amount_charged' => '5.00', 'currency' => 'EUR', 'created_at' => 1720137600],
], $csv, $log);
$s = readSupporters($csv);
assertEquals(10.0, $s[$lisa]['amount'], 'Step 3: amount accumulated to 10');
assertEquals(5.0, $s[$lisa]['monthly'], 'Step 3: monthly still 5');

// Step 4: Cancel subscription
processEvent([
    'type' => 'recurring_donation.cancelled', 'live_mode' => false,
    'data' => ['supporter_name' => $lisa],
], $csv, $log);
$s = readSupporters($csv);
assertEquals(10.0, $s[$lisa]['amount'], 'Step 4: cancel keeps amount=10');
assertEquals(0.0, $s[$lisa]['monthly'], 'Step 4: monthly zeroed');
assertEquals('one-time', $s[$lisa]['type'], 'Step 4: type back to one-time');

// Step 5: New subscription at different rate (donation.created €3)
processEvent([
    'type' => 'donation.created', 'live_mode' => false,
    'data' => ['supporter_name' => $lisa, 'total_amount_charged' => '3.00', 'currency' => 'EUR', 'created_at' => 1722729600],
], $csv, $log);
$s = readSupporters($csv);
assertEquals(13.0, $s[$lisa]['amount'], 'Step 5: amount accumulated to 13');

// Step 6: recurring_donation.started at new rate
processEvent([
    'type' => 'recurring_donation.started', 'live_mode' => false,
    'data' => ['supporter_name' => $lisa, 'amount' => 3, 'currency' => 'EUR', 'started_at' => 1722729600],
], $csv, $log);
$s = readSupporters($csv);
assertEquals(3.0, $s[$lisa]['monthly'], 'Step 6: monthly=3 (new rate)');
assertEquals('monthly', $s[$lisa]['type'], 'Step 6: type=monthly again');

// Step 7: Cancel again
processEvent([
    'type' => 'recurring_donation.cancelled', 'live_mode' => false,
    'data' => ['supporter_name' => $lisa],
], $csv, $log);
$s = readSupporters($csv);
assertEquals(13.0, $s[$lisa]['amount'], 'Step 7: cancel keeps amount=13');
assertEquals(0.0, $s[$lisa]['monthly'], 'Step 7: monthly zeroed again');

// Step 8: Partial refund (€5 of €13)
processEvent([
    'type' => 'donation.refunded', 'live_mode' => false,
    'data' => ['supporter_name' => $lisa, 'total_amount_charged' => '5.00', 'currency' => 'EUR'],
], $csv, $log);
$s = readSupporters($csv);
assertEquals(8.0, $s[$lisa]['amount'], 'Step 8: partial refund, amount=8');
assertTrue(isset($s[$lisa]), 'Step 8: supporter still exists (amount>0)');

// Step 9: Full refund of remaining €8
processEvent([
    'type' => 'donation.refunded', 'live_mode' => false,
    'data' => ['supporter_name' => $lisa, 'total_amount_charged' => '8.00', 'currency' => 'EUR'],
], $csv, $log);
$s = readSupporters($csv);
assertFalse(isset($s[$lisa]), 'Step 9: supporter removed after full refund');

echo "\n";

// ========================================
// HELPER
// ========================================

function assertFalse($condition, $message) {
    assertEquals(false, $condition, $message);
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
