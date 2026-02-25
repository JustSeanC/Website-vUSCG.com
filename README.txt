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

## Shared Navigation (PHP include)

Top navigation is centralized in `_includes/nav.php` and included from each page header using PHP.

- Edit `_includes/nav.php` to add/remove/reorder menu items.
- `.html` pages are configured to run through PHP via `.htaccess` so includes work without renaming pages.
- Keep `<nav id="nav">` in each page and include the shared nav partial inside it.

