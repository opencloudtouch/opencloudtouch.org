---
title: "Architektur"
weight: 2
---

OpenCloudTouch ersetzt die Bose SoundTouch Cloud-Infrastruktur durch einen lokalen Dienst in deinem Netzwerk.

## Wie Bose SoundTouch funktioniert (Original)

```mermaid
graph LR
    Speaker[🔊 SoundTouch Lautsprecher] -->|HTTPS| Bose[☁️ Bose Cloud]
    Bose -->|Presets, Radio, Updates| Speaker
    App[📱 Bose App] -->|API| Bose
```

Bose-Lautsprecher sind auf Cloud-Dienste angewiesen für Presets, Internetradio-Suche, Multi-Room-Koordination und Firmware-Updates. Als Bose diese Dienste abschaltete, verloren die Lautsprecher den Großteil ihrer smarten Funktionen.

## Wie OpenCloudTouch funktioniert

```mermaid
graph LR
    Speaker[🔊 SoundTouch Lautsprecher] -->|HTTP/SSDP| OCT[🏠 OpenCloudTouch]
    OCT -->|Presets, Radio, Steuerung| Speaker
    UI[🌐 Web UI] -->|REST API| OCT
    OCT -.->|Optional| TuneIn[📻 TuneIn API]
```

OpenCloudTouch fängt die Cloud-Aufrufe der Lautsprecher ab und fungiert als lokaler Ersatz:

- **SSDP-Erkennung** — findet Lautsprecher im Netzwerk automatisch
- **REST API** — bietet Preset-Verwaltung, Radio-Suche und Lautsprechersteuerung
- **Web UI** — browserbasierte Oberfläche für Konfiguration und Wiedergabe
- **Kein Internet nötig** — funktioniert vollständig offline (außer für Streaming von Radioinhalten)

## Komponenten

| Komponente | Technologie | Zweck |
|------------|-------------|-------|
| Backend | Python (FastAPI) | REST API, Lautsprecherkommunikation, SSDP-Erkennung |
| Frontend | React (TypeScript) | Webbasierte Steuerungsoberfläche |
| Datenbank | SQLite | Preset-Speicherung, Lautsprecher-Registrierung |
| Container | Docker | Deployment und Isolation |
| Raspberry Pi Image | Fertiges OS-Image | Sofort einsatzbereites Image mit allem vorkonfiguriert |

## Netzwerkanforderungen

OpenCloudTouch muss sich im **selben Netzwerksegment** wie deine Lautsprecher befinden. Es nutzt:

- **UDP 1900** — SSDP-Erkennung (Multicast)
- **UDP 5353** — mDNS (Multicast)
- **TCP 7777** — Web UI und REST API
