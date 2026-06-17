---
title: "Raspberry Pi Installation"
weight: 2
---

Diese Anleitung hilft dir, OpenCloudTouch auf einem Raspberry Pi zu installieren.

## Hardware-Anforderungen

> ⚠️ **Was du brauchst:**
> - **Raspberry Pi** (siehe Kompatibilitätstabelle unten)
> - **SD-Karte**: Mindestens 16 GB empfohlen (8 GB funktioniert, wird aber nach Updates knapp)
> - **Netzteil**:
>   - Pi 4/5: USB-C 5V/3A (15W)
>   - Pi 3: Micro-USB 5V/2.5A (12.5W)
>   - Pi 2: Micro-USB 5V/2A (10W)
> - **Ethernet-Kabel** ODER WLAN-Zugangsdaten

## Welches Raspberry Pi Modell brauchst du?

OpenCloudTouch benötigt mindestens **ARMv7** (32-Bit) oder **ARMv8** (64-Bit). Ältere Modelle mit ARMv6 werden nicht unterstützt.

### ✅ Unterstützte Modelle

| Modell | Empfohlenes Image | Hinweise |
|--------|-------------------|----------|
| **Raspberry Pi 5** | **arm64** | Beste Leistung |
| **Raspberry Pi 4 Model B** | **arm64** | Alle RAM-Varianten (1/2/4/8 GB) |
| **Raspberry Pi 3 Model B+** | **arm64** | Empfohlen, 32-Bit funktioniert auch |
| **Raspberry Pi 3 Model B** | **arm64** | Empfohlen, 32-Bit funktioniert auch |
| **Raspberry Pi 3 Model A+** | **arm64** | Empfohlen, 32-Bit funktioniert auch |
| **Raspberry Pi 2 Model B v1.2** | **arm64** | Nur v1.2! Board-Revision prüfen |
| **Raspberry Pi 2 Model B v1.0** | **armhf** | Original Pi 2, kein 64-Bit |
| **Raspberry Pi Zero 2 W** | **arm64** | Kleinere Bauform |

### ❌ Nicht unterstützte Modelle

- **Raspberry Pi 1** (alle Varianten) — ARMv6, zu alt
- **Raspberry Pi Zero / Zero W** (original, nicht Zero 2 W) — ARMv6, zu alt

## Welches Image brauchst du?

Es gibt zwei Images:

- **`opencloudtouch-arm64-*.img.xz`** — für Raspberry Pi 3/4/5, Zero 2 W, Pi 2 v1.2
- **`opencloudtouch-armhf-*.img.xz`** — für Raspberry Pi 2 Model B v1.0 (original von 2015)

> **Der Pi 3 hat einen 64-Bit-Prozessor!** Viele wissen das nicht, aber der Raspberry Pi 3 (alle Varianten: 3B, 3B+, 3A+) hat einen 64-Bit-fähigen ARM Cortex-A53 Prozessor. Verwende das **arm64** Image für bessere Leistung.

> **Raspberry Pi 2 Spezialfall:** Der Pi 2 Model B existiert in zwei Versionen:
> - **v1.0** (2015): BCM2836, Cortex-A7, nur 32-Bit → **armhf** Image
> - **v1.2** (Ende 2016): BCM2837, Cortex-A53, 64-Bit-fähig → **arm64** Image
>
> Prüfe die Board-Revision: `cat /proc/cpuinfo | grep Revision`
> - `a01041` / `a21041` → v1.0 (armhf)
> - `a02042` / `a22042` → v1.2 (arm64)

## Dein Modell identifizieren

### Visuelle Identifikation

1. **Schaue auf das Board**: Der Modellname ist meist in der Nähe der GPIO-Pins oder des SD-Karten-Slots aufgedruckt
   - Beispiel: "Raspberry Pi 3 Model B V1.2"

2. **Zähle die Anschlüsse**:
   - **1 HDMI-Port** (volle Größe) → Pi 2 oder Pi 3
   - **2 Micro-HDMI-Ports** → Pi 4 oder Pi 5
   - **Mini-HDMI** → Pi Zero Familie

### Software-Identifikation

Falls du bereits ein OS installiert hast:

```bash
# Modell prüfen
cat /proc/cpuinfo | grep Model

# CPU-Architektur prüfen
uname -m
# Ausgabe: aarch64 = 64-Bit, armv7l = 32-Bit, armv6l = zu alt

# Board-Revision (für Pi 2 v1.0 vs. v1.2)
cat /proc/cpuinfo | grep Revision
```

**Immer noch unsicher?** Eine ausführliche Anleitung mit Bildern und Entscheidungsbaum findest du im [Raspberry Pi Model Guide](https://github.com/opencloudtouch/opencloudtouch/blob/main/deployment/raspi-image/MODEL-GUIDE.md) im GitHub Repository.

## Installation

### 1. Image herunterladen

Lade das passende Image von der [GitHub Releases](https://github.com/opencloudtouch/opencloudtouch/releases) Seite herunter:

- Für **Pi 3/4/5, Zero 2 W, Pi 2 v1.2**: `opencloudtouch-arm64-*.img.xz`
- Für **Pi 2 Model B v1.0**: `opencloudtouch-armhf-*.img.xz`

### 2. Image auf SD-Karte flashen

#### Mit Raspberry Pi Imager (empfohlen)

1. Lade den [Raspberry Pi Imager](https://www.raspberrypi.com/software/) herunter und installiere ihn
2. Starte Raspberry Pi Imager
3. Klicke auf **"Eigenes verwenden"** (oder "Use custom") → wähle die heruntergeladene `.img.xz` Datei
4. Wähle deine SD-Karte (16 GB empfohlen, 8 GB Minimum)
5. **Wichtig:** Klicke auf das Zahnrad-Symbol (⚙️) für erweiterte Einstellungen:
   - Hostname setzen (z.B. `opencloudtouch`)
   - SSH aktivieren (mit Passwort oder Public-Key)
   - WLAN konfigurieren (falls kein Ethernet verfügbar)
   - Optional: Zeitzone und Tastaturlayout
6. Klicke auf **"Schreiben"** und warte bis der Vorgang abgeschlossen ist

#### Mit Balena Etcher

1. Lade [Balena Etcher](https://www.balena.io/etcher/) herunter
2. Wähle die `.img.xz` Datei
3. Wähle deine SD-Karte
4. Klicke auf **"Flash!"**

#### Manuell mit `dd` (Linux/macOS)

```bash
# Entpacken
xz -d opencloudtouch-arm64-*.img.xz

# Auf SD-Karte schreiben (ACHTUNG: /dev/sdX durch deine SD-Karte ersetzen!)
sudo dd if=opencloudtouch-arm64-*.img of=/dev/sdX bs=4M status=progress conv=fsync

# Sync sicherstellen
sync
```

### 3. WLAN konfigurieren (für Headless-Betrieb)

Falls du die WLAN-Konfiguration nicht über Raspberry Pi Imager gemacht hast:

1. Nach dem Flashen: SD-Karte aus dem Computer entfernen und wieder einstecken
2. Die Boot-Partition sollte als Laufwerk erscheinen
3. Öffne die Datei `oct-config.txt` auf der Boot-Partition
4. Trage deine WLAN-Daten ein:

```ini
# oct-config.txt — OpenCloudTouch Konfiguration
WIFI_SSID=DeinNetzwerkName
WIFI_PASSWORD=DeinWLANPasswort
WIFI_COUNTRY=DE
OCT_PORT=7777
```

5. Speichern und SD-Karte auswerfen

### 4. Erster Start

1. SD-Karte in den Raspberry Pi einlegen
2. Stromversorgung anschließen
3. **Warten** (~2–3 Minuten beim ersten Start):
   - 🟢 **Grüne ACT-LED blinkt aktiv**: Boot läuft (Dateisystem-Erweiterung, Docker-Pull)
   - 🟢 **Grüne LED ruhig/selten blinkend**: Boot abgeschlossen, System bereit
   - 🔴 **Rote PWR-LED leuchtet dauerhaft**: Stromversorgung OK
   - ⚠️ **Rote LED blinkt**: Unterspannung → stärkeres Netzteil verwenden

### 5. Zugriff auf OpenCloudTouch

Nach dem ersten Start kannst du auf OpenCloudTouch zugreifen:

```text
http://opencloudtouch.local:7777
```

**Falls `.local` nicht funktioniert** (z.B. auf Windows ohne Bonjour):

1. **Im Router nachschauen**: Webinterface deines Routers → Geräteliste → "opencloudtouch" oder "oct"

2. **`arp` Befehl** (Windows PowerShell/CMD, macOS/Linux Terminal):
   ```bash
   # Windows
   arp -a | findstr "b8-27-eb dc-a6-32 e4-5f-01"
   
   # macOS/Linux
   arp -a | grep -E "b8:27:eb|dc:a6:32|e4:5f:01"
   ```
   (Raspberry Pi MAC-Adressen beginnen mit b8:27:eb, dc:a6:32 oder e4:5f:01)

3. **IP-Scanner**: [Angry IP Scanner](https://angryip.org/) oder [Advanced IP Scanner](https://www.advanced-ip-scanner.com/de/) (Windows)

4. **Nmap** (Linux/macOS):
   ```bash
   nmap -sn 192.168.1.0/24
   # Ersetze 192.168.1 mit deinem Subnetz
   ```

## Standard-Login (SSH)

Falls du SSH aktiviert hast:

```bash
ssh oct@opencloudtouch.local
```

> ⚠️ **KRITISCH — Passwort sofort ändern!**
> - **Benutzername**: `oct`
> - **Standard-Passwort**: `opencloudtouch`
> - **Dieses Passwort ist öffentlich bekannt** — ändere es nach dem ersten Login:
>   ```bash
>   passwd
>   ```
> - Ein unverändertes Passwort ist ein Sicherheitsrisiko!

## Lautsprecher werden nicht gefunden?

Falls deine Bose SoundTouch Lautsprecher nicht automatisch erkannt werden:

1. Prüfe dass Raspberry Pi und Lautsprecher im **gleichen Netzwerk** sind
2. Manche Router blockieren Multicast (SSDP) zwischen WLAN und Ethernet → Prüfe Router-Einstellungen
3. Falls du VLAN / Guest-Netzwerke verwendest: IGMP Snooping und Multicast-Forwarding aktivieren

Detaillierte Netzwerk-Troubleshooting-Tipps findest du unter [Netzwerkkonfiguration]({{< ref "/docs/network-config" >}}).

## Wie geht's weiter?

- [Erste Schritte]({{< ref "/docs/getting-started" >}}) — Presets und Radio konfigurieren
- [Netzwerkkonfiguration]({{< ref "/docs/network-config" >}}) — Firewall, DNS und erweiterte Netzwerk-Einstellungen
- [FAQ]({{< ref "/docs/faq" >}}) — häufig gestellte Fragen
- [Raspberry Pi Model Guide (GitHub)](https://github.com/opencloudtouch/opencloudtouch/blob/main/deployment/raspi-image/MODEL-GUIDE.md) — ausführliche Modell-Identifikation

## Empfehlungen

- **Bestes Preis-Leistungs-Verhältnis**: Raspberry Pi 4 (2 GB oder 4 GB)
- **Budget-Option**: Raspberry Pi 3 Model B+ (falls du bereits einen hast)
- **Neueste Generation**: Raspberry Pi 5 (overkill aber funktioniert perfekt)
- **Kompakt**: Pi Zero 2 W (falls Platz knapp ist, aber langsamere Performance)
