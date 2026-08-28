# Multi-Instance Deployment

Running multiple simultaneous game sessions (e.g. `CNDQ-fall2026`, `CNDQ-spring2027`) from a single codebase with isolated data directories.

---

## Repository Layout

The git repo (`main` branch) has two top-level entries:

```text
main/
  static/           ← all web-served files (PHP, CSS, JS, …)
    index.php
    api/  css/  js/  lib/  admin/  login/  …
  data/             ← gitignored; must be created manually (see below)
  docs/
  tests/
  package.json
  playwright.config.js
  …
```

`data/` is listed in `.gitignore` and is never committed.  It must be created by hand on every machine (dev and production) before the app will run.

---

## How Instances Work

Each instance is a directory that contains:

- A single symlink `static → ../main/static` pointing at the shared code
- Its own real `data/` directory

Because browser API calls are relative (e.g. `./api/session/status.php`), they naturally scope to whichever instance URL the request came from — no JS changes needed.

Data isolation is handled by `cndq_data_dir()` in `lib/Database.php`.  On production, Apache sets `SCRIPT_FILENAME` to the **unresolved** symlink path (e.g. `/var/www/html/instance1/static/api/session/status.php`).  The function splits on `/static/` to find the instance root, then appends `/data`:

```php
function cndq_data_dir(): string {
    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    if (!empty($script) && !str_contains($script, 'server.php')) {
        $pos = strpos($script, '/static/');
        if ($pos !== false) {
            return substr($script, 0, $pos) . '/data';
        }
    }
    // Herd/local dev: static/lib/../../data = repo-root/data
    return __DIR__ . '/../../data';
}
```

---

## Directory Layout (production)

```text
/var/www/html/
  main/                   ← git repo (git pull here to update everything)
    static/
      index.php  api/  css/  js/  lib/  …
    data/                 ← main's own data dir (manually created)

  mainLink/               ← instance template (not in git; see below)
    static  →  ../main/static

  instance1/              ← cp -rP mainLink instance1
    static  →  ../main/static
    data/                 ← manually created after cp

  instance2/
    static  →  ../main/static
    data/
```

---

## First-Time Server Setup

```bash
# 1. Clone the repo
cd /var/www/html
git clone https://github.com/TLTMedia/CNDQ.git main

# 2. Create main's data directory
mkdir main/data

# 3. Build the instance template (one-time, not in git)
mkdir mainLink
ln -s ../main/static mainLink/static
```

---

## Creating a New Instance

```bash
# Copy the template (preserves the symlink as a symlink)
cp -rP mainLink /var/www/html/instance1

# Create that instance's isolated data directory
mkdir /var/www/html/instance1/data
```

No Apache config changes required.

### SELinux (if enforcing)

```bash
chcon -R -t httpd_sys_rw_content_t /var/www/html/instance1/data
# or persist across relabels:
semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/html/instance1/data(/.*)?"
restorecon -R /var/www/html/instance1/data
```

---

## Updating All Instances

All instances share the same `main/static/` code, so a single pull updates every instance simultaneously:

```bash
cd /var/www/html/main
git pull
```

---

## Local Development (Herd)

Run a single instance directly from the repo.  Create `data/` once:

```bash
mkdir data
```

Then start the dev server from the repo root:

```bash
npm run server        # php -S localhost:8000 -t static
# or let Playwright start it automatically:
npm test
```

The `cndq_data_dir()` Herd fallback resolves to `static/lib/../../data` = `main/data`. No symlinks or instance directories needed locally.
