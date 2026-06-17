---
title: "Raspberry Pi Installation"
weight: 2
---

This guide helps you install OpenCloudTouch on a Raspberry Pi.

## Hardware Requirements

> ⚠️ **What You Need:**
> - **Raspberry Pi** (see compatibility table below)
> - **SD Card**: 16 GB minimum recommended (8 GB works, but tight after updates)
> - **Power Supply**:
>   - Pi 4/5: USB-C 5V/3A (15W)
>   - Pi 3: Micro-USB 5V/2.5A (12.5W)
>   - Pi 2: Micro-USB 5V/2A (10W)
> - **Ethernet cable** OR Wi-Fi credentials

## Which Raspberry Pi Model Do You Need?

OpenCloudTouch requires at least **ARMv7** (32-bit) or **ARMv8** (64-bit). Older ARMv6 models are not supported.

### ✅ Supported Models

| Model | Recommended Image | Notes |
|-------|-------------------|-------|
| **Raspberry Pi 5** | **arm64** | Best performance |
| **Raspberry Pi 4 Model B** | **arm64** | All RAM variants (1/2/4/8 GB) |
| **Raspberry Pi 3 Model B+** | **arm64** | Recommended, 32-bit also works |
| **Raspberry Pi 3 Model B** | **arm64** | Recommended, 32-bit also works |
| **Raspberry Pi 3 Model A+** | **arm64** | Recommended, 32-bit also works |
| **Raspberry Pi 2 Model B v1.2** | **arm64** | Only v1.2! Check board revision |
| **Raspberry Pi 2 Model B v1.0** | **armhf** | Original Pi 2, no 64-bit |
| **Raspberry Pi Zero 2 W** | **arm64** | Smaller form factor |

### ❌ Not Supported

- **Raspberry Pi 1** (all variants) — ARMv6, too old
- **Raspberry Pi Zero / Zero W** (original, not Zero 2 W) — ARMv6, too old

## Which Image Do You Need?

There are two images:

- **`opencloudtouch-arm64-*.img.xz`** — for Raspberry Pi 3/4/5, Zero 2 W, Pi 2 v1.2
- **`opencloudtouch-armhf-*.img.xz`** — for Raspberry Pi 2 Model B v1.0 (original from 2015)

> **The Pi 3 has a 64-bit processor!** Many people don't know this, but the Raspberry Pi 3 (all variants: 3B, 3B+, 3A+) has a 64-bit capable ARM Cortex-A53 processor. Use the **arm64** image for better performance.

> **Raspberry Pi 2 Special Case:** The Pi 2 Model B exists in two versions:
> - **v1.0** (2015): BCM2836, Cortex-A7, 32-bit only → **armhf** image
> - **v1.2** (late 2016): BCM2837, Cortex-A53, 64-bit capable → **arm64** image
>
> Check the board revision: `cat /proc/cpuinfo | grep Revision`
> - `a01041` / `a21041` → v1.0 (armhf)
> - `a02042` / `a22042` → v1.2 (arm64)

## Identifying Your Model

### Visual Identification

1. **Look at the board**: Model name is usually printed near the GPIO pins or SD card slot
   - Example: "Raspberry Pi 3 Model B V1.2"

2. **Count the connectors**:
   - **1 HDMI port** (full-size) → Pi 2 or Pi 3
   - **2 micro-HDMI ports** → Pi 4 or Pi 5
   - **Mini HDMI** → Pi Zero family

### Software Identification

If you already have an OS running:

```bash
# Check model
cat /proc/cpuinfo | grep Model

# Check CPU architecture
uname -m
# Output: aarch64 = 64-bit, armv7l = 32-bit, armv6l = too old

# Check board revision (for Pi 2 v1.0 vs. v1.2)
cat /proc/cpuinfo | grep Revision
```

**Still unsure?** A detailed guide with images and decision tree is available in the [Raspberry Pi Model Guide](https://github.com/opencloudtouch/opencloudtouch/blob/main/deployment/raspi-image/MODEL-GUIDE.md) on GitHub.

## Installation

### 1. Download the Image

Download the appropriate image from the [GitHub Releases](https://github.com/opencloudtouch/opencloudtouch/releases) page:

- For **Pi 3/4/5, Zero 2 W, Pi 2 v1.2**: `opencloudtouch-arm64-*.img.xz`
- For **Pi 2 Model B v1.0**: `opencloudtouch-armhf-*.img.xz`

### 2. Flash to SD Card

#### Using Raspberry Pi Imager (Recommended)

1. Download and install [Raspberry Pi Imager](https://www.raspberrypi.com/software/)
2. Launch Raspberry Pi Imager
3. Click **"Use custom"** → select the downloaded `.img.xz` file
4. Select your SD card (16 GB recommended, 8 GB minimum)
5. **Important:** Click the gear icon (⚙️) for advanced settings:
   - Set hostname (e.g., `opencloudtouch`)
   - Enable SSH (with password or public key)
   - Configure Wi-Fi (if no Ethernet available)
   - Optional: Set timezone and keyboard layout
6. Click **"Write"** and wait for completion

#### Using Balena Etcher

1. Download [Balena Etcher](https://www.balena.io/etcher/)
2. Select the `.img.xz` file
3. Select your SD card
4. Click **"Flash!"**

#### Manually with `dd` (Linux/macOS)

```bash
# Decompress
xz -d opencloudtouch-arm64-*.img.xz

# Write to SD card (WARNING: replace /dev/sdX with your SD card!)
sudo dd if=opencloudtouch-arm64-*.img of=/dev/sdX bs=4M status=progress conv=fsync

# Ensure sync
sync
```

### 3. Configure Wi-Fi (for Headless Operation)

If you didn't configure Wi-Fi through Raspberry Pi Imager:

1. After flashing: Remove SD card from computer and reinsert
2. The boot partition should appear as a drive
3. Open the file `oct-config.txt` on the boot partition
4. Enter your Wi-Fi credentials:

```ini
# oct-config.txt — OpenCloudTouch Configuration
WIFI_SSID=YourNetworkName
WIFI_PASSWORD=YourWiFiPassword
WIFI_COUNTRY=US
OCT_PORT=7777
```

5. Save and eject the SD card

### 4. First Boot

1. Insert SD card into Raspberry Pi
2. Connect power
3. **Wait** (~2–3 minutes on first boot):
   - 🟢 **Green ACT LED blinking actively**: Boot in progress (filesystem expansion, Docker pull)
   - 🟢 **Green LED calm/occasional blink**: Boot complete, system ready
   - 🔴 **Red PWR LED solid**: Power supply OK
   - ⚠️ **Red LED blinking**: Undervoltage → use stronger power supply

### 5. Access OpenCloudTouch

After first boot, you can access OpenCloudTouch:

```text
http://opencloudtouch.local:7777
```

**If `.local` doesn't work** (e.g., on Windows without Bonjour):

1. **Check your router**: Web interface → Device list → "opencloudtouch" or "oct"

2. **`arp` command** (Windows PowerShell/CMD, macOS/Linux Terminal):
   ```bash
   # Windows
   arp -a | findstr "b8-27-eb dc-a6-32 e4-5f-01"
   
   # macOS/Linux
   arp -a | grep -E "b8:27:eb|dc:a6:32|e4:5f:01"
   ```
   (Raspberry Pi MAC addresses start with b8:27:eb, dc:a6:32, or e4:5f:01)

3. **IP Scanner**: [Angry IP Scanner](https://angryip.org/) or [Advanced IP Scanner](https://www.advanced-ip-scanner.com/) (Windows)

4. **Nmap** (Linux/macOS):
   ```bash
   nmap -sn 192.168.1.0/24
   # Replace 192.168.1 with your subnet
   ```

## Default Login (SSH)

If you enabled SSH:

```bash
ssh oct@opencloudtouch.local
```

> ⚠️ **CRITICAL — Change Password Immediately!**
> - **Username**: `oct`
> - **Default Password**: `opencloudtouch`
> - **This password is publicly known** — change it after first login:
>   ```bash
>   passwd
>   ```
> - Leaving the default password is a security risk!

## Speakers Not Being Discovered?

If your Bose SoundTouch speakers aren't automatically detected:

1. Ensure Raspberry Pi and speakers are on the **same network**
2. Some routers block multicast (SSDP) between Wi-Fi and Ethernet → Check router settings
3. If using VLANs / Guest networks: Enable IGMP snooping and multicast forwarding

Detailed network troubleshooting tips are available in [Network Configuration]({{< ref "/docs/network-config" >}}).

## What's Next?

- [Getting Started]({{< ref "/docs/getting-started" >}}) — Configure presets and radio
- [Network Configuration]({{< ref "/docs/network-config" >}}) — Firewall, DNS, and advanced network settings
- [FAQ]({{< ref "/docs/faq" >}}) — Frequently asked questions
- [Raspberry Pi Model Guide (GitHub)](https://github.com/opencloudtouch/opencloudtouch/blob/main/deployment/raspi-image/MODEL-GUIDE.md) — Detailed model identification

## Recommendations

- **Best value**: Raspberry Pi 4 (2 GB or 4 GB)
- **Budget option**: Raspberry Pi 3 Model B+ (if you already have one)
- **Latest & greatest**: Raspberry Pi 5 (overkill but works perfectly)
- **Compact**: Pi Zero 2 W (if space is tight, but slower performance)
