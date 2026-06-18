# Quick CSV Upload Script
# Uploads supporters.csv without redeploying entire API

param(
    [string]$PropertiesFile = "deploy.properties",
    [string]$CSVFile = "..\..\..\..\apps\frontend\public\supporters.csv"
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $PropertiesFile)) {
    Write-Host "❌ Properties file not found: $PropertiesFile" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path $CSVFile)) {
    Write-Host "❌ CSV file not found: $CSVFile" -ForegroundColor Red
    exit 1
}

# Parse properties
$config = @{}
Get-Content $PropertiesFile | Where-Object { $_ -match '^\s*[^#]' -and $_ -match '=' } | ForEach-Object {
    $key, $value = $_ -split '=', 2
    $config[$key.Trim()] = $value.Trim()
}

# Upload via API
Write-Host "📤 Uploading $CSVFile..." -ForegroundColor Yellow

$csv = Get-Content $CSVFile -Raw
$apiUrl = "https://$($config['ftp.host'].Replace('ftp.', ''))/api/supporters/upload.php"
$auth = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("$($config['api.user']):$($config['api.pass'])"))

try {
    $response = Invoke-RestMethod -Uri $apiUrl -Method Post -Body $csv -Headers @{Authorization="Basic $auth"}
    Write-Host "✅ Upload successful" -ForegroundColor Green
    Write-Host "   Supporters: $($response.supporters_uploaded)" -ForegroundColor Gray
    Write-Host "   Backup: $($response.backup_created)" -ForegroundColor Gray
} catch {
    Write-Host "❌ Upload failed: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
