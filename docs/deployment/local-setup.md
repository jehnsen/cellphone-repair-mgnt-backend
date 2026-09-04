# Local setup & installation (Windows)

How to get the Cellphone Repair Shop running on a Windows machine using the
scripts in [`/scripts`](../../scripts). When you're done you open
**`http://cp-repair-mgnt-app/`** in a browser and log in.

- **[`scripts/bootstrap.ps1`](../../scripts/bootstrap.ps1)** / `bootstrap.bat` —
  first time on a machine that has *nothing*. Clones both repos from GitHub,
  then runs `dev-up`.
- **[`scripts/dev-up.ps1`](../../scripts/dev-up.ps1)** / `dev-up.bat` —
  everything else: databases, migrations, seed data, the friendly hostname,
  the Apache reverse proxy, and the two dev servers. Idempotent — safe to
  re-run any time.

There are two projects:

| Repo | GitHub | Default location after bootstrap |
|---|---|---|
| Backend (Laravel API) | `jehnsen/cellphone-repair-mgnt-backend` | `%USERPROFILE%\apps\cellphone-repair-mgnt-backend` |
| Frontend (Next.js app) | `jehnsen/cellphone-repair-mgnt-app` | `%USERPROFILE%\apps\cellphone-repair-mgnt-app` |

---

## 1. Prerequisites (install once)

Install these first, in any order. The scripts check for them and stop with
a clear message if one is missing — they do **not** install them for you.

| Tool | Get it | Verify (in a new terminal) |
|---|---|---|
| **Git for Windows** | <https://git-scm.com/download/win> | `git --version` |
| **Laragon** (Apache + MySQL) | <https://laragon.org> — the "Full" edition | Laragon window opens; `D:\laragon\laragon.exe` exists |
| **PHP 8.3+ and Composer** | Laragon bundles both, **or** Laravel Herd (<https://herd.laravel.com>) | `php -v` shows 8.3+, `composer -V` works |
| **Node.js 20+ (npm)** | <https://nodejs.org> (LTS) | `node -v`, `npm -v` |

Notes:

- If Laragon is **not** installed at `D:\laragon`, set an environment
  variable `LARAGON_EXE` to the full path of `laragon.exe`, or edit the
  config block near the top of `dev-up.ps1`.
- The backend repo is currently **private** — the first `git clone` opens a
  GitHub sign-in window (Git Credential Manager). Log in once; it's cached
  after that.
- No Redis needed. Cache and queue run on the database.

---

## 2. First-time setup (fresh machine)

You only need the two `bootstrap` files — get them from the project owner
(email / USB / shared drive), or, once you have Git, from the repo at
`scripts/bootstrap.ps1` and `scripts/bootstrap.bat`.

1. Put `bootstrap.bat` and `bootstrap.ps1` in the same folder anywhere
   (Desktop, Downloads — doesn't matter).
2. **Double-click `bootstrap.bat`.**
3. When the **GitHub sign-in window** appears, log in (first clone only).
4. When the **User Account Control (UAC)** prompt appears, click **Yes** —
   this is the one step that needs admin, to add `cp-repair-mgnt-app` to the
   Windows hosts file.
5. Wait. First run takes a few minutes (`composer install`, `npm ci`,
   `next build`).
6. When it finishes it prints the URL and the logins, and three console
   windows stay open (**CPR API :8000**, **CPR queue:work**, **CPR web :3000**).
7. Open **`http://cp-repair-mgnt-app/`** and log in.

What bootstrap did: created `%USERPROFILE%\apps\`, cloned both repos side by
side, then ran `dev-up.ps1 -Fresh` (which builds the database and seeds it).

**Options** (pass through to `dev-up`):

```bat
bootstrap.bat -Root D:\projects      REM clone somewhere other than %USERPROFILE%\apps
bootstrap.bat -Demo                   REM also load demo data (customers, tickets, 90 days of sales)
```

---

## 3. Everyday use (repo already cloned)

From then on you run **`dev-up`** directly — you don't need bootstrap again.

- **Double-click** `…\cellphone-repair-mgnt-backend\scripts\dev-up.bat`, **or**
- In PowerShell:
  ```powershell
  cd $env:USERPROFILE\apps\cellphone-repair-mgnt-backend
  .\scripts\dev-up.ps1
  ```

A plain run (no flags) starts Laragon/MySQL, applies any new migrations
(no data loss), rebuilds the frontend, and opens the three server windows.
It skips work that's already done (`composer install`, `npm ci`, seeding),
so re-runs take ~15–30 seconds.

To **update to the latest code** first:

```powershell
.\scripts\dev-up.ps1 -Pull        # git pull both repos, then bring everything up
```

To **stop** the servers: close the three windows, or:

```powershell
.\scripts\dev-up.ps1 -Stop
```

Nothing auto-starts after a Windows reboot — run `dev-up` again (it's fast).

---

## 4. What you get when it's running

| URL | What |
|---|---|
| **`http://cp-repair-mgnt-app/`** | The app. Give the client this one URL. |
| `http://cp-repair-mgnt-app/api/v1` | The API, same origin (used by the app). |
| `http://127.0.0.1:8000/api/v1` | The API, direct (bypasses the proxy). |
| `http://127.0.0.1:8000/api/v1/health` | Liveness check. |
| `http://127.0.0.1:8000/api/v1/ready` | Readiness. Returns **503** with `"redis": false` — that's expected here. |
| `http://localhost:3000` | The frontend, direct. |

**Seeded logins** (password is `password` for all):

| Email | Role |
|---|---|
| `nelson.bonalos@gmail.com` | owner (sees both branches) |
| `amylou.bonalos@gmail.com` | manager |
| `jomar.cruz@gmail.com` | cashier |

A plain first run seeds the **baseline** only — staff accounts plus the
product/service catalog. Use `-Demo` for a database full of sample
customers, job orders, and sales to click around.

---

## 5. Flag reference — `dev-up.ps1`

| Flag | Effect |
|---|---|
| *(none)* | Migrate forward, auto-seed if the DB is empty, rebuild frontend, start everything. |
| `-Fresh` | **Wipe the database** (`migrate:fresh`), reseed, `npm ci`, clean production build. First-time / after schema changes. |
| `-Demo` | Seed the full demo dataset instead of the clean baseline. |
| `-Seed` | Force a reseed on a non-`-Fresh` run (e.g. if login fails). |
| `-Pull` | `git pull --ff-only` both repos before building. |
| `-Dev` | Frontend runs `npm run dev` (hot reload) instead of build + start. |
| `-NoProxy` | Skip the hosts file / Apache entirely. Use `http://localhost:3000`; the app calls the API at `http://127.0.0.1:8000/api/v1`. |
| `-SiteHost <name>` | Use a different friendly hostname (default `cp-repair-mgnt-app`). |
| `-NoQueue` | Don't open the `queue:work` window. |
| `-NoFrontend` | Backend + database only. |
| `-NoLaragon` | Assume MySQL is already up; don't call Laragon. |
| `-Stop` | Kill the windows a previous run started, then exit. |

**Environment overrides** (set before running):

| Variable | Purpose |
|---|---|
| `CPR_BACKEND` | Backend repo path (default: the folder containing `scripts/`). |
| `CPR_FRONTEND` | Frontend repo path (default: a `cellphone-repair-mgnt-app` folder next to the backend repo; auto-cloned there if absent). |
| `CPR_FRONTEND_REPO` | Frontend git URL for the auto-clone. |
| `LARAGON_EXE` | Full path to `laragon.exe` (default: `D:\laragon\laragon.exe`). |

---

## 6. Troubleshooting

| Symptom | Fix |
|---|---|
| **"running scripts is disabled on this system"** | Use the `.bat` files (they pass `-ExecutionPolicy Bypass`). To run a `.ps1` directly: `powershell -ExecutionPolicy Bypass -File .\scripts\dev-up.ps1`. |
| **UAC prompt declined / no admin** | Open Notepad **as administrator**, open `C:\Windows\System32\drivers\etc\hosts`, add a line `127.0.0.1  cp-repair-mgnt-app`, save. Re-run `dev-up`. |
| **`Apache not on :80`** | Something else owns port 80 (IIS, XAMPP's Apache, Skype). Stop it, or run with `-NoProxy` and use `http://localhost:3000`. |
| **`git not found`** | Install Git for Windows and open a **new** terminal (PATH refresh). |
| **`git clone failed` / auth loop** | The repo is private — complete the GitHub sign-in window. Or configure an SSH key and pass `-FrontendRepo`/`-BackendRepo` with the `git@github.com:` URL to bootstrap. |
| **`could not create databases` / MySQL auth error** | Open `cellphone-repair-mgnt-backend\.env` and make `DB_USERNAME` / `DB_PASSWORD` match your MySQL. Laragon default is `root` with an empty password. |
| **Can't log in / no users** | The DB wasn't seeded. Run `dev-up.bat -Seed` (or `-Fresh` to rebuild from scratch). |
| **App loads but every API call fails** | The frontend bakes the API URL in at build time. Re-run `dev-up.bat` (it rebuilds), or `-Fresh`. Check `cellphone-repair-mgnt-app\.env.local` has `NEXT_PUBLIC_API_URL=http://cp-repair-mgnt-app/api/v1`. |
| **`npm run build` fails** | Open the frontend folder and run `npm run build` to see the real error. As a stop-gap, use `dev-up.bat -Dev` (no build step). |
| **`/api/v1/ready` returns 503, `"redis": false`** | Expected. Cache and queue run on the database; the app works fine. |
| **`php not found` but PHP is installed** | It's not on PATH. Either add it, or install Laragon (the script falls back to Laragon's bundled PHP). |
| **A server window shows an error and closes** | Re-run `dev-up` from a PowerShell window (not double-click) so the output stays visible, and read the failing step. |

---

## 7. Manual setup (without the scripts)

If you'd rather do it by hand, or you're on macOS/Linux (the scripts are
Windows-only):

```bash
# Backend
git clone https://github.com/jehnsen/cellphone-repair-mgnt-backend.git
cd cellphone-repair-mgnt-backend
cp .env.example .env
# create the databases (MySQL/MariaDB):
#   CREATE DATABASE cp_repair_db          CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
#   CREATE DATABASE cp_repair_db_testing  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
composer install
php artisan key:generate
php artisan migrate --seed          # runs DatabaseSeeder (baseline + demo data)
php artisan storage:link
php artisan serve                   # http://127.0.0.1:8000

# Frontend (separate terminal)
git clone https://github.com/jehnsen/cellphone-repair-mgnt-app.git
cd cellphone-repair-mgnt-app
cp .env.example .env.local          # NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api/v1
npm install
npm run dev                         # http://localhost:3000
```

For the `cp-repair-mgnt-app` hostname without the script, add
`127.0.0.1  cp-repair-mgnt-app` to your hosts file and drop
[`scripts/apache/cp-repair-mgnt-app.conf`](../../scripts/apache/cp-repair-mgnt-app.conf)
into Laragon's `etc\apache2\sites-enabled\`, then reload Apache.

Run the test suite with `composer test` (needs the `cp_repair_db_testing`
database).
