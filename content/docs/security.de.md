---
title: "Sicherheit"
weight: 90
toc: false
---

## Schwachstellen melden

Wenn du eine Sicherheitslücke in OpenCloudTouch entdeckst, melde sie bitte verantwortungsvoll:

1. **Bevorzugt:** [GitHub Private Vulnerability Reporting](https://github.com/opencloudtouch/opencloudtouch/security/advisories/new)
2. **E-Mail:** [security@opencloudtouch.org](mailto:security@opencloudtouch.org)

> [!CAUTION]
> **Erstelle KEINE öffentlichen GitHub Issues für Sicherheitslücken.** Nutze die oben genannten vertraulichen Kanäle.

## Was passiert danach

- Dein Bericht wird innerhalb von **48 Stunden** bestätigt
- Ein Fix wird vertraulich entwickelt
- Ein Security Advisory wird nach Veröffentlichung des Fixes publiziert
- Du wirst als Entdecker genannt (es sei denn, du bevorzugst Anonymität)

## Umfang

OpenCloudTouch läuft in deinem lokalen Netzwerk. Die primäre Angriffsfläche ist:

- REST API (Port 7777)
- SSDP/mDNS Multicast-Listener

Wir nehmen alle Meldungen ernst, auch für rein lokale Netzwerkdienste.
