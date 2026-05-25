# OpenCloudTouch Website Setup Guide

Setup guide for `opencloudtouch.org` on ALL-INKL Webspace with Hugo static site.

## 1. ALL-INKL: Domain & Webspace

1. **KAS Login:** https://kas.all-inkl.com/
2. **Domain anlegen:** Tools → Domains → Neue Domain → `opencloudtouch.org`
3. **Webspace zuweisen:** Domain auf Webspace zeigen lassen (Standard-Verzeichnis: `/opencloudtouch.org/`)
4. **SSL aktivieren:** Domain → SSL-Schutz → Let's Encrypt → Aktivieren (Haken bei "Auto-Renew")
5. **Warten:** DNS-Propagation kann bis zu 24h dauern, SSL-Zertifikat wird erst nach DNS-Auflösung ausgestellt

## 2. ALL-INKL: E-Mail-Adressen

1. **KAS → E-Mail → Neues Postfach:**
   - `security@opencloudtouch.org` — mit Autoresponder (siehe Abschnitt 4)
   - Optional: `info@opencloudtouch.org`
2. **Autoresponder aktivieren:** Siehe Abschnitt 4

## 3. ALL-INKL: FTP-Zugang

1. **KAS → FTP → FTP-Benutzer:** Existierenden User nutzen oder neuen anlegen
2. **Credentials notieren** (für GitHub Secret):
   - Host: `ftp.opencloudtouch.org` (oder von KAS angezeigte Adresse)
   - User: FTP-Benutzername
   - Password: FTP-Passwort
   - Pfad: `/opencloudtouch.org/` (Zielverzeichnis)

## 4. ALL-INKL: Autoresponder für security@

1. **KAS → E-Mail → Postfach `security@opencloudtouch.org` → Autoresponder**
2. **Aktivieren:** Ja
3. **Betreff:** `[OpenCloudTouch] Security Report Received`
4. **Text:**

```
Thank you for reporting a security issue to OpenCloudTouch.

This is an automated confirmation that your message has been received.
We take security reports seriously and will respond within 48 hours.

What happens next:
- Your report will be reviewed by a project maintainer
- If confirmed, a fix will be developed privately
- A security advisory will be published after the fix is released
- You will be credited in the release notes (unless you prefer otherwise)

For faster response, you can also use GitHub Private Vulnerability Reporting:
https://github.com/opencloudtouch/opencloudtouch/security/advisories/new

Please do NOT create public GitHub issues for security vulnerabilities.

---
OpenCloudTouch Security Team
https://opencloudtouch.org
https://github.com/opencloudtouch/opencloudtouch
```

5. **Intervall:** 1x pro Absender innerhalb von 24h (verhindert Spam-Loops)

## 4b. ALL-INKL: Autoresponder für info@

1. **KAS → E-Mail → Postfach `info@opencloudtouch.org` → Autoresponder**
2. **Aktivieren:** Ja
3. **Betreff:** `[OpenCloudTouch] Thanks for reaching out`
4. **Text:**

```
Thanks for your message to OpenCloudTouch.

This is an automated reply to let you know we got your email.
A human will get back to you as soon as possible — typically within a few business days.

In the meantime, these resources might already answer your question:

- Project & Documentation: https://github.com/opencloudtouch/opencloudtouch
- Website: https://opencloudtouch.org
- Discussions & Q&A: https://github.com/opencloudtouch/opencloudtouch/discussions

If you're reporting a security vulnerability, please use our dedicated channel instead:
security@opencloudtouch.org

---
OpenCloudTouch
https://opencloudtouch.org
```

5. **Intervall:** 1x pro Absender innerhalb von 24h (verhindert Spam-Loops)

## 5. GitHub: Website-Repository erstellen

```bash
# Neues öffentliches Repo auf GitHub erstellen
gh repo create opencloudtouch/opencloudtouch.org --public --description "Project website for OpenCloudTouch" --clone
cd opencloudtouch.org
```

## 6. Hugo installieren & Site scaffolden

```bash
# Hugo installieren (Windows)
winget install Hugo.Hugo.Extended

# Neue Hugo-Site erstellen
hugo new site . --force

# Theme installieren (Beispiel: hugo-book, clean & docs-friendly)
git submodule add https://github.com/alex-shpak/hugo-book themes/hugo-book
```

### `hugo.toml` (Minimal-Config)

```toml
baseURL = "https://opencloudtouch.org/"
languageCode = "en"
title = "OpenCloudTouch"
theme = "hugo-book"

[params]
  description = "Free your Bose SoundTouch speakers from the cloud"
  github = "https://github.com/opencloudtouch/opencloudtouch"

[menu]
  [[menu.main]]
    name = "GitHub"
    url = "https://github.com/opencloudtouch/opencloudtouch"
    weight = 100
```

### Initiale Seiten

```bash
# Homepage
cat > content/_index.md << 'EOF'
---
title: "OpenCloudTouch"
---

# OpenCloudTouch

**Free your Bose SoundTouch speakers from the cloud.**

OpenCloudTouch is an open-source replacement for the Bose SoundTouch cloud services, keeping your speakers fully functional without depending on Bose's servers.

[Get Started]({{< ref "/docs/getting-started" >}}) | [GitHub](https://github.com/opencloudtouch/opencloudtouch)
EOF

mkdir -p content/docs
cat > content/docs/_index.md << 'EOF'
---
title: "Documentation"
weight: 1
---

# Documentation

Get started with OpenCloudTouch.
EOF
```

## 7. GitHub Action: Auto-Deploy

Erstelle `.github/workflows/deploy.yml`:

```yaml
name: Deploy Website

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          submodules: true

      - name: Setup Hugo
        uses: peaceiris/actions-hugo@v3
        with:
          hugo-version: 'latest'
          extended: true

      - name: Build
        run: hugo --minify

      - name: Deploy via FTP
        uses: SamKirkland/FTP-Deploy-Action@v4.3.5
        with:
          server: ${{ secrets.FTP_HOST }}
          username: ${{ secrets.FTP_USER }}
          password: ${{ secrets.FTP_PASSWORD }}
          local-dir: ./public/
          server-dir: /opencloudtouch.org/
```

### GitHub Secrets setzen

```bash
gh secret set FTP_HOST -R opencloudtouch/opencloudtouch.org
gh secret set FTP_USER -R opencloudtouch/opencloudtouch.org
gh secret set FTP_PASSWORD -R opencloudtouch/opencloudtouch.org
```

## 8. Testen

```bash
# Lokal testen
hugo server -D
# → http://localhost:1313/

# Deployen
git add -A
git commit -m "feat: initial website"
git push
# → GitHub Action baut und deployt automatisch
```

## 9. Checkliste

- [ ] Domain `opencloudtouch.org` bei ALL-INKL angelegt
- [ ] Webspace zugewiesen
- [ ] Let's Encrypt SSL aktiviert
- [ ] E-Mail `security@opencloudtouch.org` angelegt
- [ ] Autoresponder konfiguriert
- [ ] FTP-Zugang notiert
- [ ] GitHub Repo `opencloudtouch/opencloudtouch.org` erstellt
- [ ] Hugo installiert & Site gebaut
- [ ] GitHub Secrets (FTP) gesetzt
- [ ] Erster Deploy erfolgreich
- [ ] HTTPS funktioniert
- [ ] Autoresponder getestet (Test-Mail an security@)
