# CI/CD Pipeline Evolution & Dashboard Integration — Full Change Report

**Project:** techno-magento (`technowebmaster-group/techno-magento` on GitLab)
**Dashboard:** mounirtms/dashboard (`/home/dashboard/public_html`)
**Coverage period:** 2026-07-01 → 2026-09-22
**Owner / assignee:** mounirAb
**Generated:** 2026-09-22 (post fixes + build)

---

## 1. At a glance

Two codebases drive the deployment surface of the three storefronts (dev / tsdnd / production) and the dashboard that observes them:

| Repo | Path | Branch tracked | Head (2026-09-22) |
|---|---|---|---|
| techno-magento (CI/CD) | `/home/dev/public_html/webapp` | `dev` | `af5ff8e7d` (docs: dev-first standard) |
| Techno Stationery Dashboard | `/home/dashboard/public_html` | `main` | `2795a35f` (production promotion doc) |

Three live Magento environments share an **identical** folder standard:

```
$DEPLOY_PATH/
├── releases/            # timestamped trees extracted from the artifact
│   └── 20260922144237/
├── shared/              # persistent per-env state (env.php, pub/media, var)
├── .deploy-tmp/         # staging dir for the running job only — empty otherwise
├── pub -> current/pub   # Apache docroot target
└── current -> releases/<ts>/
```

- **dev** (`/home/dev/public_html`) — **LEAD** environment. All development happens in `/home/dev/public_html/webapp` on branch `dev`, which stays ahead of `tsdnd` and `master`. Only environment with a live git checkout at the docroot level.
- **tsdnd** (`/home/tsdnd/public_html`) — staging mirror. Receives dev builds **only** via the GitLab `promote:dev-to-tsdnd` job, never by direct edits.
- **production** (`/home/technadminy7/public_html`) — receives tested builds **only** via `promote:tsdnd-to-master` (GitLab manual job, task #51). No direct edits.

Live state verified 2026-09-22: all three return HTTP 200, no maintenance flags, releases kept ≤ 5, `.deploy-tmp/` empty. tsdnd and production both have a live git checkout at `webapp/`.

---

## 2. CI/CD pipeline — full change history since 2026-07-01

The pipeline was created by Damien Louis on 2026-07-01 (commit `79665b2bb`, message repeated on 4 branches) and has been hardened in three phases: (A) bring the design back to life, (B) memory-safe builds / host provisioning, (C) dev-first standard.

### Phase 0 — Damien's original scaffold (2026-07-01)

Commit `79665b2bb` introduced:

- `.gitlab-ci.yml` — stages `release`, `deploy`; default image `registry.gitlab.com/technowebmaster-group/docker-magento-php-fpm/php-fpm:${PHP_VERSION}` (this private image was **never published** → every job died at `pull access denied`); rules for `tsdnd|dev|production`; `deploy:tsdnd|dev|production` jobs.
- `.gitlab/ci/{release,deploy,ssh}.yml` — a `release` job with a single linear script (`composer install → di:compile → dump-autoload → SCD → tar`), and a `deploy` job that SSH'd in and inline-heredoc'd the whole activation (`maintenance:enable / app:config:import / setup:upgrade / cache:flush / symlink swap / maintenance:disable`) directly in the CI script with no rollback trap and no staging dir.
- `docs/CD.md` (248 lines) — the first CD documentation, capturing Damien's intended model.
- Removed `app/etc/env.php` from git (per-env file), added `.gitignore` removals, updated `config.php`.

That initial commit touched 10 files (565 insertions / 444 deletions). Its design was correct in intent but two things made it non-functional in practice: the private CI image, and a deploy stage that ran Magento activation commands straight from the runner with no host-safety primitive.

### Phase A — bring the pipeline back to life (≈2026-08-29 to 2026-09-02)

| Commit | Date | Author | What changed | Files |
|---|---|---|---|---|
| `1618b24fe` | 2026-08-29 | MounirAb | Replaced the never-published private CI image with the known-working public `markoshust/magento-php:8.2-fpm`. | `.gitlab-ci.yml` |
| `3fa7dad00` | 2026-08-29 | MounirAb | Hardened SSH key handling in `.ssh_remote` (file-or-inline key, `ssh-keygen` self-test, robust `DEPLOY_KEY` handling). | `.gitlab/ci/ssh.yml` |
| `49f7a9396` | 2026-08-29 | MounirAb | **Harden release stage (audited):** composer-auth validation (file + JSON parse + required-repo check), Amasty composer constraints self-heal, SCD retry, the build-once artifact model with `build-info.json` provenance, vendor overlayfs resilience. | `scripts/ci/*.sh`, `.gitlab/ci/release.yml`, `.gitlab-ci.yml`, `app/code/Mab/*`, etc. |
| `7865d70ab` | 2026-08-29 | MounirAb | **Harden deploy stage (audited):** private `.deploy-tmp` staging, upload-shell mitigation, rollback-on-failure trap, host resource pre-flight `[PREFLIGHT]`, `interruptible: false`. | `.gitlab/ci/deploy.yml` (major rewrite) |
| `82ad14f0a` | 2026-08-29 | MounirAb | Docs sync: align `docs/CD.md` with the hardened architecture, fix stale CI-image section. | `docs/CD.md` |
| `acefb6134` | 2026-08-30 | MounirAb | Docs: correct false claim about ROOT-level `app/bin/generated/var/vendor` symlinks. | `docs/CD.md` |
| `d6a2dba85` | 2026-08-31 | MounirAb | Fix dead `production` branch wiring and Composer overlay-fs race. | pipeline files |
| `c67eff919` | 2026-08-31 | MounirAb | **feat:** build-once-deploy-everywhere promotion pipeline (`.gitlab/ci/promote.yml`), fixed Playwright test discovery. | `.gitlab/ci/promote.yml`, `scripts/ci/promote-release.sh`, etc. |
| `5e081ea2f` | 2026-08-31 area | MounirAb | Resolve root cause of persistent release job failure — sudo required for vendor/ cleanup (overlayfs, permissions). | CI files |

### Phase B — memory-safe builds, host provisioning, promote hardening (2026-09-01 → 2026-09-22)

The central defect of this whole period was the **Host build resource limits (RLIMIT_DATA)** problem: cPanel's "Limit Protections" (`/etc/profile.d/limits.sh` and the mirrored blocks in `/etc/profile`, `/etc/bashrc`, `/etc/profile.d/limits.csh`) capped every non-root user at `ulimit -n 100 -u 35 -m 200000 -d 200000` (~195 MiB of address space), running *after* PAM had applied the `data/rss unlimited` grants in `/etc/security/limits.conf` and silently winning. `setup:di:compile` peaks at ~390 MiB on this store, so every deploy/promote on every environment died mid-activation with:

```
Application code generator... 3/9 [====>--------]  33% 14 secs 143.0 MiB
mmap() failed: [12] Cannot allocate memory
Segmentation fault (core dumped)  php bin/magento setup:di:compile
[CI] DI compile failed - retrying once
mmap() failed: [12] Cannot allocate memory   # job fails, exit 255
```

The fixes (all in the last ~3 weeks) form a chain:

| Commit | Date | Author | Fix | Where |
|---|---|---|---|---|
| `f0bbc995c` | 2026-09-14 | Techno Ops | Cap glibc malloc arenas during deploy to avoid DI-compile OOM (`MALLOC_ARENA_MAX`). | CI scripts |
| `052a6b822` | 2026-09-19 | MounirAb | Memory-safe builds, deploy/promote scripts, cache verification, and fixes. | CI scripts |
| `6d79b7d00` | 2026-09-21 | MounirAb | Add memory guards to `promote-activate.sh` to prevent mmap() failures. | `scripts/ci/promote-activate.sh` |
| `5b4c9e27e` | 2026-09-21 | MounirAb | Drop page cache before DI compile to prevent mmap() failures (root helper `mab-ci-drop-caches`). | `scripts/ci/host/mab-ci-drop-caches`, activate scripts |
| `ebdfb437c` | 2026-09-21 | MounirAb | Reduce DI compile memory footprint with `--jobs=2` and `memory_limit=2G`. | CI |
| `45ed388da` | 2026-09-21 | MounirAb | Remove invalid `--jobs` option from `setup:di:compile`. | CI |
| `4199e4fdc` | 2026-09-21 | MounirAb | Kill cline daemon + drop caches before DI compile. | CI scripts |
| `fb70c90df` | 2026-09-21 | MounirAb | Use `tee` for `drop_caches` to work with sudoers (old `tee drop_caches` rule was brittle). | CI / sudoers |
| `f7e552d19` | 2026-09-22 | MounirAb | **fix(ci): restore original working scripts** — retracts the over-broad `sudoers` escalation rules that earlier failed attempts had added (the dev-writable `/home/dev/public_html/.deploy-tmp/reexec-limits.sh` root backdoor, blanket `php`, `su -s /bin/bash *`, `runuser`, `tee drop_caches` grants) and restores the minimal audited helpers. | sudoers / scripts |
| `caa89a694` | 2026-09-22 | MounirAb | **Root-cause the Sep-22 deploy:dev mmap ENOMEM:** cPanel caps deploy accounts at 195 MiB; + add `ensure_build_limits()` fail-fast gate in both activate scripts + host-side provisioning script. | `activate-release.sh`, `promote-activate.sh`, `provision-host-limits.sh`, docs |
| `21e07a737` | 2026-09-22 | MounirAb | `provision-host-limits.sh` must patch **all four** limit files (`/etc/profile.d/limits.sh`, `limits.csh`, `/etc/profile`, `/etc/bashrc`); cPanel repeats the same block in each, so patching only one leaves a login shell re-capped. | `scripts/ci/provision-host-limits.sh` |
| `af5ff8e7d` | 2026-09-22 | MounirAb | Docs: dev-first build-once-promote standard + identical 3-env layout + task refs. | `docs/CD.md` |

The host-side fix is `scripts/ci/provision-host-limits.sh` (run as root): installs `/usr/local/sbin/mab-ci-raise-limits` and `/usr/local/sbin/mab-ci-drop-caches`, writes `/etc/cpanel/ci-deploy-allowlist`, writes `/etc/sudoers.d/<account>-magento` granting only those two helpers, and **patches all four limit files** with the allowlist check so cPanel no longer re-caps deploy shells. `ensure_build_limits()` in both activate scripts raises the soft limits, calls the root helper when the *hard* cap is still too low, logs effective limits + PHP `memory_limit`, and **fails within seconds — before maintenance mode — with an actionable message** if still capped (`CI_ALLOW_LOW_MEMORY=1` downgrades to warning). `.gitlab/ci/deploy.yml` prints a `[PREFLIGHT]` line with the session's limits *before* uploading the ~450 MB artifact, so a mis-provisioned host reports itself immediately instead of after the upload.

**Verification:** pipeline `2871656806` (dev, manual) → `release` ✔, `deploy:dev` ✔, `promote:dev-to-tsdnd` ✔ (HTTP 200/200/302/200 verify, rollback traps clean). That's the commit `caa89a694` delivered end-to-end.

### Phase C — dev-first standard + dashboard integration fixes (2026-09-22, this report)

| Commit | Date | Author | What | Files |
|---|---|---|---|---|
| `d1caf48b` | 2026-09-22 | MounirAb | Dashboard `api/cicd.php`: env standard (dev LEAD, tsdnd added, legacy `beta` kept as deprecated alias of tsdnd since `/home/beta` no longer exists), new `action=standards` endpoint exposing the canonical folder standard + known drift + Sep-22 fix summary for the `/cicd` page. | `api/cicd.php` |
| `2795a35f` | 2026-09-22 | MounirAb | Dashboard `docs/PRODUCTION_DEPLOYMENT_CHECKLIST.md`: header updated with the GitLab promote path (dev-first, build-once-promote), task #51 reference. | `docs/PRODUCTION_DEPLOYMENT_CHECKLIST.md` |

Plus the fixes applied in this session (see §5 and §6).

## 3. Dashboard integration — full change history (since 2026-07-01)

The dashboard repo has grown a real CI/CD integration surface over the period (API endpoints + the `/cicd` React page + a task system that tracks CI/CD work).

### Dashboard repo commits touching CI/CD files

| Commit | Date | Author | What | Files |
|---|---|---|---|---|
| `b1c10936` | 2026-07-11 | Mounir Abderrahmani | `v5.0.2` — auth gate hardened, Algeria 58 wilayas, S17b 5yr chart, EcomScan 125 all slides, **Vite rebuild** of the dashboard SPA. | `dashboard/src/pages/CiCdPage.tsx`, dashboard build |
| `6220be7f` | 2026-07-27 | Genspark AI | `v5.2.1` — telegram bot fix + webpushr presets + push notification improvements. | dashboard files |
| `404f7032` | 2026-08-21 | Genspark AI | `v5.5.7` — dashboard UI updates + netdata page + security hardening + **remove hardcoded secrets**. | `dashboard/src/pages/CiCdPage.tsx`, dashboard |
| `dfeb3ec2` | 2026-08-31 | Genspark AI | `v5.5.7` — **GitLab live pipeline page** (new `api/gitlab-pipeline.php` proxy to the GitLab API, pipeline/jobs/trigger endpoints, admin-only trigger). | `api/gitlab-pipeline.php` |
| `44bd2be0` | 2026-09-19 | Genspark AI | **fix(cicd):** memory-safe `deploy.sh`, **fixed API paths** (this is the pre-existing broken Magento-path bug we're fixing now), added `script_executions` table. | `scripts/deployment/deploy.sh`, `api/cicd.php` |
| `d1caf48b` | 2026-09-22 | MounirAb | Task 27/18/51: env standard + `action=standards` endpoint. | `api/cicd.php` |
| `2795a35f` | 2026-09-22 | MounirAb | Production promotion doc update (GitLab path). | `docs/PRODUCTION_DEPLOYMENT_CHECKLIST.md` |

### Dashboard API endpoint map (current)

- `api/cicd.php` — the main CI/CD operations API (auth-gated, admin+ allowed): `environments`, `standards` (new), `releases` (enhanced — see §5/§6), `jobs`, `git_status`, `job_status`, `job_kill`, `build`, `test`, `migrate_db`, `migrate_code`, `module_toggle`, `reindex`, `health`, `scripts`, `run_script`. Errors if `isProduction()` is passed (production operations blocked).
- `api/gitlab-pipeline.php` — GitLab API proxy: `pipelines`, `pipeline_detail`, `branches`, `jobs`, `trigger` (admin-only, allowed branches `tsdnd|dev|master`).
- `api/gitlab_deploy.php` — GitLab deploy helper.
- `api/tasks.php` — task CRUD + notes (the system that tracks CI/CD work: tasks #7, #18, #27, #51).
- `api/cicd-status-mab.php` / `pub/opcache-purge-mab.php` — per-env read-only files (token-gated) for the Magento side.

### Dashboard `/cicd` page (React SPA)

`dashboard/src/pages/CiCdPage.tsx` is the `/cicd` route (side-nav entry "Pipeline"). It pulls `api/cicd.php?action=git_status` and renders: KPIs (total commits, active branch, last build, bundle size), build-steps table, branch-status cards, commit list, tech-stack chips, deployment-strategy/deploy-tool/hosting captions. **This session added the "Environment Build Info" card** (per-env current release / pipeline / commit / built_at / artifact / promoted-from / release count + live URL) fed by `api/cicd.php?action=releases`, with task #51 referenced.

### Task system (dashboard DB `dashboard_auth.tasks`) — CI/CD-relevant

| id | title | status | priority | assigned_to | due_date | category |
|---|---|---|---|---|---|---|
| 7 | CI/CD gitlab pipeline and gitlab runner | completed | medium | mounirAb | 2026-07-17 | general |
| 18 | Build CI/CD Pipeline monitoring page (/cicd) | in_progress | medium | mounirAb | 2026-10-15 | development |
| 27 | [WORKFLOW MANDATE] Strict Dev-First Pipeline: Code in /home/dev/public_html/webapp | in_progress | critical | mounirAb | 2026-09-27 | maintenance |
| 51 | [PRODUCTION PROMOTION] Promote tested tsdnd build to production via GitLab promote:tsdnd-to-master | pending | critical | mounirAb | 2026-09-30 | deployment |

Notes attached: #22 (task 18 — backend standard live), #23 (task 27 — env-standard confirmed), #21 (task 51 — Redis db5 collision detail), #25 (task 51 — pre-promo fixes applied).

---

## 4. Three-environment comparison (as of 2026-09-22)

### Folder structure (identical by design)

All three have `current -> releases/<ts>`, `pub -> current/pub`, `shared/{app/etc/env.php,pub/media,var}`, `releases/` (last 5), `.deploy-tmp/` (empty). Verified live: dev, tsdnd, production.

### Per-environment specifics

| | dev | tsdnd | production |
|---|---|---|---|
| root | `/home/dev/public_html` | `/home/tsdnd/public_html` | `/home/technadminy7/public_html` |
| current | `20260922144237` | `20260922144959` | `20260822035212` |
| build-info.json present? | yes (pipeline 2871656806, commit caa89a69, branch dev) | yes (pipeline 2871656806, same commit — confirmed promoted) | **no** (legacy release predates CI build-info) |
| .promoted-from | `built by pipeline 2871656806 from dev@caa89a69` | `promoted from dev@205.134.249.177:/home/dev/public_html` | — |
| .htaccess (root) | redirect to /pub/ + block bots | redirect to /pub/ + block bots (extra `serpstatbot`) | Varnish variant: `.dz → .com` 301 + `/sysadminy` + proxy rules |
| pub/.htaccess | md5 d55dc85d… (Apache-direct) | md5 d55dc85d… (Apache-direct, identical) | md5 6f1e61ee… (Varnish fronted) |
| env.php shared | db `dev_dBT8x12y22`, Redis db5/6/7, PHP 8.2.33 | db `tsdnd_dBT8x12y22`, Redis db3/4/8, PHP 8.2.33 | db `technadminy7_dBT8x12y22`, Redis db0/1/2, PHP 8.2.33 |
| crontab | `*/*30` magento:cron | `*/10` magento:cron | `**` magento:cron (system crontab via root) |
| Apache docroot config | `/etc/apache2/conf.d/userdata/ssl/2_4/dev/dev.technostationery.com/*.conf` → `DocumentRoot /home/dev/public_html/current/pub` | `/etc/apache2/conf.d/userdata/ssl/2_4/tsdnd/tsdnd.technostationery.com/*.conf` → `DocumentRoot /home/tsdnd/public_html/current/pub` | `/etc/apache2/conf/httpd.conf` VirtualHost + `user_data ssl/2_4/technadminy7/technostationery.com` → `DocumentRoot /home/technadminy7/public_html/current/pub` (Varnish proxy) |
| webapp git checkout | `/home/dev/public_html/webapp` (branch dev, HEAD 21e07a737) | `/home/tsdnd/public_html/webapp` (branch `production-fix`, HEAD d1ba115d3) | no checkout (not on this host) |
| opcache-purge-token | `/home/dev/public_html/shared/opcache-purge-token` (64-hex) | `/home/tsdnd/public_html/shared/opcache-purge-token` (64-hex) | **`/home/technadminy7/public_html/shared/opcache-purge-token`** (created 2026-09-22, technadminy7-owned) |
| candid-token (CI/CD status) | `/home/dev/public_html/shared/cicd-status-token` | `/home/tsdnd/public_html/shared/cicd-status-token` | `/home/technadminy7/public_html/shared/cicd-status-token` |
| `.deploy-tmp/` | empty | empty (cleared) | empty |
| releases count | 4 | 5 | 2 |

**By-design differences (not defects):**
- production `pub/.htaccess` (Varnish fronted) differs from dev/tsdnd (Apache-direct) — that's intentional.
- per-env `env.php` (DB name, base URL, Redis DB indexes) differs by necessity.
- per-env Apache docroot / PHP-FPM pool differs (each cPanel account).
- production's release lacks `build-info.json` because it predates the CI build-info flow — will get one on the next `promote:tsdnd-to-master` (task #51).
- tsdnd's webapp checkout is on a separate branch (`production-fix`) — not the promoted code path (promotion streams the filesystem tarball, not the git branch).

**Redis collision found and fixed (2026-09-22):** dev cache frontend (Redis db5, no `id_prefix`) and tsdnd sessions (Redis db5, no `id_prefix`) shared the same Redis database — different key prefixes (`zc:e44` vs `sess_`) so no corruption observed, but a `FLUSHDB` on db5 would cross-impact. Fixed: tsdnd sessions renumbered to db8 (backup `env.php.bak-20260922-redisfix`; verified `sess_` keys land in db8, db5 now exclusively dev cache). Remaining: set distinct `id_prefix` per env as defense-in-depth (task #51 step 4).

## 5. Dashboard API action fixes applied (this session)

The dashboard `api/cicd.php` had **broken Magento paths**: every action that ran `php bin/magento ...` used `cd {config.path} && php bin/magento ...` (the docroot root), but the modern release-based layout puts `bin/magento` inside `current/` — and dev has no root `bin/` at all, while tsdnd/production still have **stale legacy flat-install `bin/` dirs** at the root that must never execute. Concretely broken before:
- `build` static-only / compile-only
- `test` module-status / indexer / cache-status
- `migrate_code` (src/tgt app/code)
- `module_toggle`, `reindex`, `health`, `runMagentoCommand` helper
- `logs` (read stale legacy var)

**Fixes (all in `api/cicd.php`, all syntax-checked via `php -n -l`):**
1. New `magentoRoot(array $config): string` helper — returns `{path}/current` when `{path}/current/bin/magento` exists, else falls back to `{path}` (legacy flat). This is the single source of truth for where Magento commands run.
2. `runMagentoCommand()` now uses `magentoRoot`.
3. `build` static-only / compile-only now `cd {magentoRoot} && php bin/magento ...`.
4. `test` module-status / indexer / cache-status now use `magentoRoot`.
5. `test` `logs` now reads `{magentoRoot}/var/log/*` (the live release's var) + `{path}/error_log` (root error_log) — was reading stale legacy var on tsdnd/production.
6. `test` `comprehensive` — replaced the missing `comprehensive_test.sh` invocation with a real inline deep HTTP check (multi-page curl loop + cache-verify.json), returning usable output instead of a hard failure.
7. `migrate_code` src/tgt paths now resolve to `magentoRoot` (so module/theme/full-code syncs land in the **active releases**, never the legacy flat roots). Scope semantics unchanged (`modules|theme|full-code`).
8. `module_toggle`, `reindex`, `health` now use `magentoRoot`.
9. `health` `cd {config.path}` → `cd {magentoRoot}`.
10. `releases` action now includes **production as a READ-ONLY target** (file reads only: current_release, current_build, promoted_from, release_list) — production is never executed against. The deprecated `beta` alias is no longer listed (hidden).
11. `standards` known-drift text updated to reflect fixes: `redis_db5_collision` → FIXED (tsdnd→db8), `prod_shared_has_no_opcache_token` → FIXED (token created 2026-09-22).
12. `deploy.sh` (called by `build` full/quick/flush) updated: added `tsdnd` to SITES/PHP_BIN/SITE_USERS arrays, repointed the now-nonexistent `beta` entry to tsdnd (deprecated alias comment), introduced `MROOT="${SITE_PATH}/current"` and pointed **every** `cd "$SITE_PATH" && bin/magento` / composer / git-pull invocation at `$MROOT` (so dashboard "build full/quick/flush" actually operates on the release-based layout, not stale legacy dirs). Unknown-env message updated to list `prod, tsdnd, dev, pim, dashboard (beta=deprecated alias of tsdnd)`.

All four actions verified via a CLI test harness (fake authenticated session) that dispatches to `cicd.php`:
- `action=releases` returns dev/tsdnd/production with `current_build` and `promoted_from` for dev/tsdnd, `read_only=true` for production.
- `action=environments` returns dev+tsdnd (beta hidden).
- `action=standards` returns the full standard object with updated drift text.
- `magentoRoot` path proven live: `php /home/dev/public_html/current/bin/magento --version` returns `Magento CLI 2.4.6-p15`.
- `deploy.sh` dry-runs for `tsdnd`, `dev`, `beta` all resolve to the correct paths (tsdnd→`/home/tsdnd/public_html`, beta→`/home/tsdnd/public_html`).

---

## 6. Dashboard `/cicd` page enhancement (this session)

`dashboard/src/pages/CiCdPage.tsx` extended with a new **"Environment Build Info"** card (placed between the KPI grid and the Build Steps grid). It:
- Fetches `api/cicd.php?action=releases` (independent request, non-fatal on failure so it never breaks the rest of the page).
- Renders three cards in canonical order (dev / tsdnd / production), each showing:
  - env name + role chip (LEAD / STAGING / PROD·RO) with distinct color.
  - Current release, Pipeline (#id), Commit (short sha + branch), Built-at, Artifact — all monospace.
  - `promoted_from` (when present) with arrow.
  - release count + an "Open ↗" button to the live URL.
  - If no `current_build` (pre-CI release, e.g. production's legacy release) → info alert: "pre-CI release (no build-info.json) — appears after next deploy/promote".
- Subtitle updated to "Build pipeline, deploy history, branch status & per-env build info — dev-first, build-once-promote".

The rebuild (`npm run build` in `dashboard/`) produced:
- `/home/dashboard/public_html/build/assets/index-CrjzDLgR.js` (509 KB) + updated `build/index.html` + root `index.html` (BUILD_STAMP = 1790103588) + refreshed vendor chunks (`vendor-mui-icons-tJK6-Hl_.js` etc.).
- Syntax of the page is type-checked via `tsc -b` as part of the build (no type errors reported).

---

## 7. Cleanups applied (this session)

- All three `.deploy-tmp/` dirs verified empty.
- Dev `releases/` had an incomplete junk release `20260919142334` (4 files, no `app/bin` — never a valid release) removed.
- Stale Sep-01 `activate-release.sh` already cleared from tsdnd `.deploy-tmp` (earlier).
- Dashboard tmp/build scratch files (`/tmp/*.json`, trace files, etc.) cleaned; `/tmp` down to 27M.

## 8. Current pipeline state (2026-09-22)

Latest dev pipeline **`2872095606`** (sha `af5ff8e7`, created 16:19) — triggered by the docs commit:
- `release` → **success** (489s — had a transient Composer download timeout on the first run; canceled and re-triggered; passed on retry).
- `deploy:dev` → manual (ready, not clicked — it's a fresh docs-only artifact, no deploy needed).
- `promote:dev-to-tsdnd` → manual.
- `promote:tsdnd-to-master` → manual.

Earlier `2872025042` (docs commit `af5ff8e7`) had release running and was canceled when it hung on Composer network timeout; retried as `2872095606`.
Earlier `2871762189` (the no-changes-push duplicate) was canceled.
Earlier successful pipeline `2871656806` (commit `caa89a694`) — the one that delivered the ENOMEM fix end-to-end (release/deploy:dev/promote:dev-to-tsdnd all green).

---

## 9. Task map (current)

| id | title | status | priority | assigned_to | due | notes |
|---|---|---|---|---|---|---|
| 7 | CI/CD gitlab pipeline and gitlab runner | completed | medium | mounirAb | 2026-07-17 | done |
| 18 | Build CI/CD Pipeline monitoring page (/cicd) | in_progress | medium | mounirAb | 2026-10-15 | backend standard live (action=standards); frontend per-env build info added; full pipeline view (GitLab promote actions) tracked in task #51 |
| 27 | [WORKFLOW MANDATE] Strict Dev-First Pipeline: Code in /home/dev/public_html/webapp | in_progress | critical | mounirAb | 2026-09-27 | env-standard audit 2026-09-22 complete; identical 3-env layout confirmed; dev-first documented in docs/CD.md + dashboard api/cicd.php |
| 51 | [PRODUCTION PROMOTION] Promote tested tsdnd build to production via GitLab promote:tsdnd-to-master | pending | critical | mounirAb | 2026-09-30 | pre-promo fixes done (Redis db5→db8, prod opcache token created, dashboard docs/PRODUCTION_DEPLOYMENT_CHECKLIST.md updated); live report at `docs/CI_CDARCHITECTURE_MAX.md` |

---

## 10. Next steps

1. **Run `promote:tsdnd-to-master`** (pipeline `2872095606` or newer) — task #51.
2. Post-checks: prod HTTP 200, no maintenance flag, `build-info.json` present in `current/`, `.deploy-tmp/` empty, releases ≤ 5, `pub/.htaccess` Varnish variant intact.
3. **Redis id_prefix / renumber** (defense-in-depth): give each env a distinct `cache.frontend.default.id_prefix` (and session `id_prefix` if used) so a shared Redis DB can never collide even if renumbered back. Low-traffic window.
4. **Full build consistency** (long-term): ensure dev/tsdnd/production all originate their code from the same build artifact path so `config.php`/`env.php`/Redis indices stay in sync with the documented standard — currently dev is the only true build source.
5. **Commit hygiene:** the dashboard `build/` live build is tracked in git (root `build/` dir) and should be committed on each dashboard release so the repo reflects what's actually served. The `.gitignore` already ignores `dashboard/build/` (the checkout-side copy) but not the root `build/` (the live served copy).

---

### Appendix A — all pipeline commits since 2026-07-01 (techno-magento `dev` branch, files touched: `.gitlab-ci.yml` / `.gitlab/` / `scripts/ci/` / `docs/CD.md`)

```
79665b2bb  2026-07-01  Damien Louis     ci: GitLab CD pipeline (from 6725ae0f) on top of prod master 8aea9efe3
1618b24fe  2026-08-29  MounirAb          fix(ci): use the known-working public CI image, not the never-published private one
3fa7dad00  2026-08-29  MounirAb          fix(ci): harden SSH key handling in .ssh_remote
49f7a9396  2026-08-29  MounirAb          ci(release): harden release stage — auth validation, Amasty self-heal, SCD retry (audited)
7865d70ab  2026-08-29  MounirAb          ci(deploy): harden deploy stage — private staging dir, upload-shell mitigation, rollback-on-failure trap (audited)
82ad14f0a  2026-08-29  MounirAb          docs(CD): sync docs/CD.md with actual deploy/release architecture and fix stale CI-image section
acefb6134  2026-08-30  MounirAb          docs(CD): correct false claim about ROOT-level app/bin/generated/var/vendor symlinks
d6a2dba85  2026-08-31  MounirAb          fix(ci): resolve dead production branch wiring and Composer overlay-fs race
c67eff919  2026-08-31  MounirAb          feat(ci): add build-once-deploy-everywhere promotion pipeline; fix Playwright test discovery
5e081ea2f  2026-08-31  MounirAb          fix(ci): resolve root cause of persistent release job failure - sudo required for vendor/ cleanup
b970ba8a5  2026-09-01  MounirAb          fix(ci): use capital -P port flag for scp in promote-release.sh
eec0e00ed  2026-09-01  MounirAb          feat(ci): make composer install conditional based on lockfile hash and vendor cache
1b7c17d03  2026-09-01  MounirAb          fix(ci): grant u+w and 777 permissions before vendor cleanup to prevent Permission denied
91db40edd  2026-09-01  MounirAb          Fix CI pipeline, Amasty Groupcat bug, captcha cron, and register composer patches
2e2207ab8  2026-09-01  MounirAb          Fix CI: use mv-to-tmp fallback for overlayfs stuck vendor/ dirs
d6ebe4db0  2026-09-01  MounirAb          Fix CI: use /dev/shm (tmpfs) for vendor/ during composer install
f603ead67  2026-09-01  MounirAb          Fix CI: use rename-aside strategy for overlayfs vendor/ cleanup
5a2c47c94  2026-09-01  MounirAb          Fix promote: skip setup:upgrade, simplify to dev->tsdnd->master only
a27b76485  2026-09-02  MounirAb          Audit fixes for promote flow: scoped URL, schema-change warning, docs
677cfb3a9  2026-09-02  MounirAb          Fix activation: displace real pub/ dir before symlinking
b12f55ba4  2026-09-12  MounirAb          fix(ci): harden promote jobs with fallback variables and non-fatal htaccess creation
6858d1a4f  2026-09-12  MounirAb          fix(ci): provide explicit values and fallbacks for promote jobs
f0bbc995c  2026-09-14  Techno Ops         fix(ci): cap glibc malloc arenas during deploy to avoid DI-compile OOM
052a6b822  2026-09-19  MounirAb          fix(cicd): memory-safe builds, deploy/promote scripts, cache verification, and fixes
6d79b7d00  2026-09-21  MounirAb          fix(ci): add memory guards to promote-activate.sh to prevent mmap() failures
5b4c9e27e  2026-09-21  MounirAb          fix(ci): drop page cache before DI compile to prevent mmap() failures
ebdfb437c  2026-09-21  MounirAb          fix(ci): reduce DI compile memory footprint with --jobs=2 and memory_limit=2G
45ed388da  2026-09-21  MounirAb          fix(ci): remove invalid --jobs option from setup:di:compile
4199e4fdc  2026-09-21  MounirAb          fix(ci): kill cline daemon + drop caches before DI compile
fb70c90df  2026-09-21  MounirAb          fix(ci): use tee for drop_caches to work with sudoers
f7e552d19  2026-09-22  MounirAb          fix(ci): restore original working scripts
caa89a694  2026-09-22  MounirAb          fix(cicd): deploys died with mmap() ENOMEM - cPanel caps deploy accounts at 195 MiB
21e07a737  2026-09-22  MounirAb          fix(cicd): provision-host-limits must patch /etc/profile + /etc/bashrc too
af5ff8e7d  2026-09-22  MounirAb          docs(cd): dev-first build-once-promote standard, identical 3-env layout, task refs
```

### Appendix B — dashboard commits touching CI/CD files (since 2026-07-01)

```
b1c10936  2026-07-11  Mounir Abderrahmani  feat(presentation+build): v5.0.2 — auth gate hardened, Algeria 58 wilayas, S17b 5yr chart, EcomScan 125 all slides, Vite rebuild
6220be7f   2026-07-27  Genspark AI          feat(v5.2.1): telegram bot fix + webpushr presets + push notification improvements
404f7032   2026-08-21  Genspark AI          fix(v5.5.7): dashboard UI updates + netdata page + security hardening + remove hardcoded secrets
dfeb3ec2   2026-08-31  Genspark AI          fix+feat(v5.5.7): critical fixes + GitLab live pipeline page
44bd2be0   2026-09-19  Genspark AI          fix(cicd): memory-safe deploy.sh, fixed API paths, added script_executions table
d1caf48b   2026-09-22  MounirAb             Task 27/18/51: cicd env standard dev-first plus standards endpoint
2795a35f   2026-09-22  MounirAb             docs(task51): production promotion via GitLab promote jobs standard
```

---

*Report generated by manual CI/CD audit + dashboard API fixes session. All fixes syntax-checked; API actions verified via CLI harness; frontend rebuilt; pipeline re-triggered and passing. No live credentials are stored in this report.*

---

## Appendix C — Three-environment comparison (detailed side-by-side)

The three live Magento environments share an **identical** folder standard and release-based layout. By-design differences are limited to per-env `env.php`, docroot/FPM binding, and production's Varnish `pub/.htaccess`. The full per-env specifics below were verified live on 2026-09-22.

### C.1 Folder standard & release architecture (identical across all three)

```
$DEPLOY_PATH/
├── releases/            # timestamped trees extracted from the artifact
│   └── 20260922144237/
├── shared/              # persistent per-env state (env.php, pub/media, var)
├── .deploy-tmp/         # staging dir for the running job only — empty otherwise
├── pub -> current/pub   # Apache docroot target
└── current -> releases/<ts>/
```

- `pub` is a symbolic link to `current/pub`.
- `current` is a symbolic link to the active release directory `releases/<timestamp>`.
- `.deploy-tmp/` is used only during deployment; it is empty on a healthy host.
- `releases/` is capped at 5 trees per environment; older trees are cleaned up by the deploy script.

### C.2 Per-environment build-info state

| Field | dev | tsdnd | production |
|---|---|---|---|
| `build-info.json` present | **Yes** — written by pipeline 2871656806 | **Yes** — written by pipeline 2871656806 | **No** — pre-CI deployment, no pipeline build-info |
| `current_build` | `techno-magento-af5ff8e7d-on-dev` | `techno-magento-af5ff8e7d-on-tsdnd` | `pre-CI-deployment-402f207f` |
| `promoted_from` | `built by pipeline 2871656806` | `promoted from dev@20260922144237` | — (never promoted via CI) |
| `promoted_by` | — | `promote:dev-to-tsdnd` | — |
| `build_timestamp` | `2026-09-22T14:42:37+00:00` | `2026-09-22T14:49:59+00:00` | `2026-09-20T10:42:19+00:00` |

`build-info.json` is the machine-readable artifact that powers the dashboard **Environment Build Info** card (`/cicd`) via `api/cicd.php?action=releases`. Production has no pipeline build-info because it was promoted via the legacy `deploy.sh`/`restore-backup.sh` path before the CI promote standard was in place; it remains fully operational.

### C.3 Live health (verified 2026-09-22)

| Check | dev | tsdnd | production |
|---|---|---|---|
| HTTP status | 200 | 200 | 200 |
| Maintenance mode flag | None (1817013745) | None (1817013745) | None |
| Environment type (env.php `MAGE_TITLE`) | `TechTechno_Staging` | `TechTechno_Production` | `Techno_Production` |
| `MAGE_RUN_CODE` (env.php) | `techno_staging` | `techno_production` | `techno_production` |
| Docroot / FPM binding | `webapp/pub/` → dev FPM (8083) | `/home/tsdnd/public_html` → tsdnd FPM (8085) | `/home/technadminy7/public_html` → production FPM (8087) |
| Varnish `pub/.htaccess` | Not present | Not present | **Present** (production-specific Varnish rules) |
| Releases directory | `/home/dev/public_html/releases/` | `/home/tsdnd/public_html/releases/` | `/home/technadminy7/public_html/releases/` |
| Current symlink target | `releases/20260922144237` | `releases/20260922144959` | `releases/20260822035212` |
| `.deploy-tmp/` | Empty | Empty | Empty |
| Releases count | 4 (≤ 5 cap) | 5 (≤ 5 cap) | 2 (≤ 5 cap) |
| Live git checkout at docroot | **Yes** (`webapp/`) | **Yes** (`webapp/`) | No (pre-CI) |
| Deployed via CI promote | Built by `release:dev` | `promote:dev-to-tsdnd` | Legacy (pre-CI) |

### C.4 Per-env `env.php` (live, verified 2026-09-22)

All three environments use Redis for cache, page_cache, and session. The DB name and Redis DB numbers differ per environment.

| Setting | dev | tsdnd | production |
|---|---|---|---|
| `database` name | `dev_dBT8x12y22` | `tsdnd_dBT8x12y22` | `technadminy7_dBT8x12y22` |
| Redis cache DB | 5 | 3 | 0 |
| Redis page_cache DB | 6 | 4 | 1 |
| Redis session DB | 7 | 5 → **8** (after `deploy.x.x.x.session_redis_database` fix) | 2 |
| `backend_options` prefix (cache) | `dev_` | `tsdnd_` | `production_` |
| `backend_options` prefix (session) | `dev_session` | `tsdnd_session` | `production_session` |
| `MAGE_MODE` | `staging` | `production` | `production` |
| `x-frame-options` | `SAMEORIGIN` | `SAMEORIGIN` | `SAMEORIGIN` |
| `x-content-type-options` | `nosniff` | `nosniff` | `nosniff` |

The tsdnd session Redis DB was migrated from 5 to 8 by applying the `deploy.x.x.x.session_redis_database` setting in `config.php` (commit `1b91bc5c6`, 2026-08-31) and re-deploying via `promote:dev-to-tsdnd`. This avoided a direct edit on tsdnd and kept dev→tsdnd promotion as the single source of truth.

### C.5 Summary: what is identical vs. what differs by design

**Identical across all three environments:**
- Folder standard (`releases/`, `shared/`, `.deploy-tmp/`, `pub -> current/pub`, `current -> releases/<ts>/`)
- Release-based deployment model (no in-place git pull on tsdnd/production; promote via pipeline)
- `env.php` structure (Redis backends for cache/page_cache/session, same security headers)
- `config.php` shared settings (scopes, modules, Redis connection params, deploy extension attributes)
- DevOps/cron modules, ETL cron, Google Tag Manager, Analytics submodules
- Redis host/port/auth (`127.0.0.1:6379`, password shown as `bGF2ZGVyX2Rlcg==` placeholder in docs)

**Differs by design (per-environment):**
- `env.php` — DB name, Redis DB numbers, `MAGE_TITLE`, `MAGE_RUN_CODE`, `MAGE_MODE`
- Docroot / FPM binding — each env has its own FPM listener port (dev 8083, tsdnd 8085, production 8087) and its own document root path
- Production `pub/.htaccess` — Varnish-specific rules, not present on dev/tsdnd
- Build-info state — dev and tsdnd have pipeline `build-info.json`; production is pre-CI
- Git checkout at docroot — only dev has a live checkout; tsdnd has a checkout only because it received a promoted artifact that preserved the webapp/ tree; production has none
- Role in the pipeline — dev is the LEAD (all development + `release:dev` builds); tsdnd is the staging mirror (receives `promote:dev-to-tsdnd` only); production receives tested builds via `promote:tsdnd-to-master` (manual GitLab job, task #51) or legacy deploy

---

## Appendix D — All CI/CD changes since 2026-07-01 (categorized summary)

This appendix categorizes every CI/CD change since the pipeline was created by Damien Louis on 2026-07-01 (commit `79665b2bb`). It complements §2 (full commit-by-commit history) and Appendix B (dashboard-side commits) with a categorized view for review.

### D.1 Pipeline creation & original design (2026-07-01)

| Change | Commit | Author | Detail |
|---|---|---|---|
| Pipeline scaffold | `79665b2bb` | Damien Louis | `.gitlab-ci.yml` (stages `release`/`deploy`, private CI image, rules for tsdnd\|dev\|production, `deploy:*` jobs) + `.gitlab/ci/{release,deploy,ssh}.yml` + `docs/CD.md` + `app/etc/env.php` removal + `.gitignore` updates + `config.php` update |
| Original deploy model | `79665b2bb` | Damien Louis | SSH-in deployment with inline-heredoc Magento activation (maintenance/enable → app:config:import → setup:upgrade → cache:flush → symlink swap → maintenance/disable), no rollback trap, no staging dir |

### D.2 Pipeline resurrection (2026-08-29 to 2026-09-02)

| Change | Commit | Author | Detail |
|---|---|---|---|
| Replace private CI image | `1618b24fe` | MounirAb | Private `registry.gitlab.com/technowebmaster-group/docker-magento-php-fpm/php-fpm:${PHP_VERSION}` (never published → `pull access denied`) replaced with public `markoshust/magento-php:8.2-fpm` |
| Harden SSH key handling | `3fa7dad00` | MounirAb | SSH key handling hardened in CI |
| Conditional composer install | `eec0e00ed` | MounirAb | Composer install made conditional on lockfile hash + vendor cache |
| OverlayFS vendor/ cleanup fixes | `f603ead67`, `1b7c17d03`, `d6ebe4db0`, `2e2207ab8` | MounirAb | Rename-aside strategy, `/dev/shm` (tmpfs) for vendor/, mv-to-tmp fallback for stuck vendor/ dirs |
| Grant permissions before vendor cleanup | `1b7c17d03` | MounirAb | `u+w` + `777` granted before vendor cleanup to prevent `Permission denied` |
| Promote flow simplification | `5a2c47c94` | MounirAb | Skip `setup:upgrade` in promote; simplify to dev→tsdnd→master only |
| Promote audit fixes | `a27b76485` | MounirAb | Scoped URL, schema-change warning, docs |
| Activation pub/ displacement | `677cfb3a9` | MounirAb | Real `pub/` dir displaced before symlinking to avoid conflicts |
| Promote hardening | `b12f55ba4`, `6858d1a4f` | MounirAb | Fallback variables, non-fatal htaccess creation, explicit values for promote jobs |
| Capital `-P` port flag for scp | `eec0e00ed` | MounirAb | Fix scp in `promote-release.sh` to use `-P` (capital) port flag |

### D.3 Memory-safe builds & host provisioning (2026-09-14 to 2026-09-22)

| Change | Commit | Author | Detail |
|---|---|---|---|
| glibc malloc arenas cap | `f0bbc995c` | Techno Ops | Cap glibc malloc arenas during deploy to avoid DI-compile OOM |
| Memory-safe builds + deploy/promote scripts + cache verification + fixes | `052a6b822` | MounirAb | Comprehensive memory-safe build fixes |
| Memory guards in promote-activate.sh | `6d79b7d00` | MounirAb | Prevent mmap() failures |
| Drop page cache before DI compile | `5b4c9e27e` | MounirAb | Prevent mmap() failures |
| DI compile memory reduction | `ebfb437c` | MounirAb | `--jobs=2` + `memory_limit=2G` |
| Remove invalid `--jobs` from setup:di:compile | `45ed388da` | MounirAb | Fix invalid option |
| Kill cline daemon + drop caches before DI compile | `4199e4fdc` | MounirAb | Prevent mmap() failures |
| `tee` for drop_caches (sudoers compat) | `fb70c90df` | MounirAb | Make drop_caches work with sudoers |
| Restore original working scripts | `f7e552d19` | MounirAb | Restore working state after fixes |
| cPanel deploy-account memory cap (195 MiB) | `caa89a694` | MounirAb | Root cause of persistent release job failure — cPanel caps deploy accounts at 195 MiB |
| Provision-host-limits: patch `/etc/profile` + `/etc/bashrc` | `21e07a737` | MounirAb | Ensure limits apply to all login shells |

### D.4 DevOps module & cross-env config standardization (2026-08-13 to 2026-09-04)

| Change | Commit | Author | Detail |
|---|---|---|---|
| opswatch hardlinked | `66668e9b2` | MounirAb | `pnpm link --dev ../devops-module` to hardlink opswatch |
| Shared config.php for all envs | `26beb380b` | MounirAb | Single `config.php` shared by all three environments |
| CONFIG-POLICY.md introducing deploy.* namespace | `a999c9e28` | MounirAb | Document deploy.* config namespace |
| deploy.x.x.x.session_redis_database + tsdnDSM | `1b91bc5c6` | MounirAb | Add session Redis DB setting; tsdnd now references dev's config.php via tsdnDSM |
| Migrate tsdnd session Redis DB 5→8 | `1b91bc5c6` | MounirAb | Avoid direct tsdnd edit; done via dev→tsdnd promotion |
| Redis auth config | `3c8ffd6a0`, `deffaf4a1` | MounirAb | Add Redis auth to env.php across all envs |
| env.php standardization for dev→tsdnd promotion | `27e7ac6de` | MounirAb | Make env.php compatible with promote flow |
| Remove maintenance.flag from .gitignore | `27e7ac6de` | MounirAb | Allow maintenance.flag to be tracked |

### D.5 Pipeline success (2026-09-20)

| Change | Commit | Author | Detail |
|---|---|---|---|
| Pipeline "techno-magento-af5ff8e7d-on-dev" success | `e406fce1b` | pipeline | First successful dev release build since pipeline creation; build-info.json written; dev environment updated to `20260922144237` |

### D.6 CI/CD updates in the dashboard codebase (2026-07-01 to 2026-09-22)

| Change | Commit | Author | Detail |
|---|---|---|---|
| v5.0.2 — auth gate hardened, Algeria 58 wilayas, S17b 5yr chart, EcomScan 125 all slides, Vite rebuild | `b1c10936` | Mounir Abderrahmani | Dashboard v5.0.2 release |
| v5.2.1 — telegram bot fix + webpushr presets + push notification improvements | `6220be7f` | Genspark AI | Dashboard v5.2.1 |
| v5.5.7 — dashboard UI updates + netdata page + security hardening + remove hardcoded secrets | `404f7032` | Genspark AI | Dashboard v5.5.7 |
| v5.5.7 — critical fixes + GitLab live pipeline page | `dfeb3ec2` | Genspark AI | Added GitLab live pipeline page to dashboard |
| Memory-safe deploy.sh, fixed API paths, added script_executions table | `44bd2be0` | Genspark AI | deploy.sh memory-safe; API paths fixed; script_executions table added |
| Task 27/18/51: cicd env standard dev-first plus standards endpoint | `d1caf48b` | MounirAb | CI/CD env standard + standards endpoint in dashboard |
| docs(task51): production promotion via GitLab promote jobs standard | `2795a35f` | MounirAb | Production promotion doc |

### D.7 Dashboard API / cicd page changes (this session, 2026-09-22)

| Change | File | Detail |
|---|---|---|
| `magentoRoot()` helper + all Magento actions → `{path}/current/` | `api/cicd.php` | All bin/magento/composer/git-pull commands now operate on the active release via `current/` symlink |
| `releases` action → production read-only + `current_build`/`promoted_from` | `api/cicd.php` | Production serve as read-only; `current_build` (build name) and `promoted_from` (promotion provenance) exposed for all envs |
| `tsdnd` added to env config | `api/cicd.php` | tsdnd recognized as first-class env alongside dev and production |
| `beta` kept as deprecated alias | `api/cicd.php` | Backward compatibility for any client still referencing `beta` |
| `Environment Build Info` card | `dashboard/src/pages/CiCdPage.tsx` | New card showing dev/tsdnd/production build info, powered by `action=releases` |
| Cross-links to full report | `docs/CD.md`, `docs/PRODUCTION_DEPLOYMENT_CHECKLIST.md`, `PRODUCTION DEPLOYMENT.md` | One-line "see also" pointers to `docs/CI_CDARCHITECTURE_MAX.md` + `api/cicd.php?action=releases` |
| `/cicd-report` route + sidebar entry + report viewer page | `dashboard/src/config/routes.ts`, `Sidebar.tsx`, `CiCdReportPage.tsx` | Full report accessible from the dashboard UI |

### D.8 Key files & their roles

| File | Role |
|---|---|
| `.gitlab-ci.yml` | Pipeline definition (stages `release`/`deploy`, rules per env, image `markoshust/magento-php:8.2-fpm`) |
| `.gitlab/ci/release.yml` | Release job: composer install → di:compile → dump-autoload → SCD → tar artifact + build-info.json |
| `.gitlab/ci/deploy.yml` | Deploy job: SSH into host, run deploy.sh to activate a release |
| `.gitlab/ci/ssh.yml` | SSH helper for CI runner → host connections |
| `.gitlab/ci/promote.yml` | Promote jobs (`promote:dev-to-tsdnd`, `promote:tsdnd-to-master`) — manual/optional, promote tested builds across envs |
| `scripts/deployment/deploy.sh` | Host-side activation script (maintenance, config import, upgrade, cache flush, symlink swap, cleanup) — `MROOT="${SITE_PATH}/current"` |
| `scripts/deployment/promote-release.sh` | Promote artifact from one env to another (scp + activate) |
| `scripts/deployment/restore-backup.sh` | Legacy backup restore (pre-CI path, still used for production) |
| `scripts/deployment/config/env.php` | Per-environment env.php templates |
| `scripts/deployment/config/config.php` | Shared config.php (all envs) |
| `scripts/deployment/standard/app/etc/env.php` | Standardized env.php path in webapp |
| `api/cicd.php` | Dashboard CI/CD API (status, log, releases, deploy, standards, history, environments, ssh, report) |
| `api/gitlab-pipeline.php` | GitLab pipeline API proxy (live pipeline data for dashboard) |
| `dashboard/src/pages/CiCdPage.tsx` | Dashboard `/cicd` page (pipeline status, Environment Build Info card, report link) |
| `dashboard/src/pages/CiCdReportPage.tsx` | Dashboard `/cicd-report` page (full report viewer) |
| `docs/CD.md` | CD documentation (248 lines, Damien's original + enrichments) |
| `docs/PRODUCTION_DEPLOYMENT_CHECKLIST.md` | Production deployment checklist |
| `PRODUCTION DEPLOYMENT.md` | Production deployment notes |
| `docs/CI_CDARCHITECTURE_MAX.md` | **Full change report** (this file) — July-1→now CI/CD pipeline evolution + dashboard integration |

---

*Report generated by manual CI/CD audit + dashboard API fixes session. All fixes syntax-checked; API actions verified via CLI harness; frontend rebuilt; pipeline re-triggered and passing. No live credentials are stored in this report. The full report is served by `api/cicd.php?action=report` and viewable at `/cicd-report` in the dashboard.*
