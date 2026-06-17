---
title: "Erste Schritte"
weight: 1
---

OpenCloudTouch in drei Schritten zum Laufen bringen.

## Voraussetzungen

- Ein **Raspberry Pi 3/4/5** oder **Zero 2 W** (oder ein beliebiger Linux-Rechner / Docker-Host in deinem Netzwerk)
- Ein oder mehrere **Bose SoundTouch** Lautsprecher im selben Netzwerk

## Wichtig vor der ersten Nutzung

OpenCloudTouch ist nicht nur ein Docker-Dienst. Um Bose-Cloud-Aufrufe vollständig zu ersetzen, braucht jeder Lautsprecher einen **einmaligen Onboarding-Schritt**:

- Aktuelle Gerätekonfiguration sichern (Backup)
- Cloud-Endpunkte auf deinen OCT-Host umleiten (zum Beispiel Config-URL und/oder `/etc/hosts`-Einträge)
- Setup verifizieren und dauerhaft machen

Der Setup-Wizard führt durch diese Schritte. Je nach Modell/Firmware erfolgt das Onboarding via SSH/USB oder über den Telnet-Diagnosezugang.

> [!WARNING]
> Dieses Onboarding verändert die Gerätekonfiguration. OpenCloudTouch erstellt Backups und unterstützt Restore, trotzdem können Konfigurationsänderungen je nach Region und Gerät Garantie-Auswirkungen haben.

> **Wichtig:** Der Raspberry Pi 3 ist 64-Bit-fähig! Viele wissen das nicht, aber alle Pi 3 Modelle (3B, 3B+, 3A+) haben einen 64-Bit-Prozessor. Auch der Pi 2 Model B v1.2 (nicht v1.0) ist 64-Bit-fähig. Siehe [Raspberry Pi Installation]({{< ref "/docs/raspberry-pi" >}}) für Details.

## 1. OpenCloudTouch installieren (eine Methode wählen)

### Option A: Raspberry Pi Image (Am einfachsten)

Lade das fertige Raspberry Pi Image von der [GitHub Releases](https://github.com/opencloudtouch/opencloudtouch/releases) Seite herunter. Es gibt zwei Images:

- **`opencloudtouch-arm64-*.img.xz`** — für Raspberry Pi 3/4/5, Zero 2 W, Pi 2 v1.2
- **`opencloudtouch-armhf-*.img.xz`** — für Raspberry Pi 2 Model B v1.0

Flashe es mit dem [Raspberry Pi Imager](https://www.raspberrypi.com/software/) auf eine SD-Karte, starte deinen Pi, und fertig — OpenCloudTouch startet automatisch.

**Detaillierte Installationsanleitung:** [Raspberry Pi Installation]({{< ref "/docs/raspberry-pi" >}})

### Option B: Docker Compose

```bash
# Repository klonen
git clone https://github.com/opencloudtouch/opencloudtouch.git
cd opencloudtouch

# Mit Docker Compose starten
docker compose -f deployment/docker-compose.yml up -d
```

Der Dienst startet standardmäßig auf Port **7777**.

## 2. Setup-Wizard ausführen (einmal pro Lautsprecher)

Öffne die OCT-Weboberfläche und führe den Setup-Wizard für jeden Lautsprecher aus:

```text
http://<deine-host-ip>:7777
```

Der Wizard führt durch Backup, Umleitung, Verifikation und optionale Restore-Pfade.

## 3. Lautsprecher finden

OpenCloudTouch erkennt SoundTouch-Lautsprecher in deinem Netzwerk automatisch per SSDP-Multicast. Öffne die Web-Oberfläche:

```text
http://<deine-host-ip>:7777
```

Deine Lautsprecher sollten innerhalb weniger Sekunden erscheinen.

## 4. Presets & Radio konfigurieren

Nutze die Web-Oberfläche um:

- **Presets** (1–6) deinen Lieblingsstationen zuzuweisen
- **Internetradio**-Stationen zu durchsuchen und hinzuzufügen
- **Multi-Room-Gruppen** (Zonen) zu erstellen
- **Lautstärke** und **Wiedergabe** zu steuern

> [!WARNING]
> **WSL2-Nutzer:** Multicast-Erkennung erfordert `networkingMode=mirrored` in `.wslconfig` plus eine Firewall-Regel für UDP-Ports 1900 und 5353. Siehe [Netzwerkkonfiguration]({{< ref "/docs/network-config" >}}) für Details.

## Wie geht's weiter?

- [Architektur-Überblick]({{< ref "/docs/architecture" >}}) — wie OpenCloudTouch die Bose-Cloud ersetzt
- [Netzwerkkonfiguration]({{< ref "/docs/network-config" >}}) — Firewall, DNS und WSL2-Tipps
- [FAQ]({{< ref "/docs/faq" >}}) — häufig gestellte Fragen
