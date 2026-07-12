# TechnoStationery Dashboard — Webapp Scripts

> **Last updated**: 2026-07-12 | Presentation v6.4.3

---

## Dashboard App (`dashboard-dev/`)

React + TypeScript SPA served from `/build/`.

### Build & Deploy
```bash
cd webapp/dashboard-dev
npm install
npm run build        # tsc → vite build → scripts/post-build.sh → /build/
npm run test         # vitest run (2 tests)
npm run lint         # eslint
```

**`scripts/post-build.sh`** — copies `/tmp/dashboard-build` → `public_html/build/` via rsync  
and injects a timestamped build stamp (`v202607XXXXXX`) into `index.html` for SW cache-busting.

Live URL: **https://dashboard.technostationery.com/build/**

---

## Presentation (`../presentation/`)

Single-file PHP slide deck — v6.4.2 — 38 slides, all real production data.

| File | Purpose |
|------|---------|
| `index.php` | Auth-gated presentation (production) |
| `index.html` | Exact copy of index.php (synced manually after every fix) |
| `test_view.html` | PHP block stripped — auth-free for Playwright/curl testing |
| `chart.umd.min.js` | Local Chart.js fallback (CDN primary) |
| `techno-logo.png` | Source logo (embedded as CSS custom property `--logo-img`) |
| `.htaccess` | CSP headers, Chart.js CDN allowlisted in `connect-src` |

Live URL: **https://dashboard.technostationery.com/presentation/**

### Ground Truth Data (H1 2026)
| Metric | Value |
|--------|-------|
| CMD_Done H1 2026 | **498** |
| Revenue | **2,784,169 DZD** (2.78M) |
| AOV | **5,591 DZD** |
| Cancel rate | **35.2%** (288/819) |
| YoY growth | **+11.9%** (445 → 498) |
| Monthly | Jan 116 · Feb 69 · Mar 74 · Apr 81 · May 88 · Jun 70 |
| Top wilaya | Alger 153 · Constantine 26 · Tizi Ouzou 22 · Blida 21 |

---

## Data Fetcher Scripts

### `get_all_real_data.py`
Fetches production data from Magento REST API.
- Requires valid token in `/home/dashboard/public_html/config/magento_credentials.json`
- Outputs `fresh_data_2026h1.json` and `real_data.json`
- Usage: `python3 get_all_real_data.py`

### `fetch_h1_data.sh`
Bulk-fetch H1 2026 CMD_Done orders (5 pages × 100).
- Usage: `bash fetch_h1_data.sh`
- Output: `orders_page{1-5}.json` (temp, gitignored)

## Data Files

### `real_data.json`
Ground truth production snapshot (2026-07-12).

### `fresh_data_2026h1.json`
Full wilaya breakdown for H1 2026 CMD_Done orders (498 orders, 47 wilayas).

---

## Token Refresh (when API returns 403)
```bash
curl -s -X POST https://technostationery.com/rest/V1/integration/admin/token \
  -H "Content-Type: application/json" \
  -d '{"username":"mabbot","password":"@dM1n$#@2o26MaBB0T"}'
```
Update `/home/dashboard/public_html/config/magento_credentials.json` with the new token.

---

## Git Workflow

```bash
cd /home/dashboard/public_html
git status                  # check state
git add -A && git commit    # commit changes
git fetch origin main
git rebase origin/main      # sync with remote
git push origin dev         # push to dev branch
# Then update PR #4: https://github.com/mounirtms/dashboard/pull/4
```

### Branch: `dev` → PR #4 → `main`
- PR: https://github.com/mounirtms/dashboard/pull/4
- Last commit: `52b82162` (v6.4.3)
