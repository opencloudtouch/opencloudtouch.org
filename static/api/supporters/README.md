# Supporters API — Setup & Usage

Automated supporter tracking system for OpenCloudTouch using BuyMeACoffee webhooks.

## 🚀 Quick Start (5 Minutes)

### 1. Create Config File

Copy the example:
```powershell
cd C:\DEV\private\opencloudtouch.org\static\api\supporters
Copy-Item deploy.properties.example deploy.properties
```

Edit `deploy.properties` with your credentials:
```properties
ftp.host=ftp.your-hosting.com
ftp.user=your_ftp_username
ftp.pass=your_ftp_password
ftp.path=/public_html/api/supporters

bmc.webhook.secret=wh_sec_xxxxx  # From BMC Dashboard
api.user=oct-ci
api.pass=your_strong_password
```

### 2. Deploy to Server

```powershell
.\deploy-supporters-api.ps1 -UploadCSV -Test
```

Done! The script:
- ✅ Generates `.env.php` with your secrets
- ✅ Uploads all PHP files + `.htaccess` via FTP
- ✅ Uploads initial `supporters.csv`
- ✅ Tests the API endpoint

### 3. Configure BuyMeACoffee Webhook

1. Go to [BMC Dashboard](https://www.buymeacoffee.com/dashboard/settings) → **Webhooks**
2. Add webhook URL: `https://opencloudtouch.org/api/supporters/webhook.php`
3. The secret is already configured (from `deploy.properties`)
4. Click **"Send Test Event"** to test

### 4. Add GitHub Secrets

Repository → Settings → Secrets → New:
- `SUPPORTERS_API_USER` = `oct-ci` (from deploy.properties)
- `SUPPORTERS_API_PASS` = your password (from deploy.properties)

---

## Architecture

```
BuyMeACoffee → Webhook → opencloudtouch.org/api/supporters/webhook.php
                              ↓ (stores)
                         supporters.csv
                              ↑ (fetches)
GitHub Actions Build ← HTTP GET (Basic Auth)
```

## Files

- **webhook.php** — Receives BMC webhooks (HMAC auth)
- **get.php** — Downloads CSV (Basic Auth)
- **upload.php** — Initial CSV upload (Basic Auth)
- **.htaccess** — Protects sensitive files
- **.env.php** — Secrets (NOT in Git!)
- **supporters.csv** — Data file (protected by .htaccess)

---

## 🚀 Initial Setup

### 1. Configure Secrets

Copy the example config:
```bash
cp .env.php.example .env.php
```

Edit `.env.php` with your actual secrets:
```php
define('BMC_WEBHOOK_SECRET', 'wh_sec_xxxxx'); // From BMC Dashboard
define('API_USER', 'oct-ci');
define('API_PASS', 'your_strong_password_here');
```

### 2. Set File Permissions

```bash
chmod 600 .env.php              # Only webserver can read
chmod 644 supporters.csv        # Readable by PHP
chmod 644 supporters.csv.lock   # Lock file for concurrent writes
```

### 3. Upload Initial CSV

Merge your BuyMeACoffee CSV exports (one-time + monthly) into a single CSV:
```csv
name,type,amount,monthlyAmount,firstSupportDate
John Doe,one-time,23.5,0,2026-05-15
Jane Smith,monthly,0,5.83,2026-04-01
```

Upload to server:
```powershell
$user = "oct-ci"
$pass = "your_strong_password_here"
$uri = "https://opencloudtouch.org/api/supporters/upload.php"
$csv = Get-Content .local/supporters.csv -Raw

$auth = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("${user}:${pass}"))
Invoke-RestMethod -Uri $uri -Method Post -Body $csv -Headers @{Authorization="Basic $auth"}
```

Response:
```json
{
  "status": "ok",
  "supporters_uploaded": 25,
  "backup_created": "supporters.csv.backup.2026-06-02_143052"
}
```

### 4. Configure BMC Webhook

1. Go to [BuyMeACoffee Dashboard](https://www.buymeacoffee.com/dashboard/settings) → **Webhooks**
2. Add webhook URL: `https://opencloudtouch.org/api/supporters/webhook.php`
3. Copy the **Webhook Secret** (starts with `wh_sec_`)
4. Add secret to `.env.php` as `BMC_WEBHOOK_SECRET`
5. Test webhook using BMC's "Send Test Event" button

---

## 🧪 Testing

### Test Webhook (Manual)

Simulate BMC webhook payload:
```bash
curl -X POST https://opencloudtouch.org/api/supporters/webhook.php \
  -H "Content-Type: application/json" \
  -H "X-Buymecoffee-Signature: $(echo -n '{"supporter_name":"Test User","support_coffees":3}' | openssl dgst -sha256 -hmac 'wh_sec_xxxxx' -binary | base64)" \
  -d '{"supporter_name":"Test User","support_coffees":3}'
```

Expected response:
```json
{
  "status": "ok",
  "supporter": "Test User",
  "amount": 15.0,
  "is_monthly": false
}
```

### Test Download (GitHub Actions)

```bash
curl -u "oct-ci:your_password" https://opencloudtouch.org/api/supporters/get.php -o supporters.csv
```

### Test via BMC Dashboard

1. Go to BMC Webhooks settings
2. Click **"Send Test Event"** button
3. Check server logs: `tail -f /var/log/apache2/error.log` (or your PHP error log)
4. Verify CSV updated: `curl -u "oct-ci:pass" https://opencloudtouch.org/api/supporters/get.php | tail -5`

---

## 🔧 GitHub Actions Integration

Add secrets to GitHub repository:
- `SUPPORTERS_API_USER` = `oct-ci`
- `SUPPORTERS_API_PASS` = your password

In `.github/workflows/build.yml`:
```yaml
- name: Fetch supporters
  env:
    API_USER: ${{ secrets.SUPPORTERS_API_USER }}
    API_PASS: ${{ secrets.SUPPORTERS_API_PASS }}
  run: |
    curl -u "$API_USER:$API_PASS" \
      https://opencloudtouch.org/api/supporters/get.php \
      -o apps/frontend/public/supporters.csv || \
    echo "name,type,amount,monthlyAmount,firstSupportDate" > apps/frontend/public/supporters.csv
```

---

## 🛡️ Security

✅ **Webhook**: HMAC SHA-256 signature validation  
✅ **Download/Upload**: HTTP Basic Auth  
✅ **File Protection**: .htaccess blocks direct CSV access  
✅ **Secrets**: .env.php excluded from webroot listing  
✅ **Concurrent Access**: File locking for race conditions  
✅ **Backups**: Auto-backup on upload  

---

## 📊 BuyMeACoffee Webhook Payload

Example payload from BMC:
```json
{
  "supporter_name": "John Doe",
  "payer_email": "john@example.com",
  "support_coffees": 3,
  "support_note": "Great project!",
  "support_created_on": "2026-06-02T14:30:00Z",
  "subscription_id": null,
  "is_subscription_active": false
}
```

**Fields we use:**
- `supporter_name` → CSV name
- `support_coffees` → Amount (1 coffee = $5)
- `subscription_id` → If set = monthly supporter

---

## 🔍 Troubleshooting

### Webhook Returns 403 "Invalid signature"

1. Check `.env.php` has correct `BMC_WEBHOOK_SECRET`
2. Verify BMC sends header `X-Buymecoffee-Signature`
3. Check PHP error log for HMAC mismatch details

### Upload Returns 401 "Unauthorized"

1. Verify credentials in PowerShell script match `.env.php`
2. Check Basic Auth header format: `Authorization: Basic <base64(user:pass)>`

### CSV Not Updating

1. Check file permissions: `ls -la supporters.csv`
2. Verify PHP can write: `touch supporters.csv.test && rm supporters.csv.test`
3. Check `.htaccess` isn't blocking writes
4. Review PHP error log

### 404 on PHP files

1. Ensure Apache has PHP module enabled: `apache2 -M | grep php`
2. Check `.htaccess` allows .php access
3. Verify files copied to `public/` after Hugo build

---

## 📝 Maintenance

### Manual CSV Update

1. Download current CSV: `curl -u user:pass https://opencloudtouch.org/api/supporters/get.php > current.csv`
2. Edit locally
3. Re-upload: `curl -u user:pass https://opencloudtouch.org/api/supporters/upload.php --data-binary @current.csv`

### View Recent Supporters

```bash
curl -u user:pass https://opencloudtouch.org/api/supporters/get.php | tail -10
```

### Check Backups

```bash
ssh your-server
ls -lh /path/to/opencloudtouch.org/public/api/supporters/supporters.csv.backup.*
```
