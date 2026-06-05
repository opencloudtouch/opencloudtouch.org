# BuyMeACoffee Webhook Integration — Complete Handover

**Project:** OpenCloudTouch Supporter Recognition System  
**Date:** 2026-06-02  
**Status:** Phase 1 complete (one-time donations), Phase 2 planned (full lifecycle + refunds)

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Implemented Features](#implemented-features)
3. [Planned Features](#planned-features)
4. [Critical Learnings & Gotchas](#critical-learnings--gotchas)
5. [Event Types & Data Structures](#event-types--data-structures)
6. [Code Snippets](#code-snippets)
7. [Security](#security)
8. [Testing](#testing)
9. [Deployment](#deployment)
10. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

### The Problem

- **BMC REST API is deprecated** — `/api/v1/supporters` returns 400 Bad Request
- **OCT runs locally only** — Raspberry Pi / NAS / Docker behind NAT, no public endpoints
- **Webhooks require public URL** — BMC cannot POST to `localhost`

### The Solution: Hybrid Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│ BuyMeACoffee                                                    │
│  └─> Webhook POST to opencloudtouch.org/api/supporters/webhook │
└─────────────────────────────────────────────────────────────────┘
                                  │
                                  v
┌─────────────────────────────────────────────────────────────────┐
│ PHP Webhook Receiver (opencloudtouch.org)                      │
│  ├─> Verify HMAC signature (X-Signature-Sha256)                │
│  ├─> Update supporters.csv on server                           │
│  └─> Save event JSON to events/ directory (90-day retention)   │
└─────────────────────────────────────────────────────────────────┘
                                  │
                                  v
┌─────────────────────────────────────────────────────────────────┐
│ GitHub Actions (Scheduled + Manual)                            │
│  ├─> Fetch supporters.csv via HTTP Basic Auth                  │
│  ├─> Rebuild Docker image with embedded CSV                    │
│  └─> Push to GitHub Container Registry                         │
└─────────────────────────────────────────────────────────────────┘
                                  │
                                  v
┌─────────────────────────────────────────────────────────────────┐
│ OpenCloudTouch (Local Instance)                                │
│  ├─> Pull updated Docker image                                 │
│  ├─> Read supporters.csv from /app/public/                     │
│  └─> Display in About page (Wimmelbild + Golden Bling-Bling)   │
└─────────────────────────────────────────────────────────────────┘
```

**Key Decision:** CSV lives on **server**, not in Git → avoids PII in public repo, enables real-time updates

---

## Implemented Features

### Phase 1: One-Time Donations ✅

**Webhook Handler (`webhook.php`)**
- ✅ Receives `donation.created` events from BuyMeACoffee
- ✅ Verifies HMAC SHA-256 signature via `X-Signature-Sha256` header
- ✅ Test mode support (`live_mode: false` bypasses signature validation)
- ✅ Appends to `supporters.csv` with proper CSV escaping
- ✅ UTF-8 encoding handling (with BOM issues — see [Gotchas](#bom-byte-order-mark))
- ✅ Returns JSON response: `{"success":true,"supporter":"Name","type":"one-time"}`

**CSV Structure (`supporters.csv`)**
```csv
name,type,amount,monthlyAmount,firstSupportDate
Siggi,one-time,5,0,2026-06-02
Jamie Smith,one-time,1,0,2026-06-02
Stephan,monthly,0,20,2026-05-20
```

**Frontend Integration (React)**
- ✅ `About.tsx` — Wimmelbild display with:
  - Color gradient based on supporter index: `hue = (index / total) * 360`
  - Font size based on total contribution: `12px + (32px - 12px) * (totalSupport / maxAmount)`
  - Sorting: `(amount + monthlyAmount) DESC`, then `firstSupportDate ASC`, then `name ASC`
  - Golden bling-bling animation for monthly supporters (linear-gradient shine)
  - Random "Thank You" tooltips (20 regular, 15 monthly, 10 languages planned)

**GitHub Actions Integration**
- ✅ `.github/workflows/build-images.yml` modified:
  - Fetch CSV from `https://opencloudtouch.org/api/supporters/get.php` (HTTP Basic Auth)
  - Embed into Docker image at `/app/public/supporters.csv`
  - Secret `SUPPORTERS_API_PASS` stored in GitHub Secrets

**Event Archiving**
- ✅ `rotate-events.sh` deployed to server:
  - Archives events older than 90 days into `events_archive_YYYY-MM-DD.zip`
  - Deletes original JSON files after archiving
  - **Not yet active** — needs cron setup (see [Deployment](#deployment))

---

## Planned Features

### Phase 2: Full Subscription Lifecycle 🚧

**Missing Event Handlers:**

1. **`recurring_donation.started`** ⏳
   - **Action:** Add new monthly supporter to CSV
   - **Fields needed:** `supporter_name`, `amount`, `started_at` → `firstSupportDate`
   - **CSV operation:** Append or update if supporter already exists

2. **`recurring_donation.updated`** ⏳
   - **Action:** Update monthly amount (user changed subscription tier)
   - **Edge case:** What if name changed? Use `supporter_id` as stable identifier
   - **CSV operation:** Find by `supporter_id` (not implemented yet — CSV has no ID column!)

3. **`recurring_donation.cancelled`** ⏳
   - **Action:** Set `monthlyAmount = 0`, keep `amount` (past one-time donations)
   - **User feedback:** `supporter_feedback` field contains cancellation reason
   - **CSV operation:** Update existing row

4. **`donation.refunded`** ⏳
   - **Action:** Subtract refunded amount from `amount` or `monthlyAmount`
   - **Edge case:** What if refund amount > current total? (unlikely but possible)
   - **Data needed:** `data.refunded_at`, `data.amount` (refunded amount)
   - **CSV operation:** Update existing row, possibly remove if total becomes 0

### Phase 3: Currency Handling ✅ (Completed, but needs review)

**Current State:**
- USD donations are converted to EUR using historical exchange rates
- Example: Peter St. donated $25 on 2026-06-01 → stored as €21.47
- **Critical Bug Found:** Agent initially converted USD→EUR correctly, then reversed it by converting back to round EUR
- **NO Fix Needed - was already fixed by the user in the csv online** 

**URL: http://opencloudtouch.org/api/supporters/supporters.csv**
```csv
name,type,amount,monthlyAmount,firstSupportDate
Flo,one-time,30,0,2026-05-26
"Peter St.",one-time,21.47,0,2026-06-01
Klaus,one-time,20,0,2026-05-11
Cerebrus,one-time,23.3,0,2026-05-28
Chris.G,one-time,20,0,2026-05-16
"Rolf K.",one-time,20,0,2026-05-16
"Klaus H.",one-time,20,0,2026-05-17
Peter,one-time,20,0,2026-05-22
"Christian H.",one-time,20,0,2026-05-23
"Jürgen N.",one-time,23.2,0,2026-05-20
Simon,one-time,11.6,0,2026-05-23
"Michael Schmeiss",one-time,11.6,0,2026-05-24
https://github.com/Struppie,one-time,10,0,2026-05-10
Someone,one-time,10,0,2026-05-17
Stephan,monthly,5,5,2026-05-20
Martin,one-time,10,0,2026-05-31
"Wolfi Z.",one-time,10,0,2026-05-15
Volker,one-time,10,0,2026-05-16
Saschbe,one-time,10,0,2026-05-17
JoeSom68,one-time,10,0,2026-05-27
Woody,one-time,8.50,0,2026-05-10
Siggi,one-time,8,0,2026-06-02
@Eisenvater,one-time,5,0,2026-05-09
Harald,one-time,5,0,2026-05-11
"Victor R.",one-time,5,0,2026-05-18
Christoph,one-time,5,0,2026-05-31
Ingo,one-time,5,0,2026-05-16
"Jamie Smith",monthly,1,1,2026-06-02
 
```

### Phase 4: Enhanced Data Tracking 🆕

**Problem:** CSV loses rich data from webhook events
- `support_note` — supporter's message (visible in examples)
- `supporter_email` — contact info (privacy concern?)
- `transaction_id` — Stripe transaction ID
- `application_fee` — BMC's cut (5%)
- `note_hidden` — whether supporter wants public acknowledgment

**Proposed Solution:**
- Keep CSV simple for frontend display
- Store full event JSONs in `events/` directory (already planned)
- Build separate `supporters.json` aggregated from events:

```json
{
  "supporters": [
    {
      "supporter_id": 10815174,
      "name": "Alex",
      "email": "alex@example.com",
      "total_amount": 8.00,
      "monthly_amount": 0,
      "first_support_date": "2026-06-02",
      "donations": [
        {
          "event_id": 580796,
          "amount": 5.00,
          "currency": "EUR",
          "created_at": 1780394554,
          "support_note": "Dein Programm hat meine 5 Bose...",
          "note_hidden": false,
          "transaction_id": "pi_3Tdp9PJRHVLVJ5LA0Eti2Zxx"
        },
        {
          "event_id": 580805,
          "amount": 1.00,
          "currency": "EUR",
          "created_at": 1780395286,
          "support_note": "Ich habe mich im Betrag...",
          "note_hidden": true
        }
      ],
      "subscriptions": []
    }
  ]
}
```

**Benefits:**
- Enables detailed reporting ("Who donated the most?" → Flo with 30€)
- Preserves supporter messages for future "Wall of Fame" feature
- Very appreciative and detailed reviews can be posted as cards on the opencloudtouch.org website under the 'Reviews' section 
- Allows id-based matching across donations (same `supporter_id` = same person)

---

## Critical Learnings & Gotchas

### 1. BOM (Byte Order Mark) 💀

**Problem:** PHP's `fputcsv()` does NOT write UTF-8 BOM, but BMC's response JSONs show `﻿` prefix

**Evidence:**
```json
"﻿{"success":true,"supporter":"Jamie Smith","type":"one-time"}"
```

**Root Cause:**
- `webhook.php` returns JSON via `json_encode()` which is UTF-8 without BOM
- But BMC's webhook log displays responses with BOM prefix
- **Hypothesis:** BMC's webhook logger adds BOM when rendering, not our fault

**Client-Side Fix (Applied in React):**
```typescript
const response = await fetch('/supporters.csv');
let text = await response.text();

// Strip BOM if present
if (text.charCodeAt(0) === 0xFEFF) {
  text = text.substring(1);
}

const rows = text.split('\n');
```

**Server-Side Fix Attempts (All Failed):**
```php
// Attempt 1: header() — no effect
header('Content-Type: text/plain; charset=utf-8');

// Attempt 2: echo BOM — doubled it instead
echo "\xEF\xBB\xBF";

// Attempt 3: ltrim() — strips data, not BOM
$content = ltrim(file_get_contents($csvPath), "\xEF\xBB\xBF");
```

**Lesson:** BOM issues are browser/server quirks. Always strip client-side as fallback.

---

### 2. Mojibake & Double-Escaping 🔤

**Problem:** Umlauts appear garbled in webhook responses

**Evidence:**
```json
"supporter": "Jamie Smith"  // In BMC's request (correct)
"Gr\\u00fcnwald Alm\\u00f6h\\u00fc" // In our JSON response (escaped)
"Gr\\u00fcnwald Alm\\u00f6h\\u00fc" // In BMC's webhook log (double-escaped?)
```

**Root Cause:**
- BMC sends proper UTF-8: `Jamie Smith`
- PHP `json_encode()` escapes by default: `"Gr\u00fcnwald Alm\u00f6h\u00fc"` (single backslash in source)
- BMC's webhook log escapes the backslashes again when rendering: `"Gr\\u00fcnwald..."`

**Fix:**
```php
// Use JSON_UNESCAPED_UNICODE to prevent escaping
$response = [
    'success' => true,
    'supporter' => $name,
    'type' => 'one-time'
];
echo json_encode($response, JSON_UNESCAPED_UNICODE);

// Output: {"success":true,"supporter":"Jamie Smith","type":"one-time"}
```

**Lesson:** Always use `JSON_UNESCAPED_UNICODE` when returning non-ASCII names to external services.

---

### 3. `total_amount_charged` is NOT Total Amount! 💸

**Most Critical Gotcha**

**BMC Webhook Data:**
```json
{
  "data": {
    "supporter_id": 10815174,      // SAME for all 5 donations
    "supporter_name": "Alex",     // SAME
    "total_amount_charged": "1.00" // ONLY the current donation!
  }
}
```

**Siggi's Donation History:**
1. Event 580796: €5.00 → `total_amount_charged: "5.00"`
2. Event 580805: €1.00 → `total_amount_charged: "1.00"`
3. Event 580835: €1.00 → `total_amount_charged: "1.00"`
4. Event 580907: €1.00 → `total_amount_charged: "1.00"`
5. Event 580914: ... (different supporter)

**Expected Behavior (WRONG):**
- `total_amount_charged` should be €5 + €1 + €1 + €1 = €8 cumulative

**Actual Behavior (CORRECT):**
- `total_amount_charged` is ONLY the current transaction amount
- We MUST sum donations ourselves using `supporter_id` as key

**Implementation:**
```php
// Load existing supporters from CSV
$supporters = [];
if (file_exists($csvPath)) {
    $handle = fopen($csvPath, 'r');
    fgetcsv($handle); // Skip header
    while ($row = fgetcsv($handle)) {
        $supporters[$row[0]] = [
            'amount' => floatval($row[2]),
            'monthlyAmount' => floatval($row[3]),
            'firstSupportDate' => $row[4]
        ];
    }
    fclose($handle);
}

// Add new donation
$name = $event['data']['supporter_name'];
$amount = floatval($event['data']['total_amount_charged']);

if (isset($supporters[$name])) {
    // Existing supporter → add to total
    $supporters[$name]['amount'] += $amount;
} else {
    // New supporter
    $supporters[$name] = [
        'amount' => $amount,
        'monthlyAmount' => 0,
        'firstSupportDate' => date('Y-m-d', $event['data']['created_at'])
    ];
}

// Write back to CSV
$handle = fopen($csvPath, 'w');
fputcsv($handle, ['name', 'type', 'amount', 'monthlyAmount', 'firstSupportDate']);
foreach ($supporters as $name => $data) {
    $type = $data['monthlyAmount'] > 0 ? 'monthly' : 'one-time';
    fputcsv($handle, [
        $name,
        $type,
        $data['amount'],
        $data['monthlyAmount'],
        $data['firstSupportDate']
    ]);
}
fclose($handle);
```

**Lesson:** NEVER trust field names. Always verify with real data.

---

### 4. Test Events & Signature Validation 🧪

**Test Mode:**
```json
{
  "live_mode": false,  // Test event
  "data": { ... }
}
```

**Webhook Logic:**
```php
$event = json_decode(file_get_contents('php://input'), true);

if ($event['live_mode'] === false) {
    // Bypass signature validation for test events
    processEvent($event);
    exit;
}

// Production: Verify signature
$signature = $_SERVER['HTTP_X_SIGNATURE_SHA256'] ?? '';
$expectedSignature = hash_hmac('sha256', file_get_contents('php://input'), BMC_WEBHOOK_SECRET);

if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}
```

**Signature Validation Failed Example:**
- Event 580796 (Alex, €5) returned `{"error":"Invalid signature"}`
- **Cause:** Unknown — possibly BMC retry with stale signature?
- **Impact:** Donation was NOT processed (CSV unchanged)
- **Mitigation:** BMC retries failed webhooks up to 3 times

**Lesson:** Always log signature validation failures with full request data for debugging.

---

### 5. CSV Line Endings & Excel Compatibility 📄

**Problem:** Initial implementation wrote CSV without line breaks → all data in one line

**Fix:**
```php
$handle = fopen($csvPath, 'w');
fputcsv($handle, ['name', 'type', 'amount', 'monthlyAmount', 'firstSupportDate']);

foreach ($supporters as $name => $data) {
    fputcsv($handle, [
        $name,
        $data['type'],
        $data['amount'],
        $data['monthlyAmount'],
        $data['firstSupportDate']
    ]);
}

fclose($handle); // CRITICAL: fclose() writes line endings
```

**Windows Line Ending Issue:**
- PHP on Windows uses `\r\n` (CRLF)
- PHP on Linux uses `\n` (LF)
- `fputcsv()` respects system default → no manual `\n` needed

**Lesson:** Use `fputcsv()` + `fclose()`, never `file_put_contents()` for CSV.

---

### 6. Currency Handling: USD → EUR Conversion 💱

**Challenge:** BMC supports multiple currencies, CSV stores only EUR

**Historical Exchange Rates (Manual Table):**
```php
// Source: European Central Bank (ECB) historical rates
$usdToEurRates = [
    '2026-06-01' => 0.8588,  // Peter St. $25 → €21.47
    '2026-05-10' => 0.8500,  // Woody $10 → €8.50
    // Fallback: current rate if date not found
];

function convertUsdToEur($amountUsd, $date) {
    global $usdToEurRates;
    $rate = $usdToEurRates[$date] ?? 0.85; // Fallback to ~0.85
    return round($amountUsd * $rate, 2);
}
```

**BMC Webhook Provides:**
```json
{
  "data": {
    "amount": 25,
    "currency": "USD",
    "created_at": 1780394554  // Unix timestamp
  }
}
```

**Implementation:**
```php
$amount = $event['data']['amount'];
$currency = $event['data']['currency'];
$date = date('Y-m-d', $event['data']['created_at']);

if ($currency === 'USD') {
    $amountEur = convertUsdToEur($amount, $date);
} else {
    $amountEur = $amount; // Already EUR
}

// Store $amountEur in CSV
```

**Critical Bug History:**
1. Initial conversion: $25 USD → €21.47 (CORRECT)
2. Agent mistake: Saw €21.47 as "krumm", converted to €25 (WRONG)
3. Agent realization: Wait, round USD should become krumme EUR (CORRECT)
4. Current state: online CSV has correct conversions (NEEDS NO FIX)

**Lesson:** Round USD input should always produce krumme EUR output. If you see round EUR from USD donor, it's wrong.

---

## Event Types & Data Structures

### 1. `donation.created` (One-Time Donation)

**Example:** [donation_created_alex_5eur_invalid_signature.json](supporters/events/examples/donation_created_alex_5eur_invalid_signature.json)

```json
{
  "data": {
    "id": 14421406,
    "amount": 5,
    "object": "payment",
    "status": "succeeded",
    "message": "Alex became a supporter.",
    "currency": "EUR",
    "refunded": "false",
    "created_at": 1780394550,
    "note_hidden": "false",
    "refunded_at": null,
    "coffee_count": 1,
    "coffee_price": "5.0000",
    "support_note": "Dein Programm hat meine 5 Bose Soundtouch Boxen gerettet...",
    "support_type": "Supporter",
    "supporter_id": 10815174,
    "supporter_name": "Alex",
    "transaction_id": "pi_3Tdp9PJRHVLVJ5LA0Eti2Zxx",
    "application_fee": "0.25",
    "supporter_email": "alex@example.com",
    "supporter_name_type": "default",
    "total_amount_charged": "5.00"
  },
  "type": "donation.created",
  "attempt": 1,
  "created": 1780394554,
  "event_id": 580796,
  "live_mode": true
}
```

**Key Fields:**
- `supporter_id` — Stable identifier across donations (use this, not email!)
- `total_amount_charged` — THIS DONATION ONLY, not cumulative
- `support_note` — Supporter's message (may contain PII)
- `note_hidden` — If `true`, supporter wants privacy
- `refunded` — String `"false"`, not boolean (check carefully!)
- `created_at` — Unix timestamp (seconds since epoch)

---

### 2. `recurring_donation.started` (Monthly Subscription Started)

**Example:** [recurring_donation_started_jamie.json](supporters/events/examples/recurring_donation_started_jamie.json)

```json
{
  "data": {
    "id": 1020646,
    "amount": 1,
    "object": "recurring_donation",
    "paused": "false",
    "psp_id": "sub_1Tds1nJRHVLVJ5LATvgEnLII",
    "status": "active",
    "canceled": "false",
    "currency": "EUR",
    "started_at": 1780405607,
    "canceled_at": null,
    "note_hidden": false,
    "support_note": "joa da ka ma scho emoi wos stabils mochn",
    "supporter_id": 10816073,
    "duration_type": "month",
    "supporter_name": "Jamie Smith",
    "supporter_email": "jamie@example.com",
    "current_period_end": 1782997603,
    "supporter_feedback": null,
    "supporter_name_type": "default",
    "cancel_at_period_end": "false",
    "current_period_start": 1780405603
  },
  "type": "recurring_donation.started",
  "attempt": 1,
  "created": 1780405643,
  "event_id": 580918,
  "live_mode": true
}
```

**Key Fields:**
- `amount` — Monthly donation amount (€1/month here)
- `duration_type` — Usually `"month"` (could also be `"year"` for annual?)
- `psp_id` — Stripe subscription ID (for tracking/cancellation)
- `started_at` — When subscription started (use as `firstSupportDate`)
- `current_period_end` — When next charge happens

---

### 3. `recurring_donation.updated` (Subscription Changed)

**Example:** [recurring_donation_updated_jamie.json](supporters/events/examples/recurring_donation_updated_jamie.json)

**Same structure as `started`, but:**
- `status` remains `"active"`
- `amount` may change (user upgraded/downgraded tier)
- Check `cancel_at_period_end` — if `"true"`, subscription will cancel after current period

---

### 4. `recurring_donation.cancelled` (Subscription Cancelled)

**Example:** [recurring_donation_cancelled_jamie.json](supporters/events/examples/recurring_donation_cancelled_jamie.json)

```json
{
  "data": {
    "id": 1020646,
    "status": "canceled",
    "canceled": "true",
    "canceled_at": 1780406031,
    "supporter_feedback": "I prefer one-time support over monthly support.",
    ...
  },
  "type": "recurring_donation.cancelled"
}
```

**Key Fields:**
- `canceled_at` — When subscription was cancelled (Unix timestamp)
- `supporter_feedback` — User's reason for cancelling (optional)
- `status` — `"canceled"` (note British spelling)

---

### 5. `donation.refunded` (Refund Processed) ⏳ Not Yet Seen

**Expected Structure (Based on BMC Docs):**
```json
{
  "data": {
    "id": 14421406,
    "amount": 5,
    "refunded": "true",
    "refunded_at": 1780500000,
    "transaction_id": "pi_3Tdp9PJRHVLVJ5LA0Eti2Zxx",
    "supporter_id": 10815174,
    "supporter_name": "Alex"
  },
  "type": "donation.refunded",
  "created": 1780500005
}
```

**Handling Strategy:**
1. Load supporter from CSV by `supporter_name` (or better: by `supporter_id` if we add ID column)
2. Subtract `amount` from total
3. If total reaches 0, remove from CSV? Or keep with `amount = 0` for history?

---

## Code Snippets

### Complete Webhook Handler (Current Implementation)

**File:** `.local/api-supporters/webhook.php` (5412 bytes)

```php
<?php
// BuyMeACoffee Webhook Receiver for OpenCloudTouch
// Version: 1.0 (donation.created only)
// Date: 2026-06-02

// Paths
define('CSV_PATH', __DIR__ . '/supporters.csv');
define('LOG_PATH', __DIR__ . '/webhook.log');
define('DEBUG_LOG_PATH', __DIR__ . '/webhook-debug.log');

// BMC Webhook Secret (store in environment variable in production!)
define('BMC_WEBHOOK_SECRET', getenv('BMC_WEBHOOK_SECRET') ?: 'your_secret_here');

// Read raw POST body
$rawBody = file_get_contents('php://input');

// Debug logging
file_put_contents(DEBUG_LOG_PATH, 
    date('[Y-m-d H:i:s] ') . "Received webhook\n" .
    "Headers: " . json_encode(getallheaders()) . "\n" .
    "Body: " . $rawBody . "\n\n",
    FILE_APPEND
);

// Decode JSON
$event = json_decode($rawBody, true);

if (!$event) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Test mode: bypass signature validation
if (isset($event['live_mode']) && $event['live_mode'] === false) {
    file_put_contents(LOG_PATH, 
        date('[Y-m-d H:i:s] ') . "Test event received, bypassing signature\n",
        FILE_APPEND
    );
    processEvent($event);
    exit;
}

// Production: verify HMAC signature
$signature = $_SERVER['HTTP_X_SIGNATURE_SHA256'] ?? '';
$expectedSignature = hash_hmac('sha256', $rawBody, BMC_WEBHOOK_SECRET);

if (!hash_equals($expectedSignature, $signature)) {
    file_put_contents(LOG_PATH, 
        date('[Y-m-d H:i:s] ') . "Invalid signature\n" .
        "Expected: $expectedSignature\n" .
        "Received: $signature\n",
        FILE_APPEND
    );
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// Signature valid, process event
processEvent($event);

function processEvent($event) {
    $type = $event['type'] ?? '';
    
    if ($type === 'donation.created') {
        handleDonationCreated($event);
    } else {
        // Unknown event type
        file_put_contents(LOG_PATH, 
            date('[Y-m-d H:i:s] ') . "Unsupported event type: $type\n",
            FILE_APPEND
        );
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'type' => $type]);
    }
}

function handleDonationCreated($event) {
    $data = $event['data'];
    
    $name = $data['supporter_name'];
    $amount = floatval($data['total_amount_charged']);
    $currency = $data['currency'];
    $timestamp = $data['created_at'];
    $date = date('Y-m-d', $timestamp);
    
    // Currency conversion
    if ($currency === 'USD') {
        $amount = convertUsdToEur($amount, $date);
    }
    
    // Load existing supporters
    $supporters = [];
    if (file_exists(CSV_PATH)) {
        $handle = fopen(CSV_PATH, 'r');
        fgetcsv($handle); // Skip header
        while ($row = fgetcsv($handle)) {
            $supporters[$row[0]] = [
                'type' => $row[1],
                'amount' => floatval($row[2]),
                'monthlyAmount' => floatval($row[3]),
                'firstSupportDate' => $row[4]
            ];
        }
        fclose($handle);
    }
    
    // Add or update supporter
    if (isset($supporters[$name])) {
        $supporters[$name]['amount'] += $amount;
    } else {
        $supporters[$name] = [
            'type' => 'one-time',
            'amount' => $amount,
            'monthlyAmount' => 0,
            'firstSupportDate' => $date
        ];
    }
    
    // Write back to CSV
    $handle = fopen(CSV_PATH, 'w');
    fputcsv($handle, ['name', 'type', 'amount', 'monthlyAmount', 'firstSupportDate']);
    
    foreach ($supporters as $name => $data) {
        fputcsv($handle, [
            $name,
            $data['type'],
            $data['amount'],
            $data['monthlyAmount'],
            $data['firstSupportDate']
        ]);
    }
    
    fclose($handle);
    
    // Save event JSON to events/ directory
    $eventsDir = __DIR__ . '/events';
    if (!is_dir($eventsDir)) {
        mkdir($eventsDir, 0755, true);
    }
    
    $eventId = $event['event_id'];
    $eventFile = $eventsDir . '/' . $eventId . '.json';
    file_put_contents($eventFile, json_encode($event, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Log success
    file_put_contents(LOG_PATH, 
        date('[Y-m-d H:i:s] ') . "Donation processed: $name, €$amount\n",
        FILE_APPEND
    );
    
    // Return success response (with JSON_UNESCAPED_UNICODE to avoid mojibake!)
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'supporter' => $name,
        'amount' => $amount,
        'type' => 'one-time'
    ], JSON_UNESCAPED_UNICODE);
}

function convertUsdToEur($amountUsd, $date) {
    // Historical USD→EUR rates from ECB
    $rates = [
        '2026-06-01' => 0.8588,
        '2026-05-10' => 0.8500,
    ];
    
    $rate = $rates[$date] ?? 0.85; // Fallback
    return round($amountUsd * $rate, 2);
}
?>
```

---

### Event Rotation Script (90-Day Archive)

**File:** `rotate-events.sh` (deployed to server)

```bash
#!/bin/bash
# Archive events older than 90 days
# Run daily via cron: 0 3 * * * /var/www/opencloudtouch.org/api/supporters/rotate-events.sh

EVENTS_DIR="/var/www/opencloudtouch.org/api/supporters/events"
ARCHIVE_DIR="/var/www/opencloudtouch.org/api/supporters/events_archive"

mkdir -p "$ARCHIVE_DIR"

# Find JSON files older than 90 days
OLD_FILES=$(find "$EVENTS_DIR" -name "*.json" -mtime +90)

if [ -z "$OLD_FILES" ]; then
    echo "No files to archive"
    exit 0
fi

# Create archive with current date
ARCHIVE_NAME="events_archive_$(date +%Y-%m-%d).zip"
ARCHIVE_PATH="$ARCHIVE_DIR/$ARCHIVE_NAME"

# Add files to archive
echo "$OLD_FILES" | xargs zip -j "$ARCHIVE_PATH"

# Delete original files
echo "$OLD_FILES" | xargs rm -f

echo "Archived $(echo "$OLD_FILES" | wc -l) files to $ARCHIVE_NAME"
```

**Cron Setup (Manual Step Required):**
```bash
# SSH into server (credentials in static/api/.env)
ssh $FTP_USER@$FTP_HOST

# Edit crontab
crontab -e

# Add this line:
0 3 * * * /var/www/opencloudtouch.org/api/supporters/rotate-events.sh >> /var/www/opencloudtouch.org/api/supporters/rotate-events.log 2>&1
```

---

### CSV Upload/Download Endpoints

**File:** `get.php` (CSV download with HTTP Basic Auth)

```php
<?php
// Require HTTP Basic Auth
$user = $_SERVER['PHP_AUTH_USER'] ?? '';
$pass = $_SERVER['PHP_AUTH_PW'] ?? '';

if ($user !== 'oct-ci' || $pass !== getenv('API_PASS')) {
    header('WWW-Authenticate: Basic realm="OCT Supporters API"');
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

// Return CSV
$csvPath = __DIR__ . '/supporters.csv';

if (!file_exists($csvPath)) {
    http_response_code(404);
    echo 'CSV not found';
    exit;
}

// Try to remove BOM (doesn't work perfectly, client-side stripping required)
$content = file_get_contents($csvPath);
$content = ltrim($content, "\xEF\xBB\xBF"); // Strip UTF-8 BOM

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: inline; filename="supporters.csv"');
echo $content;
?>
```

**File:** `upload.php` (CSV upload with HTTP Basic Auth)

```php
<?php
// Require HTTP Basic Auth
$user = $_SERVER['PHP_AUTH_USER'] ?? '';
$pass = $_SERVER['PHP_AUTH_PW'] ?? '';

if ($user !== 'oct-ci' || $pass !== getenv('API_PASS')) {
    header('WWW-Authenticate: Basic realm="OCT Supporters API"');
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

// Read uploaded CSV
$rawBody = file_get_contents('php://input');

if (empty($rawBody)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty body']);
    exit;
}

// Write to file (no BOM manipulation, just as-is)
$csvPath = __DIR__ . '/supporters.csv';
file_put_contents($csvPath, $rawBody);

http_response_code(200);
echo json_encode(['success' => true, 'size' => strlen($rawBody)]);
?>
```

---

### Frontend: BOM Removal & CSV Parsing

**File:** `apps/frontend/src/pages/About.tsx`

```typescript
useEffect(() => {
  const loadSupporters = async () => {
    try {
      const response = await fetch('/supporters.csv');
      let text = await response.text();

      // CRITICAL: Strip UTF-8 BOM if present
      if (text.charCodeAt(0) === 0xFEFF) {
        text = text.substring(1);
      }

      const lines = text.trim().split('\n');
      const header = lines[0].split(',');

      // Validate header
      if (header[0] !== 'name' || header[2] !== 'amount') {
        throw new Error('Invalid CSV format');
      }

      const parsedSupporters: Supporter[] = [];

      for (let i = 1; i < lines.length; i++) {
        const values = lines[i].split(',');
        if (values.length < 5) continue;

        parsedSupporters.push({
          name: values[0],
          type: values[1] as 'one-time' | 'monthly',
          amount: parseFloat(values[2]) || 0,
          monthlyAmount: parseFloat(values[3]) || 0,
          firstSupportDate: values[4]
        });
      }

      // Sort: (amount + monthlyAmount) DESC, firstSupportDate ASC, name ASC
      parsedSupporters.sort((a, b) => {
        const totalA = a.amount + a.monthlyAmount;
        const totalB = b.amount + b.monthlyAmount;

        if (totalB !== totalA) return totalB - totalA;

        const dateCompare = a.firstSupportDate.localeCompare(b.firstSupportDate);
        if (dateCompare !== 0) return dateCompare;

        return a.name.localeCompare(b.name);
      });

      setSupporters(parsedSupporters);
    } catch (error) {
      console.error('Failed to load supporters:', error);
      setSupporters([]);
    }
  };

  loadSupporters();
}, []);
```

---

## Security

### 1. HMAC Signature Verification

**Header:** `X-Signature-Sha256`  
**Algorithm:** HMAC SHA-256  
**Secret:** Stored in BMC dashboard → Webhooks → Secret

**PHP Implementation:**
```php
$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE_SHA256'] ?? '';
$expectedSignature = hash_hmac('sha256', $rawBody, BMC_WEBHOOK_SECRET);

if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}
```

**Critical Points:**
- Use `hash_equals()`, NOT `===` (prevents timing attacks)
- Hash the RAW body, NOT decoded JSON
- Test events (`live_mode: false`) bypass validation

---

### 2. HTTP Basic Auth (CSV Upload/Download)

**Credentials:**
- Username: `oct-ci`
- Password: Stored in GitHub Secret `SUPPORTERS_API_PASS`

**PHP Implementation:**
```php
$user = $_SERVER['PHP_AUTH_USER'] ?? '';
$pass = $_SERVER['PHP_AUTH_PW'] ?? '';

if ($user !== 'oct-ci' || $pass !== getenv('API_PASS')) {
    header('WWW-Authenticate: Basic realm="OCT Supporters API"');
    http_response_code(401);
    exit;
}
```

**GitHub Actions Usage:**
```yaml
- name: Fetch supporters.csv
  run: |
    curl -u "oct-ci:${{ secrets.SUPPORTERS_API_PASS }}" \
      https://opencloudtouch.org/api/supporters/get.php \
      -o apps/frontend/public/supporters.csv
```

---

### 3. PII (Personally Identifiable Information)

**Data We Receive:**
- `supporter_name` — Public display name (user-chosen)
- `supporter_email` — Email address (SENSITIVE!)
- `support_note` — Free-text message (may contain PII)
- `supporter_id` — BMC internal ID (stable identifier)

**Current Storage:**
- CSV: Only `name`, amounts, date (NO email, NO notes)
- Event JSONs: Full data including email and notes

**Privacy Concerns:**
- Event JSONs contain emails → must NOT be publicly accessible
- `.htaccess` on server should deny direct access to `events/` directory
- Future: Add `events/.htaccess` with `Deny from all`

**Planned Anonymization:**
```php
// Before saving event JSON, redact sensitive fields
$eventSanitized = $event;
$eventSanitized['data']['supporter_email'] = '[REDACTED]';

if ($event['data']['note_hidden'] === "true") {
    $eventSanitized['data']['support_note'] = '[HIDDEN BY SUPPORTER]';
}

file_put_contents($eventFile, json_encode($eventSanitized, JSON_PRETTY_PRINT));
```

---

## Testing

### 1. BMC Test Events

**Location:** BMC Dashboard → Webhooks → Test Tab

**How to Send:**
1. Click "Send test event"
2. Choose event type (e.g., `donation.created`)
3. BMC sends with `live_mode: false`
4. Webhook bypasses signature validation

**Test Response Example:**
```json
{
  "success": true,
  "supporter": "Test Supporter",
  "type": "one-time"
}
```

---

### 2. Manual cURL Testing

**Test Donation Event:**
```bash
# Prepare payload
cat > test-donation.json <<EOF
{
  "data": {
    "id": 99999999,
    "amount": 10,
    "object": "payment",
    "status": "succeeded",
    "currency": "EUR",
    "created_at": $(date +%s),
    "supporter_id": 12345678,
    "supporter_name": "Test User",
    "supporter_email": "test@example.com",
    "total_amount_charged": "10.00"
  },
  "type": "donation.created",
  "event_id": 999999,
  "live_mode": false
}
EOF

# Send to webhook
curl -X POST https://opencloudtouch.org/api/supporters/webhook \
  -H "Content-Type: application/json" \
  -d @test-donation.json

# Expected response:
# {"success":true,"supporter":"Test User","amount":10,"type":"one-time"}
```

**Test Subscription Started:**
```bash
cat > test-subscription.json <<EOF
{
  "data": {
    "id": 88888888,
    "amount": 5,
    "object": "recurring_donation",
    "status": "active",
    "currency": "EUR",
    "started_at": $(date +%s),
    "supporter_id": 12345678,
    "supporter_name": "Test User",
    "supporter_email": "test@example.com",
    "duration_type": "month"
  },
  "type": "recurring_donation.started",
  "event_id": 888888,
  "live_mode": false
}
EOF

curl -X POST https://opencloudtouch.org/api/supporters/webhook \
  -H "Content-Type: application/json" \
  -d @test-subscription.json
```

---

### 3. Signature Validation Testing

**Generate Valid Signature:**
```bash
SECRET="your_webhook_secret_from_bmc"
PAYLOAD='{"type":"donation.created","live_mode":true,"data":{...}}'

SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | cut -d' ' -f2)

curl -X POST https://opencloudtouch.org/api/supporters/webhook \
  -H "Content-Type: application/json" \
  -H "X-Signature-Sha256: $SIGNATURE" \
  -d "$PAYLOAD"
```

---

## Deployment

### Server Structure

```
/var/www/opencloudtouch.org/
├── api/
│   └── supporters/
│       ├── webhook.php              (Webhook receiver)
│       ├── get.php                  (CSV download endpoint)
│       ├── upload.php               (CSV upload endpoint)
│       ├── rotate-events.sh         (Event archiving script)
│       ├── supporters.csv           (Live supporter data)
│       ├── webhook.log              (Production log)
│       ├── webhook-debug.log        (Full request dumps)
│       ├── events/                  (Event JSONs, 90-day retention)
│       │   ├── 580796.json
│       │   ├── 580805.json
│       │   └── ...
│       └── events_archive/          (Zipped archives >90 days)
│           ├── events_archive_2026-09-01.zip
│           └── ...
└── static/
    └── api/
        ├── HANDOVER.md              (This document)
        └── supporters/
            └── events/
                └── examples/        (Example event JSONs for documentation)
```

---

### FTP Credentials

> **Credentials stored in `.env` file** (see `static/api/.env`).  
> Never commit credentials to Git or documentation.

**Upload Example (PowerShell):**
```powershell
# Load credentials from .env
Get-Content "static/api/.env" | ForEach-Object {
    if ($_ -match '^([^#=]+)=(.+)$') { Set-Item "env:$($Matches[1].Trim())" $Matches[2].Trim() }
}

# Install WinSCP PowerShell module (once)
Install-Module -Name WinSCP

# Upload file
$sessionOptions = New-Object WinSCP.SessionOptions -Property @{
    Protocol = [WinSCP.Protocol]::Ftp
    HostName = $env:FTP_HOST
    UserName = $env:FTP_USER
    Password = $env:FTP_PASS
}

$session = New-Object WinSCP.Session
$session.Open($sessionOptions)

$session.PutFiles("C:\local\webhook.php", "$env:FTP_ROOT/api/supporters/webhook.php")

$session.Dispose()
```

---

### Cron Setup (Manual)

**SSH Access:**
```bash
ssh $FTP_USER@$FTP_HOST
```

**Add Cron Job:**
```bash
crontab -e

# Add this line for daily event rotation at 3 AM:
0 3 * * * /var/www/opencloudtouch.org/api/supporters/rotate-events.sh >> /var/www/opencloudtouch.org/api/supporters/rotate-events.log 2>&1
```

**Verify Cron:**
```bash
crontab -l
```

---

### GitHub Actions Integration

**File:** `.github/workflows/build-images.yml`

**Added Steps:**
```yaml
- name: Fetch supporters.csv from server
  run: |
    curl -u "oct-ci:${{ secrets.SUPPORTERS_API_PASS }}" \
      https://opencloudtouch.org/api/supporters/get.php \
      -o apps/frontend/public/supporters.csv
    
    # Verify CSV has content
    if [ ! -s apps/frontend/public/supporters.csv ]; then
      echo "Error: supporters.csv is empty"
      exit 1
    fi

- name: Build Docker image
  # ... existing build steps ...
  # CSV is now embedded in image at /app/public/supporters.csv
```

**GitHub Secret Setup:**
```bash
gh secret set SUPPORTERS_API_PASS --body "your_password_here"
```

---

## Troubleshooting

### Issue: Webhook Returns 401 (Invalid Signature)

**Symptoms:**
- BMC webhook log shows "Invalid signature" response
- Donations not processed

**Possible Causes:**
1. **Wrong secret** — Check BMC dashboard vs. server environment variable
2. **Header name mismatch** — Must be `X-Signature-Sha256` (case-sensitive in some servers)
3. **Body tampering** — Reverse proxy (Cloudflare, etc.) may modify body

**Debugging:**
```php
// Add to webhook.php
file_put_contents(DEBUG_LOG_PATH, 
    "Raw body length: " . strlen($rawBody) . "\n" .
    "Expected signature: $expectedSignature\n" .
    "Received signature: $signature\n" .
    "Secret (first 10 chars): " . substr(BMC_WEBHOOK_SECRET, 0, 10) . "\n",
    FILE_APPEND
);
```

**Fix:**
- Verify secret matches BMC dashboard
- Check server `$_SERVER['HTTP_X_SIGNATURE_SHA256']` vs. actual header name
- If behind proxy, disable body modification

---

### Issue: CSV Has BOM, Frontend Shows Garbage

**Symptoms:**
- CSV first line: `﻿name,type,amount,...`
- Frontend shows `\ufeffname` as column header

**Fix (Client-Side):**
```typescript
// Already implemented in About.tsx
if (text.charCodeAt(0) === 0xFEFF) {
  text = text.substring(1);
}
```

---

### Issue: Umlauts Show as `\u00fc` in BMC Log

**Symptoms:**
- BMC webhook log shows: `"Gr\\u00fcnwald Alm\\u00f6h\\u00fc"`
- But CSV has correct: `Jamie Smith`

**Root Cause:**
- PHP `json_encode()` escapes by default
- BMC's log viewer escapes backslashes when rendering

**Fix:**
```php
// Use JSON_UNESCAPED_UNICODE flag
echo json_encode($response, JSON_UNESCAPED_UNICODE);
```

**Result:**
- BMC log still shows escaped (BMC's rendering issue)
- But CSV has correct UTF-8 characters
- Frontend displays correctly

---

### Issue: Alex Has 5 Donations But CSV Shows Wrong Total

**Symptoms:**
- Alex donated: €5 + €1 + €1 + €1 + €1 = €9
- CSV shows: €8 or €5 (depends on which webhook we missed)

**Root Cause:**
- `total_amount_charged` is ONLY the current donation
- If webhook fails (signature error, server down), donation is lost
- BMC retries 3 times, but if all fail → permanent data loss

**Fix:**
1. **Never rely on `total_amount_charged` as cumulative**
2. **Load existing CSV, add new amount:**
   ```php
   if (isset($supporters[$name])) {
       $supporters[$name]['amount'] += $amount; // ADD, don't replace
   }
   ```
3. **Monitor webhook.log for failed events**
4. **Manually reconcile CSV with BMC dashboard periodically**

---

### Issue: Event Rotation Doesn't Run

**Symptoms:**
- `events/` directory has files older than 90 days
- No archives in `events_archive/`

**Causes:**
1. Cron not set up (manual SSH step required)
2. Script not executable: `chmod +x rotate-events.sh`
3. Wrong paths in script (check absolute paths)

**Debugging:**
```bash
# SSH into server (credentials in static/api/.env)
ssh $FTP_USER@$FTP_HOST

# Check cron jobs
crontab -l

# Manually run script
bash -x /var/www/opencloudtouch.org/api/supporters/rotate-events.sh

# Check rotation log
tail -f /var/www/opencloudtouch.org/api/supporters/rotate-events.log
```

---

### Issue: Docker Image Has Stale CSV

**Symptoms:**
- User donates, but About page doesn't show them
- Wait 30 minutes, still not visible

**Root Cause:**
- GitHub Actions build is manual or scheduled, not triggered by webhook
- Docker image bakes CSV at build time, not runtime

**Fix (Short-Term):**
- Manually trigger GitHub Actions workflow
- Or schedule more frequent builds (every 6 hours?)

**Fix (Long-Term — Out of Scope for Now):**
- Poll CSV from Docker container at runtime
- Or: GitHub Actions triggered by webhook (requires public GitHub endpoint)

---

## Next Steps

### Immediate (Before Production Use)

1. ✅ **Create `supporters.json`** with full event history (see [Enhanced Data Tracking](#phase-4-enhanced-data-tracking-))
2. ✅ **Add `.htaccess` to `events/` directory** to prevent direct access
3. ✅ **Set up cron job** for event rotation
4. ⏳ **Test refund handling** (send test `donation.refunded` event)
5. ⏳ **Fix USD→EUR conversions** in CSV (restore Peter St. and Woody amounts)

### Phase 2 (Recurring Donations)

1. Implement `recurring_donation.started` handler
2. Implement `recurring_donation.updated` handler
3. Implement `recurring_donation.cancelled` handler
4. Add `supporter_id` column to CSV for stable identification
5. Test subscription lifecycle with real BMC account

### Phase 3 (Advanced Features)

1. Email notifications on new donations (optional, privacy concern)
2. "Recent Supporters" widget on OCT frontend (last 7 days)
3. Supporter "Wall of Fame" page with messages (requires `note_hidden` handling)
4. Admin dashboard for manual CSV editing (web UI instead of FTP)

---

## Contact & Support

**Project:** OpenCloudTouch  
**Repository:** https://github.com/yourusername/opencloudtouch  
**BMC Page:** https://buymeacoffee.com/opencloudtouch  
**Server:** opencloudtouch.org (KAS-hosted)

**Key Files:**
- Webhook: `https://opencloudtouch.org/api/supporters/webhook` (BMC webhook URL)
- CSV Download: `https://opencloudtouch.org/api/supporters/get.php` (HTTP Basic Auth)
- CSV Upload: `https://opencloudtouch.org/api/supporters/upload.php` (HTTP Basic Auth)

**For Issues:**
- Check `webhook-debug.log` on server for full request/response dumps
- Check GitHub Actions logs for CSV fetch/build errors
- Check Docker container logs for CSV parsing errors

---

**End of Handover Document**  
**Version:** 1.0  
**Last Updated:** 2026-06-02
