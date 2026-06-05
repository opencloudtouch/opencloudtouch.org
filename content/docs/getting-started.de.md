---
title: "Erste Schritte"
weight: 1
---

OpenCloudTouch in drei Schritten zum Laufen bringen.

## Voraussetzungen

- Ein **Raspberry Pi 4/5** (oder ein beliebiger Linux-Rechner / Docker-Host in deinem Netzwerk)
- Ein oder mehrere **Bose SoundTouch** Lautsprecher im selben Netzwerk

## Option A: Raspberry Pi Image (Am einfachsten)

Lade das fertige Raspberry Pi Image von der [GitHub Releases](https://github.com/opencloudtouch/opencloudtouch/releases) Seite herunter. Flashe es mit dem [Raspberry Pi Imager](https://www.raspberrypi.com/software/) auf eine SD-Karte, starte deinen Pi, und fertig — OpenCloudTouch startet automatisch.

## Option B: Docker Compose

```bash
# Repository klonen
git clone https://github.com/opencloudtouch/opencloudtouch.git
cd opencloudtouch

# Mit Docker Compose starten
docker compose up -d
```

Der Dienst startet standardmäßig auf Port **7777**.

## 2. Lautsprecher finden

OpenCloudTouch erkennt SoundTouch-Lautsprecher in deinem Netzwerk automatisch per SSDP-Multicast. Öffne die Web-Oberfläche:

```text
http://<deine-host-ip>:7777
```

Deine Lautsprecher sollten innerhalb weniger Sekunden erscheinen.

## 3. Presets & Radio konfigurieren

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
