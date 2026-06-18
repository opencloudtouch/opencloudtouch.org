# Supporters API — Local Testing Script

Write-Host "=== OpenCloudTouch Supporters API Test ===" -ForegroundColor Cyan
Write-Host ""

# Configuration
$baseUrl = "https://opencloudtouch.org/api/supporters"
$user = "oct-ci"
$pass = Read-Host "Enter API password" -AsSecureString
$passPlain = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($pass))

# Test 1: Upload CSV
Write-Host "Test 1: Upload CSV" -ForegroundColor Yellow
if (Test-Path "apps/frontend/public/supporters.csv") {
    $csv = Get-Content "apps/frontend/public/supporters.csv" -Raw
    $auth = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("${user}:${passPlain}"))
    
    try {
        $response = Invoke-RestMethod -Uri "$baseUrl/upload.php" -Method Post -Body $csv -Headers @{Authorization="Basic $auth"}
        Write-Host "✅ Upload successful" -ForegroundColor Green
        Write-Host "   Supporters: $($response.supporters_uploaded)"
        Write-Host "   Backup: $($response.backup_created)"
    } catch {
        Write-Host "❌ Upload failed: $($_.Exception.Message)" -ForegroundColor Red
    }
} else {
    Write-Host "⚠️  No CSV found at apps/frontend/public/supporters.csv" -ForegroundColor Yellow
}

Write-Host ""

# Test 2: Download CSV
Write-Host "Test 2: Download CSV" -ForegroundColor Yellow
try {
    $auth = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("${user}:${passPlain}"))
    $downloaded = Invoke-RestMethod -Uri "$baseUrl/get.php" -Method Get -Headers @{Authorization="Basic $auth"}
    
    $lines = $downloaded -split "`n"
    Write-Host "✅ Download successful" -ForegroundColor Green
    Write-Host "   Lines: $($lines.Count)"
    Write-Host "   First 3 supporters:"
    $lines[1..3] | ForEach-Object { Write-Host "   $_" }
} catch {
    Write-Host "❌ Download failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Test 3: Webhook (requires BMC secret)
Write-Host "Test 3: Webhook Simulation" -ForegroundColor Yellow
Write-Host "⚠️  Skipped (requires BMC webhook secret for HMAC)" -ForegroundColor Yellow
Write-Host "   Use BMC Dashboard → Webhooks → 'Send Test Event' button" -ForegroundColor Gray

Write-Host ""
Write-Host "=== Tests Complete ===" -ForegroundColor Cyan
