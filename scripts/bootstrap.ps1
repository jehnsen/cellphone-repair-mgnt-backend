<#
.SYNOPSIS
    First-time setup on a machine that has NOTHING of this project yet.
    Clones the backend + frontend from GitHub, then hands off to dev-up.ps1.

.DESCRIPTION
    This is the only file the client needs to start with. Put it (and
    bootstrap.bat) anywhere - Desktop, Downloads, wherever - and run it.

    It expects these already installed (install once, any order):
      * Git            https://git-scm.com/download/win
      * PHP 8.3+ & Composer   (Laragon bundles both, or Laravel Herd)
      * Node.js 20+ (npm)
      * Laragon        https://laragon.org   (Apache + MySQL)

    Everything after that - databases, migrations, seed data, the
    cp-repair-mgnt-app hostname, the Apache proxy, both dev servers - is
    done by dev-up.ps1, which this script calls at the end.

.PARAMETER Root
    Folder to clone the two repos into (side by side). Default: %USERPROFILE%\apps

.PARAMETER BackendRepo
.PARAMETER FrontendRepo
    Override the GitHub URLs (e.g. an SSH remote).

.PARAMETER DevUpArgs
    Anything after the named args is forwarded to dev-up.ps1
    (e.g. -Demo, -Dev, -NoProxy). On the very first clone -Fresh is added
    automatically.

.EXAMPLE
    .\bootstrap.ps1
.EXAMPLE
    .\bootstrap.ps1 -Root D:\projects -Demo
#>
[CmdletBinding()]
param(
    [string]$Root = (Join-Path $env:USERPROFILE 'apps'),
    [string]$BackendRepo  = 'https://github.com/jehnsen/cellphone-repair-mgnt-backend.git',
    [string]$FrontendRepo = 'https://github.com/jehnsen/cellphone-repair-mgnt-app.git',
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$DevUpArgs
)

$ErrorActionPreference = 'Stop'
function Say  ($m) { Write-Host "  $m" -ForegroundColor Gray }
function Step ($m) { Write-Host "`n==> $m" -ForegroundColor Cyan }
function Ok   ($m) { Write-Host "  [ok] $m" -ForegroundColor Green }
function Die  ($m) { Write-Host "  [xx] $m" -ForegroundColor Red; exit 1 }

Write-Host ""
Write-Host "  Cellphone Repair Shop - first-time bootstrap" -ForegroundColor White
Write-Host "  clone root : $Root"

# --- git -------------------------------------------------------------------
$git = (Get-Command git -ErrorAction SilentlyContinue).Source
if (-not $git) {
    foreach ($p in 'C:\Program Files\Git\cmd\git.exe', 'D:\laragon\bin\git\bin\git.exe') {
        if (Test-Path $p) { $git = $p; break }
    }
}
if (-not $git) { Die 'git not found - install Git for Windows (https://git-scm.com/download/win) and re-run' }
Ok "git $git"

# --- clone / update ------------------------------------------------------
if (-not (Test-Path $Root)) { New-Item -ItemType Directory -Path $Root -Force | Out-Null }

function Get-Repo {
    param([string]$Url, [string]$Dir)
    $prev = $ErrorActionPreference; $ErrorActionPreference = 'Continue'
    try {
        if (Test-Path (Join-Path $Dir '.git')) {
            Say "updating $(Split-Path $Dir -Leaf) (git pull)"
            & $git -C $Dir pull --ff-only
            if ($LASTEXITCODE -ne 0) { Die "git pull failed in $Dir" }
            return $false
        }
        Say "cloning $Url"
        Say '(if the repo is private, a GitHub sign-in window will pop - log in once)'
        & $git clone $Url $Dir
        if ($LASTEXITCODE -ne 0) { Die "git clone failed: $Url" }
        return $true
    } finally { $ErrorActionPreference = $prev }
}

$backendDir  = Join-Path $Root 'cellphone-repair-mgnt-backend'
$frontendDir = Join-Path $Root 'cellphone-repair-mgnt-app'

Step 'Backend repo'
$freshClone = Get-Repo $BackendRepo $backendDir
Step 'Frontend repo'
Get-Repo $FrontendRepo $frontendDir | Out-Null

# --- hand off to dev-up -------------------------------------------------
$devUp = Join-Path $backendDir 'scripts\dev-up.ps1'
if (-not (Test-Path $devUp)) { Die "dev-up.ps1 not found at $devUp - is the backend repo up to date?" }

$env:CPR_FRONTEND = $frontendDir            # dev-up.ps1 reads this

$fwd = @()
if ($DevUpArgs) { $fwd += $DevUpArgs }
if ($freshClone -and ($fwd -notcontains '-Fresh')) { $fwd += '-Fresh' }   # first ever run: build the DB + seed

Step "Running dev-up.ps1 $($fwd -join ' ')"
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File $devUp @fwd
exit $LASTEXITCODE
