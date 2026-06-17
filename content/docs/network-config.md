---
title: "Network Configuration"
weight: 3
---

OpenCloudTouch relies on multicast networking for speaker discovery. Most setups work out of the box, but some environments need extra configuration.

## Standard Setup (Raspberry Pi / Linux)

No special configuration needed. Plug the Pi into your network, start the container with `--network host`, and discovery works immediately.

```bash
docker run -d --network host --name opencloudtouch \
  ghcr.io/opencloudtouch/opencloudtouch:latest
```

## Docker Desktop (macOS / Windows)

Docker Desktop runs containers inside a VM, which blocks multicast by default.

> [!WARNING]
> **Docker Desktop does not support `--network host` on macOS and Windows.** You need to use port mapping and configure your firewall to allow multicast traffic.

```bash
docker run -d -p 7777:7777 \
  --name opencloudtouch \
  -v opencloudtouch-data:/data \
  ghcr.io/opencloudtouch/opencloudtouch:latest
```

Speaker discovery may not work automatically in this setup. You can add speaker IPs manually in the OpenCloudTouch web interface.

## WSL2 (Windows Subsystem for Linux)

WSL2 requires specific configuration for multicast to work:

### 1. Enable Mirrored Networking

Edit `%USERPROFILE%\.wslconfig`:

```ini
[wsl2]
networkingMode=mirrored
```

Restart WSL: `wsl --shutdown`

### 2. Windows Firewall Rules

```powershell
New-NetFirewallRule -DisplayName "WSL SSDP Discovery" `
    -Direction Inbound -Action Allow -Protocol UDP `
    -LocalPort 1900,5353 -Program "System"
```

### 3. Run Container in Rootful Mode (Podman only)

> [!NOTE]
> This step applies to **Podman** users only. Docker for Windows/WSL2 does not support this command — use `--network host` directly in your Docker run command instead.

```bash
# Podman users only:
podman machine set --rootful
podman run --network host ...
```

## Firewall Ports

| Port | Protocol | Direction | Purpose |
|------|----------|-----------|---------|
| 1900 | UDP | In/Out | SSDP Discovery |
| 5353 | UDP | In/Out | mDNS |
| 7777 | TCP | In | Web UI / REST API |

## Troubleshooting

### Speakers not discovered

1. Verify your host can see multicast traffic: `tcpdump -i any udp port 1900`
2. Check that no firewall blocks UDP 1900
3. Ensure speaker and host are on the same VLAN/subnet
4. If automatic discovery doesn't work, you can add speaker IPs manually in the OpenCloudTouch web interface
