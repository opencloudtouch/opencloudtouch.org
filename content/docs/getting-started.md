---
title: "Getting Started"
weight: 1
---

# Getting Started

Get OpenCloudTouch running in three steps.

## Prerequisites

- A **Raspberry Pi 4/5** (or any Linux machine / Docker host on your network)
- **Docker** and **Docker Compose** installed
- One or more **Bose SoundTouch** speakers on the same network

## 1. Deploy OpenCloudTouch

```bash
# Clone the repository
git clone https://github.com/opencloudtouch/opencloudtouch.git
cd opencloudtouch

# Start with Docker Compose
docker compose up -d
```

The service starts on port **8090** by default.

## 2. Discover Your Speakers

OpenCloudTouch automatically discovers SoundTouch speakers on your network via SSDP multicast. Open the web UI:

```
http://<your-host-ip>:8090
```

Your speakers should appear within a few seconds.

## 3. Configure Presets & Radio

Use the web interface to:
- Assign **presets** (1–6) to your favorite stations
- Browse and add **internet radio** stations
- Create **multi-room groups** (zones)
- Control **volume** and **playback**

{{< hint warning >}}
**WSL2 users:** Multicast discovery requires `networkingMode=mirrored` in `.wslconfig` plus a firewall rule for UDP ports 1900 and 5353. See [Network Configuration]({{< ref "/docs/network-config" >}}) for details.
{{< /hint >}}

## What's Next?

- [Architecture Overview]({{< ref "/docs/architecture" >}}) — how OpenCloudTouch replaces the Bose cloud
- [Network Configuration]({{< ref "/docs/network-config" >}}) — firewall, DNS, and WSL2 tips
- [FAQ]({{< ref "/docs/faq" >}}) — common questions answered
