---
title: "Getting Started"
weight: 1
---

Get OpenCloudTouch running in three steps.

## Prerequisites

- A **Raspberry Pi 3/4/5** or **Zero 2 W** (or any Linux machine / Docker host on your network)
- One or more **Bose SoundTouch** speakers on the same network

## Important Before First Use

OpenCloudTouch is not only a server you start in Docker. To fully replace Bose cloud calls, each speaker needs a **one-time onboarding step**:

- Back up the current speaker configuration
- Redirect cloud endpoints to your OCT host (for example config URL and/or `/etc/hosts` entries)
- Verify and persist the setup

This is guided by the setup wizard. Depending on model/firmware, onboarding is done via SSH/USB or via Telnet diagnostic access.

> [!WARNING]
> This onboarding changes speaker-side configuration. OpenCloudTouch creates backups and supports restore, but configuration changes can still have warranty implications depending on region and device.

> **Important:** The Raspberry Pi 3 is 64-bit capable! Many people don't know this, but all Pi 3 models (3B, 3B+, 3A+) have a 64-bit processor. The Pi 2 Model B v1.2 (not v1.0) is also 64-bit capable. See [Raspberry Pi Installation]({{< ref "/docs/raspberry-pi" >}}) for details.

## 1. Install OpenCloudTouch (Choose One Method)

### Option A: Raspberry Pi Image (Easiest)

Download the pre-built Raspberry Pi image from the [GitHub Releases](https://github.com/opencloudtouch/opencloudtouch/releases) page. There are two images:

- **`opencloudtouch-arm64-*.img.xz`** — for Raspberry Pi 3/4/5, Zero 2 W, Pi 2 v1.2
- **`opencloudtouch-armhf-*.img.xz`** — for Raspberry Pi 2 Model B v1.0

Flash it to an SD card using [Raspberry Pi Imager](https://www.raspberrypi.com/software/), boot your Pi, and you're done — OpenCloudTouch starts automatically.

**Detailed installation guide:** [Raspberry Pi Installation]({{< ref "/docs/raspberry-pi" >}})

### Option B: Docker Compose

```bash
# Clone the repository
git clone https://github.com/opencloudtouch/opencloudtouch.git
cd opencloudtouch

# Start with Docker Compose
docker compose -f deployment/docker-compose.yml up -d
```

The service starts on port **7777** by default.

## 2. Run Setup Wizard (One-Time per Speaker)

Open the OCT web UI and run the setup wizard for each speaker:

```text
http://<your-host-ip>:7777
```

The wizard guides backup, redirect, verification, and optional restore paths.

## 3. Discover Your Speakers

OpenCloudTouch automatically discovers SoundTouch speakers on your network via SSDP multicast. Open the web UI:

```text
http://<your-host-ip>:7777
```

Your speakers should appear within a few seconds.

## 4. Configure Presets & Radio

Use the web interface to:

- Assign **presets** (1–6) to your favorite stations
- Browse and add **internet radio** stations
- Create **multi-room groups** (zones)
- Control **volume** and **playback**

> [!WARNING]
> **WSL2 users:** Multicast discovery requires `networkingMode=mirrored` in `.wslconfig` plus a firewall rule for UDP ports 1900 and 5353. See [Network Configuration]({{< ref "/docs/network-config" >}}) for details.

## What's Next?

- [Architecture Overview]({{< ref "/docs/architecture" >}}) — how OpenCloudTouch replaces the Bose cloud
- [Network Configuration]({{< ref "/docs/network-config" >}}) — firewall, DNS, and WSL2 tips
- [FAQ]({{< ref "/docs/faq" >}}) — common questions answered
