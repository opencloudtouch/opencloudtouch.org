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

Write-Host "=== Integration Tests: $BaseUrl ===" -ForegroundColor Cyan
Write-Host ""

# --- Test 1: donation.created ---
Write-Host "--- Test 1: donation.created ---" -ForegroundColor Yellow
$f = Join-Path $testsDir "test_donation.json"
$r = curl.exe -s -X POST "$BaseUrl/webhook.php" -H "Content-Type: application/json" -d "@$f" 2>&1
Assert-Response $r '"success":true' "donation.created returns success"
Assert-Response $r "Integration Test" "Supporter name in response"
Write-Host ""

# --- Test 2: recurring_donation.started ---
Write-Host "--- Test 2: recurring_donation.started ---" -ForegroundColor Yellow
$f = Join-Path $testsDir "test_subscription_started.json"
$r = curl.exe -s -X POST "$BaseUrl/webhook.php" -H "Content-Type: application/json" -d "@$f" 2>&1
Assert-Response $r '"success":true' "subscription.started returns success"
Assert-Response $r "monthly" "Monthly rate in response"
Write-Host ""

# --- Test 3: recurring_donation.cancelled ---
Write-Host "--- Test 3: recurring_donation.cancelled ---" -ForegroundColor Yellow
$f = Join-Path $testsDir "test_subscription_cancelled.json"
$r = curl.exe -s -X POST "$BaseUrl/webhook.php" -H "Content-Type: application/json" -d "@$f" 2>&1
Assert-Response $r '"success":true' "subscription.cancelled returns success"
Assert-Response $r "cancelled" "Action=cancelled in response"
Write-Host ""

# --- Test 4: donation.refunded ---
Write-Host "--- Test 4: donation.refunded ---" -ForegroundColor Yellow
$f = Join-Path $testsDir "test_refund.json"
$r = curl.exe -s -X POST "$BaseUrl/webhook.php" -H "Content-Type: application/json" -d "@$f" 2>&1
Assert-Response $r '"success":true' "refund returns success"
Assert-Response $r "refunded" "Refunded amount in response"
Write-Host ""

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
