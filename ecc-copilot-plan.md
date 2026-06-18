# ECC Native Copilot Migration Plan

## Evidenz

ECC bietet **native GitHub Copilot Unterstuetzung** seit v2.0.0-rc.1.
Quelle: `docs/de-DE/README.md#github-copilot-unterstuetzung`

### Was ECC nativ liefert (8 Dateien)

| # | ECC-Quelle | Zweck | Copilot-Mechanismus |
|---|-----------|-------|---------------------|
| 1 | `.github/copilot-instructions.md` | Stets aktive Rules (Coding-Style, Security, Testing, Git) | Automatisch in jede Chat-Anfrage injiziert |
| 2 | `.vscode/settings.json` | Task-spezifische Instruction-Overlays | `codeGeneration`, `testGeneration`, `reviewSelection`, `commitMessage` |
| 3 | `.github/prompts/plan.prompt.md` | Phasenweise Implementierungsplanung | Manuell via Attach > Prompt oder `/` |
| 4 | `.github/prompts/tdd.prompt.md` | Red-Green-Improve TDD-Zyklus | Manuell via Attach > Prompt oder `/` |
| 5 | `.github/prompts/code-review.prompt.md` | Qualitaets- und Sicherheitsreview | Manuell via Attach > Prompt oder `/` |
| 6 | `.github/prompts/security-review.prompt.md` | OWASP-orientierte Sicherheitsanalyse | Manuell via Attach > Prompt oder `/` |
| 7 | `.github/prompts/build-fix.prompt.md` | Systematische Build-/CI-Fehlerbehebung | Manuell via Attach > Prompt oder `/` |
| 8 | `.github/prompts/refactor.prompt.md` | Dead-Code-Cleanup, Vereinfachung | Manuell via Attach > Prompt oder `/` |

### Was Copilot NICHT unterstuetzt (ECC-Einschraenkungen)

- **Hooks** -- kein Hook-System (keine Auto-Formatierung, kein Dev-Server-Guard)
- **Agents/Subagents** -- keine native Subagent-API (aber VS Code `.agent.md` als Workaround)
- **Skills** -- kein Skill-Lademechanismus (stattdessen `.instructions.md` mit `applyTo`)
- **MCP-Server** -- nicht ueber Copilot verfuegbar
- **Custom Tools** -- nicht verfuegbar

### Wie Copilot Dateien laedt (Evidenz aus VS Code Docs)

| Dateityp | Pfad | Verhalten |
|----------|------|-----------|
| `copilot-instructions.md` | `.github/copilot-instructions.md` | Automatisch in JEDE Chat-Anfrage injiziert |
| `.prompt.md` | `.github/prompts/*.prompt.md` | Manuell aufrufbar via Attach-Symbol oder `/` |
| `.instructions.md` | beliebig, mit YAML `applyTo` | Automatisch geladen wenn Datei mit Pattern matcht |
| `settings.json` | `.vscode/settings.json` | Task-spezifische Overlays fuer Code/Test/Review/Commit |

---

## Ist-Zustand opencloudtouch

### Vorhanden (korrekt)
- `.github/copilot-instructions.md` -- erweitert mit Projekt-Kontext + ECC-Prinzipien
- `.vscode/settings.json` -- 4 Copilot-Settings bereits gemerged

### Zu entfernen (alter Hack, nicht-nativer Ansatz)
- `.github/instructions/` -- 33 konvertierte `.instructions.md` Dateien
- `.github/ecc-skills/` -- 16 tote Skill-Dateien (Copilot laedt sie nicht)
- `.gitignore`-Eintraege fuer diese Verzeichnisse

### Fehlend
- `.github/prompts/` -- 6 ECC-Workflow-Prompts
- `chat.promptFiles: true` in `.vscode/settings.json`

---

## Migrations-Tasks

### Task 1: Alte Artefakte entfernen

| Aktion | Pfad | Grund |
|--------|------|-------|
| DELETE dir | `.github/instructions/` (33 Dateien) | Nicht-nativer ECC-Ansatz |
| DELETE dir | `.github/ecc-skills/` (16 Dateien) | Copilot laedt keine Skills |
| EDIT | `.gitignore` | Eintraege fuer instructions/ und ecc-skills/ entfernen |

### Task 2: ECC-Prompts kopieren (1:1, keine Anpassung)

| Quelle (ECC) | Ziel (opencloudtouch) |
|--------------|----------------------|
| `ECC/.github/prompts/plan.prompt.md` | `.github/prompts/plan.prompt.md` |
| `ECC/.github/prompts/tdd.prompt.md` | `.github/prompts/tdd.prompt.md` |
| `ECC/.github/prompts/code-review.prompt.md` | `.github/prompts/code-review.prompt.md` |
| `ECC/.github/prompts/security-review.prompt.md` | `.github/prompts/security-review.prompt.md` |
| `ECC/.github/prompts/build-fix.prompt.md` | `.github/prompts/build-fix.prompt.md` |
| `ECC/.github/prompts/refactor.prompt.md` | `.github/prompts/refactor.prompt.md` |

**Warum keine Anpassung?** Die Prompts sind bewusst stack-agnostisch.
Projekt-Kontext kommt aus `copilot-instructions.md` (automatisch dazu injiziert).

### Task 3: copilot-instructions.md -- Prompt-Library-Verweis ergaenzen

Einzige Aenderung: Verweis auf verfuegbare Prompts am Ende der Datei (wie im ECC-Original).

### Task 4: settings.json -- `chat.promptFiles` sicherstellen

Pruefen/setzen: `"chat.promptFiles": true` (noetig damit VS Code die Prompts erkennt).

### Task 5: Validierung

- [ ] `copilot-instructions.md` wird automatisch geladen
- [ ] 6 Prompt-Dateien erscheinen im Copilot-Chat Attach-Picker
- [ ] `chat.promptFiles: true` in settings.json
- [ ] Keine toten Dateien mehr
- [ ] .gitignore sauber

---

## Ergebnis-Struktur

```
opencloudtouch/
  .github/
    copilot-instructions.md          <-- Stets aktiv (Projekt + ECC Rules)
    prompts/
      plan.prompt.md                 <-- /plan
      tdd.prompt.md                  <-- /tdd
      code-review.prompt.md          <-- /code-review
      security-review.prompt.md      <-- /security-review
      build-fix.prompt.md            <-- /build-fix
      refactor.prompt.md             <-- /refactor
  .vscode/
    settings.json                    <-- Copilot Task-Overlays + chat.promptFiles
```

## Feature-Abdeckung

| ECC-Feature | Copilot-Entsprechung | Status |
|-------------|---------------------|--------|
| Coding-Standards | copilot-instructions.md (stets aktiv) | OK |
| Sicherheits-Checkliste | copilot-instructions.md + security-review.prompt.md | OK |
| TDD | copilot-instructions.md + tdd.prompt.md | OK |
| Implementierungsplanung | plan.prompt.md | OK |
| Code-Review | code-review.prompt.md | OK |
| Build-Fehlerbehebung | build-fix.prompt.md | OK |
| Refactoring | refactor.prompt.md | OK |
| Commit-Nachrichten | settings.json commitMessageGeneration | OK |
| Hooks/Automatisierung | Copilot-Limitation -- nicht moeglich | N/A |
| Agents/Delegation | Copilot-Limitation -- nicht moeglich | N/A |
