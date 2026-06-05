# Integration Tests - BuyMeACoffee Webhook API
# Runs against the LIVE server at opencloudtouch.org
# All tests use live_mode=false (no HMAC signature required)
# Run: powershell.exe -NoProfile -ExecutionPolicy Bypass -File test-integration.ps1

param(
    [string]$BaseUrl = "https://opencloudtouch.org/api/supporters"
)

$ErrorActionPreference = "Stop"
$testsDir = Join-Path $PSScriptRoot "tests"
$pass = 0
$fail = 0

function Assert-Response {
    param([string]$Response, [string]$ExpectedKey, [string]$Description)

    if ($Response -match [regex]::Escape($ExpectedKey)) {
        $script:pass++
        Write-Information "  PASS $Description" -InformationAction Continue
        Write-Information "       $Response" -InformationAction Continue
    } else {
        $script:fail++
        Write-Information "  FAIL $Description" -InformationAction Continue
        Write-Information "       Expected: $ExpectedKey" -InformationAction Continue
        Write-Information "       Got:      $Response" -InformationAction Continue
    }
}

function Test-WebhookEvent {
    param(
        [string]$TestName,
        [string]$JsonFile,
        [hashtable[]]$Assertions
    )
    Write-Host "--- $TestName ---" -ForegroundColor Yellow
    $f = Join-Path $testsDir $JsonFile
    $r = curl.exe -s -X POST "$BaseUrl/webhook.php" -H "Content-Type: application/json" -d "@$f" 2>&1
    foreach ($a in $Assertions) {
        Assert-Response $r $a.Expected $a.Description
    }
    Write-Host ""
}

Write-Host "=== Integration Tests: $BaseUrl ===" -ForegroundColor Cyan
Write-Host ""

# --- Test 1: donation.created ---
Test-WebhookEvent "Test 1: donation.created" "test_donation.json" @(
    @{ Expected = '"success":true'; Description = "donation.created returns success" },
    @{ Expected = "Integration Test"; Description = "Supporter name in response" }
)

# --- Test 2: recurring_donation.started ---
Test-WebhookEvent "Test 2: recurring_donation.started" "test_subscription_started.json" @(
    @{ Expected = '"success":true'; Description = "subscription.started returns success" },
    @{ Expected = "monthly"; Description = "Monthly rate in response" }
)

# --- Test 3: recurring_donation.cancelled ---
Test-WebhookEvent "Test 3: recurring_donation.cancelled" "test_subscription_cancelled.json" @(
    @{ Expected = '"success":true'; Description = "subscription.cancelled returns success" },
    @{ Expected = "cancelled"; Description = "Action=cancelled in response" }
)

# --- Test 4: donation.refunded ---
Test-WebhookEvent "Test 4: donation.refunded" "test_refund.json" @(
    @{ Expected = '"success":true'; Description = "refund returns success" },
    @{ Expected = "refunded"; Description = "Refunded amount in response" }
)

# --- Test 5: Invalid JSON ---
Write-Host "--- Test 5: Error Handling ---" -ForegroundColor Yellow
$r = curl.exe -s -X POST "$BaseUrl/webhook.php" -H "Content-Type: application/json" -d "not-json" 2>&1
Assert-Response $r "error" "Invalid JSON returns error"

$tempFile2 = Join-Path $env:TEMP "oct-test-empty.json"
Set-Content -Path $tempFile2 -Value " " -NoNewline
$r = curl.exe -s -X POST "$BaseUrl/webhook.php" -H "Content-Type: application/json" -d "@$tempFile2" 2>&1
Remove-Item $tempFile2 -ErrorAction SilentlyContinue
Assert-Response $r "error" "Empty body returns error"
Write-Host ""

# --- Test 6: Unknown event type ---
Write-Host "--- Test 6: Unknown Event Type ---" -ForegroundColor Yellow
$tempFile = Join-Path $env:TEMP "oct-test-unknown.json"
Set-Content -Path $tempFile -Value '{"type":"some.future.event","data":{"supporter_name":"Nobody"},"event_id":111111,"live_mode":false}' -NoNewline
$r = curl.exe -s -X POST "$BaseUrl/webhook.php" -H "Content-Type: application/json" -d "@$tempFile" 2>&1
Remove-Item $tempFile -ErrorAction SilentlyContinue
Assert-Response $r "archived" "Unknown event archived with 200 OK"
Write-Host ""

# --- Test 7: Signature rejection ---
Write-Host "--- Test 7: Signature Rejection ---" -ForegroundColor Yellow
$tempFile = Join-Path $env:TEMP "oct-test-sig.json"
Set-Content -Path $tempFile -Value '{"type":"donation.created","data":{"supporter_name":"Hacker","total_amount_charged":"999.99"},"event_id":1,"live_mode":true}' -NoNewline
$r = curl.exe -s -X POST "$BaseUrl/webhook.php" -H "Content-Type: application/json" -d "@$tempFile" 2>&1
Remove-Item $tempFile -ErrorAction SilentlyContinue
# Note: Server returns 200 instead of 403 (shared hosting PHP-CGI limitation)
# but the response body correctly rejects the request
Assert-Response $r "Invalid signature" "live_mode=true without signature is rejected"
Write-Host ""

# --- Summary ---
Write-Host "========================================" -ForegroundColor Cyan
$total = $pass + $fail
if ($fail -gt 0) {
    Write-Host "Results: $pass of $total passed - $fail FAILED" -ForegroundColor Red
} else {
    Write-Host "Results: $pass of $total passed - ALL GREEN" -ForegroundColor Green
}
Write-Host "========================================" -ForegroundColor Cyan

if ($fail -gt 0) { exit 1 } else { exit 0 }
