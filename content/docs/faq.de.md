---
title: "FAQ"
weight: 10
---

## Allgemein

{{< details title="Was ist OpenCloudTouch?" >}}
OpenCloudTouch ist ein Open-Source-Ersatz für die Bose SoundTouch Cloud-Dienste. Es läuft lokal in deinem Netzwerk und stellt Funktionen wieder her, die verloren gingen als Bose seine Cloud-Infrastruktur abschaltete.
{{< /details >}}

{{< details title="Welche Lautsprecher werden unterstützt?" >}}
Alle Bose SoundTouch Lautsprecher werden unterstützt:

| Modell | Status |
|--------|--------|
| SoundTouch 10 | ✅ Verifiziert |
| SoundTouch 20 | ✅ Verifiziert |
| SoundTouch 30 | ✅ Verifiziert |
| SoundTouch 300 (Soundbar) | ✅ Verifiziert |
| Wave SoundTouch Music System IV | ✅ Verifiziert |
| SoundTouch SA-5 Amplifier | ⚠️ Experimentell — siehe [Community-Diskussion](https://github.com/opencloudtouch/opencloudtouch/discussions/67) |
| Lifestyle 535/600/650 | 🔬 Ungetestet — siehe [Diskussion #214](https://github.com/opencloudtouch/opencloudtouch/discussions/214) für Community-Fortschritt |

Wenn "SoundTouch" im Namen steht, sollte es funktionieren.
{{< /details >}}

{{< details title="Muss ich meine Lautsprecher modifizieren?" >}}
Nein. OpenCloudTouch funktioniert mit unveränderten Lautsprechern. Keine Firmware-Änderungen, kein Löten, keine Hardware-Modifikationen. Deine Garantie (falls noch vorhanden) bleibt bestehen.
{{< /details >}}

{{< details title="Braucht es Internetzugang?" >}}
OpenCloudTouch selbst funktioniert vollständig offline. Allerdings benötigt das Streaming von Internetradio-Stationen natürlich eine Internetverbindung für den Audio-Stream.
{{< /details >}}

## Einrichtung

{{< details title="Welche Hardware brauche ich?" >}}
Ein Raspberry Pi 4 oder 5 ist das empfohlene Setup. Wir bieten auch **fertige Raspberry Pi Images** an — einfach auf eine SD-Karte flashen, booten und loslegen.

Jeder Linux-Rechner, NAS oder Docker-fähige Host in deinem lokalen Netzwerk funktioniert ebenfalls. Mindestanforderungen: 512 MB RAM, 1 CPU-Kern, 100 MB Speicherplatz.
{{< /details >}}

{{< details title="Kann ich es auf einem NAS (Synology, QNAP) betreiben?" >}}
Ja! Wenn dein NAS Docker-Container unterstützt, kannst du OpenCloudTouch darauf ausführen. Nutze `--network host` für automatische Lautsprechererkennung.
{{< /details >}}

{{< details title="Können mehrere Instanzen im selben Netzwerk laufen?" >}}
Du solltest nur eine Instanz pro Netzwerk betreiben. Mehrere Instanzen konkurrieren um die Lautsprechersteuerung und verursachen Konflikte.
{{< /details >}}

## Fehlerbehebung

{{< details title="Meine Lautsprecher werden nicht angezeigt" >}}

1. Stelle sicher dass OpenCloudTouch und deine Lautsprecher im **selben Subnetz** sind
2. Prüfe ob Multicast-Traffic (UDP 1900) nicht von deinem Router/Firewall blockiert wird
3. Bei Docker Desktop auf macOS/Windows funktioniert Multicast nicht in der VM — siehe [Netzwerkkonfiguration]({{< ref "/docs/network-config" >}})
4. Versuche deine Lautsprecher neu zu starten (10 Sekunden vom Strom trennen)
5. Wenn die automatische Erkennung nicht funktioniert, kannst du Lautsprecher-IPs manuell in der OpenCloudTouch Web-Oberfläche hinzufügen

{{< /details >}}

{{< details title="Presets werden nicht gespeichert" >}}
Prüfe ob das Daten-Volume in Docker korrekt gemountet ist. Ohne persistenten Speicher gehen Presets bei Container-Neustart verloren:

```bash
docker run -v oct-data:/data ...
```

{{< /details >}}

## Mitmachen

{{< details title="Wie kann ich helfen?" >}}

- [Einen Bug melden](https://github.com/opencloudtouch/opencloudtouch/issues/new?template=bug_report.yml)
- [Ein Feature vorschlagen](https://github.com/opencloudtouch/opencloudtouch/issues/new?template=feature_request.yml)
- Ideen diskutieren in [GitHub Discussions](https://github.com/opencloudtouch/opencloudtouch/discussions)
- Pull Requests einreichen — siehe den Contributing Guide im Repository
- Mit verschiedenen Lautsprechermodellen testen und Kompatibilität melden

{{< /details >}}

{{< details title="Ich habe ein Sicherheitsproblem gefunden" >}}
Bitte melde Sicherheitslücken vertraulich über [GitHub Security Advisories](https://github.com/opencloudtouch/opencloudtouch/security/advisories/new) oder per E-Mail an [security@opencloudtouch.org](mailto:security@opencloudtouch.org). Erstelle **keine** öffentlichen Issues für Sicherheitslücken.
{{< /details >}}
