# vUSCG Public Website

Static website for **vUSCG.com** (public-facing site), managed with GitHub and edited locally in VS Code.

## Overview

This repository contains the **public HTML/CSS/JS website** for vUSCG.

It includes:
- Static HTML pages
- Site assets (CSS, JS, fonts)
- Images used by the public site

It does **not** include the crew portal / phpVMS site (hosted separately).

## Live Site

- Public Website: https://vuscg.com
- Crew Portal (separate system): https://crew.vuscg.com

## Tech Stack

- HTML5
- CSS
- JavaScript
- cPanel hosting (live deployment)

## Project Structure

```text
.
├── index.html
├── about.html
├── staff.html
├── partners.html
├── sop.html
├── training.html
├── fleet.html
├── districts.html
├── 2025.html
├── assets/
│   ├── css/
│   ├── js/
│   └── webfonts/
├── images/
├── .htaccess          # optional / host-specific
└── .gitignore
```

## Shared Navigation (PHP include)

Top navigation is centralized in `_includes/nav.php` and included from each page header using PHP.

- Edit `_includes/nav.php` to add/remove/reorder menu items.
- Top-level submenu labels link to real pages (`About` -> `about.html`, `Operations` -> `fleet.html`) as a fallback if dropdown JS is unavailable.
- `.html` pages are configured to run through PHP via `.htaccess` so includes work without renaming pages.
- Keep `<nav id="nav">` in each page and include the shared nav partial inside it.

## Home Live Stats

Homepage live stats are loaded from `api/stats-home.php` and displayed in `index.html`.

1. Copy `api/stats-home.config.example.php` to `api/stats-home.config.php`.
2. Set `google_sheet_csv_url` to your **published CSV URL**:
   - Format: `https://docs.google.com/spreadsheets/d/<SHEET_ID>/export?format=csv&gid=<GID>`
3. Configure phpVMS source in `phpvms.mode`:
   - `http` (recommended): point `http_url` to your crew endpoint, e.g. `https://crew.vuscg.com/api/stats-home.php`
   - `mysql`: set credentials and SQL directly (only if this host can reach that DB)
4. Optional cache settings are in `cache`.

### phpVMS Option A (recommended): crew endpoint

Use an endpoint on crew host and let this public site call it.

- Copy `api/stats-home.example.php` from this repo into crew portal web root as:
  - `crew.vuscg.com/public/api/stats-home.php`
- Update DB credentials in that file.
- Test endpoint directly in browser:
  - `https://crew.vuscg.com/api/stats-home.php`
- Then set `phpvms.mode = 'http'` and `http_url` in `api/stats-home.config.php` on this public site.

### Finding DB username/password

Common places to find phpVMS DB credentials (crew host):

1. **cPanel → MySQL® Databases**
   - See DB users and which DB they are assigned to.
2. **phpVMS `.env` file** (Laravel install)
   - Usually in crew app root: `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_HOST`, `DB_PORT`.
3. **cPanel File Manager**
   - Open crew portal root and inspect `.env` (enable “show hidden files”).

Never commit real credentials to this Git repo.
