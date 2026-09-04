<#
.SYNOPSIS
    One-shot local bring-up for the Cellphone Repair Shop stack:
    Laragon (MySQL) -> Laravel API (:8000) -> Next.js app (:3000).

.DESCRIPTION
    Idempotent. Safe to re-run. Detects what's already running and only does
    the missing work. Backend + frontend each get their own titled console
    window so you can watch or stop them independently; this script exits once
    everything reports healthy.

.PARAMETER Fresh
    Wipe and rebuild: `php artisan migrate:fresh --seed` on the backend and a
    clean `npm ci` + production build on the frontend. Use on first setup or
    after schema churn. WITHOUT this flag the DB is only migrated forward
    (no data loss) and node_modules is reused.

.PARAMETER Seed
    Force a re-seed after a normal (non-Fresh) migrate. Not usually needed:
    the script auto-seeds whenever it finds an empty database.

.PARAMETER Demo
    Seed the full demo dataset (customers, tickets across every status, 90
    days of sales, buy-backs, ...) instead of the clean baseline (staff
    accounts + product/service catalog only).

.PARAMETER Pull
    `git pull` the backend and frontend repos before building. Off by
    default so a run never changes your code unexpectedly. (The frontend is
    still auto-CLONED when it's missing entirely, regardless of this flag.)

.PARAMETER Dev
    Frontend runs `npm run dev` (hot reload) instead of build + `npm start`.

.PARAMETER SiteHost
    Friendly hostname the client types in the browser (default
    "cp-repair-mgnt-app"). The script adds a hosts-file entry and a Laragon
    Apache reverse-proxy vhost so http://<SiteHost>/ serves the Next.js app
    and http://<SiteHost>/api/... reaches the Laravel API on the same
    origin. Editing the hosts file needs admin once - the script will prompt
    for elevation.

.PARAMETER NoProxy
    Don't touch the hosts file / Apache. Frontend talks to the API at
    http://127.0.0.1:8000/api/v1 and you reach it at http://localhost:3000.

.PARAMETER NoFrontend
    Skip the Next.js app entirely (backend + DB only).

.PARAMETER NoQueue
    Don't spawn the `queue:work` window (queue connection is database-backed).

.PARAMETER NoLaragon
    Assume MySQL is already listening on its port; don't touch Laragon.

.PARAMETER Stop
    Kill the windows/processes a previous run of this script started, then exit.

.EXAMPLE
    .\scripts\dev-up.ps1 -Fresh
.EXAMPLE
    .\scripts\dev-up.ps1            # daily use: migrate forward, reuse builds
.EXAMPLE
    .\scripts\dev-up.ps1 -Dev -NoQueue
.EXAMPLE
    .\scripts\dev-up.ps1 -SiteHost cp-repair-mgnt-app
.EXAMPLE
    .\scripts\dev-up.ps1 -Stop
#>
[CmdletBinding()]
param(
    [switch]$Fresh,
    [switch]$Seed,
    [switch]$Demo,
    [switch]$Pull,
    [switch]$Dev,
    [string]$SiteHost = 'cp-repair-mgnt-app',
    [switch]$NoProxy,
    [switch]$NoFrontend,
    [switch]$NoQueue,
    [switch]$NoLaragon,
    [switch]$Stop
)

$ErrorActionPreference = 'Stop'
$script:Fail = $false

# ------------------------------------------------------------------ config ---
# Override any of these with an environment variable before running.
$Backend    = if ($env:CPR_BACKEND) { $env:CPR_BACKEND } else { Split-Path -Parent $PSScriptRoot }
$LaragonExe = if ($env:LARAGON_EXE) { $env:LARAGON_EXE } else { 'D:\laragon\laragon.exe' }

# Frontend location, in order of preference:
#   1. $env:CPR_FRONTEND
#   2. a sibling of the backend repo  (where bootstrap.ps1 puts it)
#   3. this dev machine's checkout under \FE\
#   4. fall back to the sibling path, so auto-clone lands it there
$feSibling = Join-Path (Split-Path $Backend -Parent) 'cellphone-repair-mgnt-app'
$feDevBox  = 'D:\xampp\apache\bin\FE\cellphone-repair-mgnt-app'
$Frontend =
    if     ($env:CPR_FRONTEND)                                  { $env:CPR_FRONTEND }
    elseif (Test-Path (Join-Path $feSibling 'package.json'))    { $feSibling }
    elseif (Test-Path (Join-Path $feDevBox  'package.json'))    { $feDevBox }
    else                                                        { $feSibling }

# Used only to auto-clone the frontend when it isn't on disk yet, and by
# -Pull. The backend repo is wherever this script already lives.
$FrontendRepo = if ($env:CPR_FRONTEND_REPO) { $env:CPR_FRONTEND_REPO } else { 'https://github.com/jehnsen/cellphone-repair-mgnt-app.git' }

$ApiHost = '127.0.0.1'
$ApiPort = 8000
$WebPort = 3000
$StateFile = Join-Path $env:TEMP 'cpr-dev-up.pids.json'

$LaragonRoot   = Split-Path $LaragonExe -Parent
$ApacheSitesDir = Join-Path $LaragonRoot 'etc\apache2\sites-enabled'
$VhostFile     = Join-Path $ApacheSitesDir 'cp-repair-mgnt-app.conf'
$HostsFile     = Join-Path $env:SystemRoot 'System32\drivers\etc\hosts'
$SiteUrl       = "http://$SiteHost"
$UseProxy      = -not $NoProxy

# --------------------------------------------------------------- utilities ---
function Say  ($m) { Write-Host "  $m" -ForegroundColor Gray }
function Step ($m) { Write-Host "`n==> $m" -ForegroundColor Cyan }
function Ok   ($m) { Write-Host "  [ok] $m" -ForegroundColor Green }
function Warn ($m) { Write-Host "  [!!] $m" -ForegroundColor Yellow }
function Die  ($m) { Write-Host "  [xx] $m" -ForegroundColor Red; exit 1 }

function Find-Tool {
    param([string]$Name, [string[]]$Fallbacks)
    $c = Get-Command $Name -ErrorAction SilentlyContinue
    if ($c) { return $c.Source }
    foreach ($f in $Fallbacks) { if ($f -and (Test-Path $f)) { return (Resolve-Path $f).Path } }
    return $null
}

function Test-Port {
    param([int]$Port)
    return [bool](Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue)
}

function Wait-Port {
    param([int]$Port, [int]$TimeoutSec = 60)
    $sw = [Diagnostics.Stopwatch]::StartNew()
    while ($sw.Elapsed.TotalSeconds -lt $TimeoutSec) {
        if (Test-Port $Port) { return $true }
        Start-Sleep -Milliseconds 800
    }
    return $false
}

function Wait-Url {
    param([string]$Url, [int]$TimeoutSec = 90)
    $sw = [Diagnostics.Stopwatch]::StartNew()
    while ($sw.Elapsed.TotalSeconds -lt $TimeoutSec) {
        try {
            $r = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 5
            if ($r.StatusCode -ge 200 -and $r.StatusCode -lt 500) { return $true }
        } catch {
            # 503 (Redis down) still means the app is up and answering
            if ($_.Exception.Response -and [int]$_.Exception.Response.StatusCode -eq 503) { return $true }
        }
        Start-Sleep -Seconds 2
    }
    return $false
}

function Invoke-Step {
    param([string]$Exe, [string[]]$Args, [string]$WorkDir = $Backend, [string]$Label)
    if ($Label) { Say $Label } else { Say "$Exe $($Args -join ' ')" }
    Push-Location $WorkDir
    # git / composer / npm write progress to stderr; don't let that trip the
    # Stop preference - the exit code is the real pass/fail signal.
    $prev = $ErrorActionPreference; $ErrorActionPreference = 'Continue'
    try {
        & $Exe @Args
        $code = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $prev
        Pop-Location
    }
    if ($code -ne 0) { throw "$(Split-Path $Exe -Leaf) exited $code" }
}

function Start-DevWindow {
    param([string]$Title, [string]$WorkDir, [string]$CommandLine)
    $inner = @"
`$Host.UI.RawUI.WindowTitle = '$Title'
Set-Location -LiteralPath '$WorkDir'
Write-Host '>>> $CommandLine' -ForegroundColor Cyan
$CommandLine
"@
    $p = Start-Process -FilePath 'powershell.exe' `
        -ArgumentList @('-NoExit', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-Command', $inner) `
        -PassThru
    return $p.Id
}

function Get-EnvValue {
    param([string]$Path, [string]$Key)
    if (-not (Test-Path $Path)) { return $null }
    $m = Select-String -Path $Path -Pattern ("^\s*" + [regex]::Escape($Key) + "\s*=") | Select-Object -First 1
    if (-not $m) { return $null }
    $v = ($m.Line -replace ("^\s*" + [regex]::Escape($Key) + "\s*=\s*"), '').Trim()
    return $v.Trim('"').Trim("'")
}

function Set-EnvValue {
    # Idempotent upsert of KEY=value in a dotenv-style file. Returns $true if
    # the file changed.
    param([string]$Path, [string]$Key, [string]$Value)
    if (-not (Test-Path $Path)) { return $false }
    $lines = Get-Content -LiteralPath $Path
    $pat = '^\s*' + [regex]::Escape($Key) + '\s*='
    $new = "$Key=$Value"
    $found = $false
    $out = foreach ($l in $lines) {
        if ($l -match $pat) { $found = $true; if ($l.Trim() -ne $new) { $new } else { $l } }
        else { $l }
    }
    $out = @($out | Where-Object { $null -ne $_ })
    if (-not $found) { $out += $new }
    $before = (@($lines) -join "`n")
    $after = ($out -join "`n")
    if ($before -ne $after) {
        Set-Content -LiteralPath $Path -Value $out -Encoding UTF8
        return $true
    }
    return $false
}

function Test-Admin {
    $id = [Security.Principal.WindowsIdentity]::GetCurrent()
    return ([Security.Principal.WindowsPrincipal]$id).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Get-RowCount {
    # Row count of <db>.<Table> via the mysql client, or $null if it can't tell.
    param([string]$Table)
    if (-not $Mysql -or -not $dbName) { return $null }
    $r = & $Mysql @mysqlArgs -N -B -e "SELECT COUNT(*) FROM $dbName.$Table" 2>$null
    if ($LASTEXITCODE -ne 0) { return $null }
    $n = ($r | Select-Object -First 1)
    if ($n -match '^\d+$') { return [int]$n }
    return $null
}

function Save-State  ($h) { $h | ConvertTo-Json | Set-Content -Path $StateFile -Encoding UTF8 }
function Load-State       { if (Test-Path $StateFile) { Get-Content $StateFile -Raw | ConvertFrom-Json } }

# ------------------------------------------------------------------- -Stop ---
if ($Stop) {
    Step 'Stopping servers started by dev-up'
    $s = Load-State
    if (-not $s) { Warn "no state file at $StateFile - nothing recorded"; exit 0 }
    foreach ($name in 'backend', 'queue', 'frontend') {
        $procId = $s.$name
        if ($procId) {
            & taskkill /PID $procId /T /F 2>$null | Out-Null
            if ($LASTEXITCODE -eq 0) { Ok "$name (pid $procId) killed" } else { Warn "$name (pid $procId) already gone" }
        }
    }
    Remove-Item $StateFile -ErrorAction SilentlyContinue
    exit 0
}

Write-Host ""
Write-Host "  Cellphone Repair Shop - local bring-up" -ForegroundColor White
Write-Host "  backend : $Backend"
Write-Host "  frontend: $Frontend"
Write-Host "  url     : $(if ($UseProxy) { "$SiteUrl/  (Apache proxy -> :$WebPort / :$ApiPort)" } else { "http://localhost:$WebPort  (-NoProxy)" })"
Write-Host "  mode    : $(if ($Fresh) {'FRESH (wipe + reseed + clean build)'} else {'incremental'})"

# -------------------------------------------------------------- toolchain ---
Step 'Resolving toolchain'

$Php = Find-Tool 'php' @(
    (Get-ChildItem "$LaragonRoot\bin\php\*\php.exe" -ErrorAction SilentlyContinue | Select-Object -First 1 -Expand FullName)
)
$Composer = Find-Tool 'composer' @(
    "$LaragonRoot\bin\composer\composer.bat",
    "$env:USERPROFILE\.config\herd-lite\bin\composer.bat"
)
$Npm = Find-Tool 'npm' @(
    (Get-ChildItem "$LaragonRoot\bin\nodejs\*\npm.cmd" -ErrorAction SilentlyContinue | Select-Object -First 1 -Expand FullName)
)
$Mysql = Find-Tool 'mysql' @(
    (Get-ChildItem "$LaragonRoot\bin\mysql\*\bin\mysql.exe" -ErrorAction SilentlyContinue | Select-Object -First 1 -Expand FullName)
)
$MysqlAdmin = Find-Tool 'mysqladmin' @(
    (Get-ChildItem "$LaragonRoot\bin\mysql\*\bin\mysqladmin.exe" -ErrorAction SilentlyContinue | Select-Object -First 1 -Expand FullName)
)
$Httpd = Get-ChildItem "$LaragonRoot\bin\apache\*\bin\httpd.exe" -ErrorAction SilentlyContinue | Select-Object -First 1 -Expand FullName
$Git = Find-Tool 'git' @(
    (Get-ChildItem "$LaragonRoot\bin\git\*\bin\git.exe" -ErrorAction SilentlyContinue | Select-Object -First 1 -Expand FullName),
    'C:\Program Files\Git\cmd\git.exe'
)

if (-not $Php)      { Die 'php not found (PATH or Laragon bin)' }
if (-not $Composer) { Die 'composer not found (PATH, Laragon bin, or herd-lite)' }
Ok "php      $Php"
Ok "composer $Composer"
if (-not $NoFrontend) {
    if (-not $Npm) { Die 'npm not found - install Node or pass -NoFrontend' }
    Ok "npm      $Npm"
}
if ($Mysql) { Ok "mysql    $Mysql" } else { Warn 'mysql client not found - DB auto-create will be skipped' }
if ($Git)   { Ok "git      $Git" } else { Warn 'git not found - cannot auto-clone/pull the frontend' }

if (-not (Test-Path (Join-Path $Backend 'artisan'))) { Die "no artisan in $Backend - wrong CPR_BACKEND?" }
$envPath = Join-Path $Backend '.env'

if ($Pull -and $Git -and (Test-Path (Join-Path $Backend '.git'))) {
    Step 'Updating source (git pull)'
    Invoke-Step $Git @('-C', $Backend, 'pull', '--ff-only') $Backend 'git pull (backend)'
}

# ------------------------------------------------------------------ MySQL ---
Step 'Database engine (Laragon / MySQL)'
$dbPort = Get-EnvValue $envPath 'DB_PORT'; if (-not $dbPort) { $dbPort = '3306' }
$dbPort = [int]$dbPort

if (Test-Port $dbPort) {
    Ok "MySQL already listening on :$dbPort"
} elseif ($NoLaragon) {
    Die "-NoLaragon set but nothing is listening on :$dbPort"
} elseif (Test-Path $LaragonExe) {
    Say "starting Laragon: $LaragonExe start"
    Start-Process -FilePath $LaragonExe -ArgumentList 'start' | Out-Null
    if (Wait-Port $dbPort 60) { Ok "MySQL up on :$dbPort" }
    else { Die "MySQL did not come up on :$dbPort within 60s - start Laragon by hand and re-run" }
} else {
    Die "Laragon not found at $LaragonExe (set LARAGON_EXE) and :$dbPort is closed"
}

# extra settle time for a cold mysqld
if ($MysqlAdmin) {
    $dbUser = Get-EnvValue $envPath 'DB_USERNAME'; if (-not $dbUser) { $dbUser = 'root' }
    $dbPass = Get-EnvValue $envPath 'DB_PASSWORD'
    $adminArgs = @("--user=$dbUser", '--host=127.0.0.1', "--port=$dbPort", '--protocol=tcp', '--connect-timeout=3')
    if ($dbPass -and $dbPass -ne 'null') { $adminArgs += "--password=$dbPass" }
    for ($i = 0; $i -lt 20; $i++) {
        & $MysqlAdmin @adminArgs ping 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { break }
        Start-Sleep -Milliseconds 750
    }
}

# --------------------------------------------------------------- create DBs ---
if ($Mysql) {
    Step 'Ensuring databases exist'
    $dbUser = Get-EnvValue $envPath 'DB_USERNAME'; if (-not $dbUser) { $dbUser = 'root' }
    $dbPass = Get-EnvValue $envPath 'DB_PASSWORD'
    $dbName = Get-EnvValue $envPath 'DB_DATABASE'; if (-not $dbName) { $dbName = 'cp_repair_db' }
    $mysqlArgs = @("--user=$dbUser", '--host=127.0.0.1', "--port=$dbPort", '--protocol=tcp')
    if ($dbPass -and $dbPass -ne 'null') { $mysqlArgs += "--password=$dbPass" }
    # names are plain identifiers (letters/underscore) so no backtick quoting needed
    $sql = "CREATE DATABASE IF NOT EXISTS $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; " +
           "CREATE DATABASE IF NOT EXISTS ${dbName}_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    & $Mysql @mysqlArgs -e $sql
    if ($LASTEXITCODE -eq 0) { Ok "$dbName + ${dbName}_testing ready" }
    else { Warn "could not create databases (check DB_USERNAME/DB_PASSWORD in .env) - continuing" }
}

# ---------------------------------------------------------------- backend ---
Step 'Backend: dependencies & config'
if ($Fresh -or -not (Test-Path (Join-Path $Backend 'vendor\autoload.php'))) {
    Invoke-Step $Composer @('install', '--no-interaction', '--prefer-dist') $Backend 'composer install'
} else {
    Say 'vendor/ present - skipping composer install (use -Fresh to force)'
}

if (-not (Test-Path $envPath)) {
    $example = Join-Path $Backend '.env.example'
    if (Test-Path $example) { Copy-Item $example $envPath; Ok '.env created from .env.example' }
    else { Die 'no .env and no .env.example - cannot continue' }
}
if (-not (Get-EnvValue $envPath 'APP_KEY')) {
    Invoke-Step $Php @('artisan', 'key:generate', '--force') $Backend 'php artisan key:generate'
}

Invoke-Step $Php @('artisan', 'config:clear') $Backend 'php artisan config:clear'
try { Invoke-Step $Php @('artisan', 'storage:link') $Backend 'php artisan storage:link' } catch { Say 'storage:link already exists' }

# ------------------------------------------------------ friendly hostname ---
# The URL the frontend build should call. Same-origin through the Apache
# proxy by default; raw loopback when -NoProxy.
if ($UseProxy) { $FrontApiUrl = "$SiteUrl/api/v1" } else { $FrontApiUrl = "http://127.0.0.1:$ApiPort/api/v1" }

if ($UseProxy) {
    Step "Friendly hostname ($SiteUrl)"

    # 1. hosts file: 127.0.0.1  <SiteHost>
    $hostsHasEntry = $false
    if (Test-Path $HostsFile) {
        $hostsHasEntry = Select-String -Path $HostsFile -Quiet `
            -Pattern ('^\s*[0-9.]+\s+' + [regex]::Escape($SiteHost) + '(\s|#|$)')
    }
    if ($hostsHasEntry) {
        Ok 'hosts entry present'
    } else {
        $entry = "127.0.0.1 $SiteHost # cellphone-repair-mgnt-app (dev-up.ps1)"
        try {
            Add-Content -LiteralPath $HostsFile -Value $entry -Encoding ASCII -ErrorAction Stop
            Ok 'hosts entry added'
        } catch {
            Say 'hosts file needs admin - requesting elevation (accept the UAC prompt)...'
            $elev = "Add-Content -LiteralPath '$HostsFile' -Value '$entry' -Encoding ASCII"
            $enc = [Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($elev))
            try {
                Start-Process -FilePath 'powershell.exe' -Verb RunAs -Wait -ErrorAction Stop `
                    -ArgumentList @('-NoProfile', '-EncodedCommand', $enc)
            } catch {
                Warn "elevation declined - add this line to $HostsFile by hand:"
                Warn "    $entry"
            }
            if ((Test-Path $HostsFile) -and (Select-String -Path $HostsFile -Quiet -Pattern ([regex]::Escape($SiteHost)))) {
                Ok 'hosts entry added (elevated)'
            }
        }
    }

    # 2. Apache reverse-proxy vhost
    if (-not (Test-Path $ApacheSitesDir)) {
        Warn "Laragon Apache config dir not found - skipping proxy vhost ($ApacheSitesDir)"
    } else {
        $vhost = @"
# Generated by scripts/dev-up.ps1 - reverse proxy for the Cellphone Repair app.
#   http://$SiteHost/        -> Next.js  (127.0.0.1:$WebPort)
#   http://$SiteHost/api/... -> Laravel  (127.0.0.1:$ApiPort)
<IfModule !proxy_module>
    LoadModule proxy_module modules/mod_proxy.so
</IfModule>
<IfModule !proxy_http_module>
    LoadModule proxy_http_module modules/mod_proxy_http.so
</IfModule>
<IfModule !proxy_wstunnel_module>
    LoadModule proxy_wstunnel_module modules/mod_proxy_wstunnel.so
</IfModule>

<VirtualHost *:80>
    ServerName $SiteHost
    ProxyPreserveHost On
    ProxyRequests Off

    # Laravel API - must precede the catch-all below
    ProxyPass         /api  http://127.0.0.1:$ApiPort/api  timeout=120
    ProxyPassReverse  /api  http://127.0.0.1:$ApiPort/api

    # Next.js dev hot-reload websocket (inert for a production build)
    ProxyPass         /_next/webpack-hmr  ws://127.0.0.1:$WebPort/_next/webpack-hmr

    # Next.js app - everything else
    ProxyPass         /  http://127.0.0.1:$WebPort/  timeout=120
    ProxyPassReverse  /  http://127.0.0.1:$WebPort/
</VirtualHost>
"@
        $current = ''
        if (Test-Path $VhostFile) { $c = Get-Content -LiteralPath $VhostFile -Raw; if ($c) { $current = $c } }
        $vhostChanged = ($current.TrimEnd() -ne $vhost.TrimEnd())
        if ($vhostChanged) {
            Set-Content -LiteralPath $VhostFile -Value $vhost -Encoding ASCII
            Ok "vhost written: $VhostFile"
        } else {
            Ok 'vhost already current'
        }

        # config sanity check (advisory only - Laragon does its own on start)
        $cfgOk = $true
        if ($Httpd) {
            $prevEAP = $ErrorActionPreference; $ErrorActionPreference = 'Continue'
            & $Httpd -t *> $null
            if ($LASTEXITCODE -ne 0) { $cfgOk = $false }
            $ErrorActionPreference = $prevEAP
        }

        if (Test-Port 80) {
            if ($vhostChanged) {
                if ($cfgOk) {
                    & $LaragonExe reload *> $null
                    Say 'Laragon reload (Apache picks up the vhost)'
                } else {
                    Warn 'httpd -t reported an issue - skipping reload of the running Apache; check the vhost by hand'
                }
            }
        } else {
            Say 'Apache not running - asking Laragon to start it'
            Start-Process -FilePath $LaragonExe -ArgumentList 'start' | Out-Null
        }
        if (Wait-Port 80 40) { Ok 'Apache proxy listening on :80' }
        else { Warn 'Apache not on :80 - open the Laragon window and switch Apache on, then re-run' }
    }

    # 3. same-origin => no CORS needed, but keep APP_URL / CORS consistent
    if (Set-EnvValue $envPath 'APP_URL' $SiteUrl) { Say "APP_URL -> $SiteUrl" }
    $cors = Get-EnvValue $envPath 'CORS_ALLOWED_ORIGINS'
    if (($cors -split ',') -notcontains $SiteUrl) {
        $merged = if ($cors) { "$cors,$SiteUrl" } else { $SiteUrl }
        if (Set-EnvValue $envPath 'CORS_ALLOWED_ORIGINS' $merged) { Say "CORS_ALLOWED_ORIGINS += $SiteUrl" }
    }
    Invoke-Step $Php @('artisan', 'config:clear') $Backend 'php artisan config:clear (.env changed)'
}

Step 'Backend: migrations'
# BaseInstallSeeder = staff logins + product/service catalog only (what a
# real shop starts with). DatabaseSeeder = that plus a demo dataset.
$seedClass = if ($Demo) { 'Database\Seeders\DatabaseSeeder' } else { 'Database\Seeders\BaseInstallSeeder' }
$seedLabel = if ($Demo) { 'demo dataset' } else { 'baseline (staff + catalog)' }

if ($Fresh) {
    Invoke-Step $Php @('artisan', 'migrate:fresh', '--force') $Backend 'php artisan migrate:fresh'
    Invoke-Step $Php @('artisan', 'db:seed', "--class=$seedClass", '--force') $Backend "php artisan db:seed - $seedLabel"
} else {
    Invoke-Step $Php @('artisan', 'migrate', '--force') $Backend 'php artisan migrate'
    $userRows = Get-RowCount 'users'
    if ($Seed -or ($null -ne $userRows -and $userRows -eq 0)) {
        if ($null -ne $userRows -and $userRows -eq 0) { Say 'database has no users yet - seeding' }
        Invoke-Step $Php @('artisan', 'db:seed', "--class=$seedClass", '--force') $Backend "php artisan db:seed - $seedLabel"
    } elseif ($null -eq $userRows) {
        Say 'could not verify the DB is seeded (no mysql client) - re-run with -Seed if login fails'
    } else {
        Say "database already seeded ($userRows users) - skipping (use -Seed to force)"
    }
}
Ok 'schema up to date'

# ----------------------------------------------------------- backend serve ---
Step 'Backend: serving'
$state = @{ backend = $null; queue = $null; frontend = $null; started = (Get-Date).ToString('s') }

if (Test-Port $ApiPort) {
    Warn "something already listening on :$ApiPort - not starting a second api server"
} else {
    $state.backend = Start-DevWindow 'CPR API :8000' $Backend "& '$Php' artisan serve --host=$ApiHost --port=$ApiPort"
    Ok "api server window started (pid $($state.backend))"
}

if (-not $NoQueue) {
    $state.queue = Start-DevWindow 'CPR queue:work' $Backend "& '$Php' artisan queue:work --tries=1 --timeout=0"
    Ok "queue worker window started (pid $($state.queue))"
}

# --------------------------------------------------------------- frontend ---
if (-not $NoFrontend) {
    if (-not (Test-Path (Join-Path $Frontend 'package.json'))) {
        Step 'Frontend: cloning'
        if (-not $Git) { Die "frontend not at $Frontend and git is unavailable - clone $FrontendRepo there by hand" }
        $parent = Split-Path $Frontend -Parent
        if (-not (Test-Path $parent)) { New-Item -ItemType Directory -Path $parent -Force | Out-Null }
        Invoke-Step $Git @('clone', $FrontendRepo, $Frontend) $parent "git clone $FrontendRepo"
        Ok "cloned to $Frontend"
    } elseif ($Pull -and $Git -and (Test-Path (Join-Path $Frontend '.git'))) {
        Step 'Frontend: git pull'
        Invoke-Step $Git @('-C', $Frontend, 'pull', '--ff-only') $Frontend 'git pull (frontend)'
    }

    Step 'Frontend: dependencies'
    $feEnv = Join-Path $Frontend '.env.local'
    $feEnvExample = Join-Path $Frontend '.env.example'
    if (-not (Test-Path $feEnv)) {
        if (Test-Path $feEnvExample) { Copy-Item $feEnvExample $feEnv } else { New-Item -ItemType File -Path $feEnv | Out-Null }
        Ok '.env.local created'
    }
    if (Set-EnvValue $feEnv 'NEXT_PUBLIC_API_URL' $FrontApiUrl) {
        Ok "NEXT_PUBLIC_API_URL -> $FrontApiUrl (frontend will be rebuilt)"
    } else {
        Say "NEXT_PUBLIC_API_URL already $FrontApiUrl"
    }

    $needInstall = $Fresh -or -not (Test-Path (Join-Path $Frontend 'node_modules'))
    if ($needInstall) {
        if (Test-Path (Join-Path $Frontend 'package-lock.json')) {
            try { Invoke-Step $Npm @('ci') $Frontend 'npm ci' }
            catch { Invoke-Step $Npm @('install') $Frontend 'npm install (ci failed, fell back)' }
        } else {
            Invoke-Step $Npm @('install') $Frontend 'npm install'
        }
    } else {
        Say 'node_modules present - skipping install (use -Fresh to force)'
    }

    if (Test-Port $WebPort) {
        Warn "something already listening on :$WebPort - not starting the frontend"
    } elseif ($Dev) {
        Step 'Frontend: dev server'
        $state.frontend = Start-DevWindow 'CPR web :3000 (dev)' $Frontend "& '$Npm' run dev"
        Ok "next dev window started (pid $($state.frontend))"
    } else {
        Step 'Frontend: production build'
        Invoke-Step $Npm @('run', 'build') $Frontend 'npm run build'
        Ok 'build complete'
        $state.frontend = Start-DevWindow 'CPR web :3000' $Frontend "& '$Npm' run start -- --port $WebPort"
        Ok "next start window started (pid $($state.frontend))"
    }
}

Save-State $state

# ----------------------------------------------------------------- health ---
Step 'Waiting for services to answer'
$apiHealth = "http://${ApiHost}:${ApiPort}/api/v1/health"
if ($state.backend -or (Test-Port $ApiPort)) {
    if (Wait-Url $apiHealth 90) { Ok "API   $apiHealth" } else { Warn "API not answering yet - check the 'CPR API :8000' window" }
}
if (-not $NoFrontend -and ($state.frontend -or (Test-Port $WebPort))) {
    if (Wait-Url "http://localhost:$WebPort" 120) { Ok "Web   http://localhost:$WebPort" } else { Warn "Web not answering yet - check the 'CPR web :3000' window" }
}
if ($UseProxy -and (Test-Port 80)) {
    if (Wait-Url "$SiteUrl/api/v1/health" 30) { Ok "Proxy $SiteUrl/api/v1  (Laravel)" } else { Warn "$SiteUrl/api not routing - is Apache up? (Laragon > Apache)" }
    if (-not $NoFrontend) {
        if (Wait-Url "$SiteUrl/" 30) { Ok "Proxy $SiteUrl/  (Next.js)" } else { Warn "$SiteUrl/ not routing yet" }
    }
}

Write-Host ""
Write-Host "  ---------------------------------------------------------------" -ForegroundColor DarkGray
if ($UseProxy -and -not $NoFrontend) {
    Write-Host "   OPEN    : $SiteUrl/" -ForegroundColor Green
    Write-Host "   (give the client that one URL - the API rides on $SiteUrl/api)" -ForegroundColor Gray
} elseif (-not $NoFrontend) {
    Write-Host "   OPEN    : http://localhost:$WebPort" -ForegroundColor Green
}
Write-Host "   API     : http://${ApiHost}:${ApiPort}/api/v1   (direct)" -ForegroundColor White
Write-Host "   Login   : nelson.bonalos@gmail.com  / password   (owner)" -ForegroundColor White
Write-Host "             amylou.bonalos@gmail.com  / password   (manager)" -ForegroundColor Gray
Write-Host "             jomar.cruz@gmail.com      / password   (cashier)" -ForegroundColor Gray
Write-Host "   Stop    : .\scripts\dev-up.ps1 -Stop   (or just close the windows)" -ForegroundColor Gray
Write-Host "  ---------------------------------------------------------------" -ForegroundColor DarkGray
Write-Host ""
