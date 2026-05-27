---
title: "Netzwerkkonfiguration"
weight: 3
---

OpenCloudTouch nutzt Multicast-Netzwerk für die Lautsprechererkennung. Die meisten Setups funktionieren sofort, aber einige Umgebungen benötigen zusätzliche Konfiguration.

## Standard-Setup (Raspberry Pi / Linux)

Keine besondere Konfiguration nötig. Stecke den Pi in dein Netzwerk, starte den Container mit `--network host`, und die Erkennung funktioniert sofort.

```bash
docker run -d --network host --name opencloudtouch \
  ghcr.io/opencloudtouch/opencloudtouch:latest
```

## Docker Desktop (macOS / Windows)

Docker Desktop führt Container in einer VM aus, die Multicast standardmäßig blockiert.

> [!WARNING]
> **Docker Desktop unterstützt `--network host` auf macOS und Windows nicht.** Du musst Port-Mapping nutzen und deine Firewall für Multicast-Traffic konfigurieren.

```bash
docker run -d -p 7777:7777 \
  --name opencloudtouch \
  ghcr.io/opencloudtouch/opencloudtouch:latest
```

Die Lautsprechererkennung funktioniert in diesem Setup möglicherweise nicht automatisch. Du kannst Lautsprecher-IPs manuell in der OpenCloudTouch Web-Oberfläche hinzufügen.

## WSL2 (Windows Subsystem for Linux)

WSL2 benötigt eine spezielle Konfiguration damit Multicast funktioniert:

### 1. Mirrored Networking aktivieren

Bearbeite `%USERPROFILE%\.wslconfig`:

```ini
[wsl2]
networkingMode=mirrored
```

WSL neustarten: `wsl --shutdown`

### 2. Windows-Firewall-Regeln

```powershell
New-NetFirewallRule -DisplayName "WSL SSDP Discovery" `
    -Direction Inbound -Action Allow -Protocol UDP `
    -LocalPort 1900,5353 -Program "System"
```

### 3. Podman/Docker im Rootful-Modus ausführen

```bash
podman machine set --rootful
podman run --network host ...
```

## Firewall-Ports

| Port | Protokoll | Richtung | Zweck |
|------|-----------|----------|-------|
| 1900 | UDP | Ein/Aus | SSDP-Erkennung |
| 5353 | UDP | Ein/Aus | mDNS |
| 7777 | TCP | Ein | Web UI / REST API |

## Fehlerbehebung

### Lautsprecher werden nicht gefunden

1. Überprüfe ob dein Host Multicast-Traffic sehen kann: `tcpdump -i any udp port 1900`
2. Prüfe dass keine Firewall UDP 1900 blockiert
3. Stelle sicher dass Lautsprecher und Host im selben VLAN/Subnetz sind
4. Wenn die automatische Erkennung nicht funktioniert, kannst du Lautsprecher-IPs manuell in der OpenCloudTouch Web-Oberfläche hinzufügen
