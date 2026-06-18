#!/usr/bin/env pwsh
<#
.SYNOPSIS
    Installs ECC native Copilot support into opencloudtouch.
    Based on: ECC docs/de-DE/README.md#github-copilot-unterstuetzung

.DESCRIPTION
    Task 1: Remove old hack artifacts (.github/instructions/, .github/ecc-skills/)
    Task 2: Copy 6 ECC prompt files to .github/prompts/
    Task 3: Add prompt library reference to copilot-instructions.md
    Task 4: Ensure chat.promptFiles=true in .vscode/settings.json
    Task 5: Clean .gitignore

.PARAMETER DryRun
    Show what would be done without writing files.
#>

[CmdletBinding()]
param(
    [switch]$DryRun
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$EccRoot = "C:\DEV\private\ECC"
$ProjectRoot = "C:\DEV\private\opencloudtouch"

function Write-Step { param([string]$msg) Write-Host "  [STEP] $msg" -ForegroundColor Cyan }
function Write-OK   { param([string]$msg) Write-Host "  [OK]   $msg" -ForegroundColor Green }
function Write-Skip { param([string]$msg) Write-Host "  [SKIP] $msg" -ForegroundColor Yellow }

Write-Host ""
Write-Host "========================================================" -ForegroundColor Magenta
Write-Host "  ECC Native Copilot -> opencloudtouch" -ForegroundColor Magenta
Write-Host "========================================================" -ForegroundColor Magenta
Write-Host ""

# --- Validation ----------------------------------------------------------
if (-not (Test-Path (Join-Path $EccRoot ".github\copilot-instructions.md"))) {
    Write-Host "  [ERR] ECC not found at: $EccRoot" -ForegroundColor Red
    exit 1
}

# --- Task 1: Remove old hack artifacts -----------------------------------
Write-Step "Task 1: Remove old artifacts"

$oldInstructions = Join-Path $ProjectRoot ".github\instructions"
$oldSkills = Join-Path $ProjectRoot ".github\ecc-skills"

foreach ($dir in @($oldInstructions, $oldSkills)) {
    if (Test-Path $dir) {
        $count = (Get-ChildItem $dir -File).Count
        if ($DryRun) {
            Write-Host "    WOULD DELETE: $dir ($count files)" -ForegroundColor DarkGray
        }
        else {
            Remove-Item $dir -Recurse -Force
            Write-OK "Deleted $dir ($count files)"
        }
    }
    else {
        Write-Skip "$dir does not exist"
    }
}

# --- Task 2: Copy 6 ECC prompt files -------------------------------------
Write-Step "Task 2: Copy ECC prompt files"

$promptsDir = Join-Path $ProjectRoot ".github\prompts"
$eccPromptsDir = Join-Path $EccRoot ".github\prompts"

if (-not $DryRun) {
    New-Item -ItemType Directory -Path $promptsDir -Force | Out-Null
}

$prompts = @(
    "plan.prompt.md"
    "tdd.prompt.md"
    "code-review.prompt.md"
    "security-review.prompt.md"
    "build-fix.prompt.md"
    "refactor.prompt.md"
)

foreach ($prompt in $prompts) {
    $src = Join-Path $eccPromptsDir $prompt
    $dst = Join-Path $promptsDir $prompt
    if (Test-Path $src) {
        if ($DryRun) {
            Write-Host "    WOULD COPY: $prompt" -ForegroundColor DarkGray
        }
        else {
            Copy-Item -Path $src -Destination $dst -Force
            Write-OK $prompt
        }
    }
    else {
        Write-Host "  [ERR] Missing: $src" -ForegroundColor Red
    }
}

# --- Task 3: Add prompt library to copilot-instructions.md ---------------
Write-Step "Task 3: Add prompt library reference to copilot-instructions.md"

$ciPath = Join-Path $ProjectRoot ".github\copilot-instructions.md"
$ciContent = Get-Content -Path $ciPath -Raw -Encoding UTF8

$promptLibrary = @"

## ECC Prompt Library

Use these prompts in Copilot Chat for deeper workflows:

| Prompt | When to use | Purpose |
|--------|-------------|---------|
| ``/plan`` | Complex feature | Phased implementation plan |
| ``/tdd`` | New feature or bug fix | Test-driven development cycle |
| ``/code-review`` | After writing code | Quality and security review |
| ``/security-review`` | Before a release | Deep security analysis |
| ``/build-fix`` | Build/CI failure | Systematic error resolution |
| ``/refactor`` | Code maintenance | Dead code cleanup and simplification |

To use: open Copilot Chat, click Attach > Prompt, or type ``/`` and select.
"@

if ($ciContent -match "ECC Prompt Library") {
    Write-Skip "Prompt library reference already present"
}
else {
    if ($DryRun) {
        Write-Host "    WOULD APPEND: Prompt library table to copilot-instructions.md" -ForegroundColor DarkGray
    }
    else {
        $ciContent += $promptLibrary
        $ciContent | Set-Content -Path $ciPath -Encoding UTF8 -NoNewline
        Write-OK "Prompt library reference added"
    }
}

# --- Task 4: Ensure chat.promptFiles in settings.json --------------------
Write-Step "Task 4: Ensure chat.promptFiles=true in settings.json"

$settingsPath = Join-Path $ProjectRoot ".vscode\settings.json"
$settingsRaw = Get-Content -Path $settingsPath -Raw -Encoding UTF8

if ($settingsRaw -match "chat\.promptFiles") {
    Write-Skip "chat.promptFiles already set"
}
else {
    if ($DryRun) {
        Write-Host "    WOULD ADD: chat.promptFiles: true to settings.json" -ForegroundColor DarkGray
    }
    else {
        $jsonObj = $settingsRaw | ConvertFrom-Json
        $props = @{}
        # Preserve existing properties
        foreach ($prop in $jsonObj.PSObject.Properties) {
            $props[$prop.Name] = $prop.Value
        }
        $props["chat.promptFiles"] = $true
        $props | ConvertTo-Json -Depth 10 | Set-Content -Path $settingsPath -Encoding UTF8
        Write-OK "chat.promptFiles added"
    }
}

# --- Task 5: Clean .gitignore ---------------------------------------------
Write-Step "Task 5: Clean .gitignore"

$gitignorePath = Join-Path $ProjectRoot ".gitignore"
if (Test-Path $gitignorePath) {
    $lines = Get-Content -Path $gitignorePath -Encoding UTF8
    $originalCount = $lines.Count
    $cleaned = $lines | Where-Object {
        $_ -notmatch '\.github/instructions/' -and
        $_ -notmatch '\.github/ecc-skills/' -and
        $_ -notmatch '# ECC-generated instructions'
    }
    $removedCount = $originalCount - $cleaned.Count

    if ($removedCount -gt 0) {
        if ($DryRun) {
            Write-Host "    WOULD REMOVE: $removedCount lines from .gitignore" -ForegroundColor DarkGray
        }
        else {
            $cleaned | Set-Content -Path $gitignorePath -Encoding UTF8
            Write-OK "$removedCount old ECC entries removed from .gitignore"
        }
    }
    else {
        Write-Skip "No old ECC entries in .gitignore"
    }
}

# --- Summary --------------------------------------------------------------
Write-Host ""
Write-Host "========================================================" -ForegroundColor Green
Write-Host "  Done!" -ForegroundColor Green
Write-Host "========================================================" -ForegroundColor Green
Write-Host ""
Write-Host "Result:" -ForegroundColor White
Write-Host "  .github/copilot-instructions.md   -- Always active (project + ECC rules)" -ForegroundColor Gray
Write-Host "  .github/prompts/plan.prompt.md     -- /plan" -ForegroundColor Gray
Write-Host "  .github/prompts/tdd.prompt.md      -- /tdd" -ForegroundColor Gray
Write-Host "  .github/prompts/code-review.prompt.md      -- /code-review" -ForegroundColor Gray
Write-Host "  .github/prompts/security-review.prompt.md  -- /security-review" -ForegroundColor Gray
Write-Host "  .github/prompts/build-fix.prompt.md        -- /build-fix" -ForegroundColor Gray
Write-Host "  .github/prompts/refactor.prompt.md         -- /refactor" -ForegroundColor Gray
Write-Host "  .vscode/settings.json              -- Copilot overlays + chat.promptFiles" -ForegroundColor Gray
Write-Host ""
