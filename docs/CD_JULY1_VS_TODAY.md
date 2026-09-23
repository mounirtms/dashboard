# CI/CD — July 1 vs Today: Full Comparison for the Meeting

> **Meeting brief — Damien's CI/CD presented 2026-07-01 vs the pipeline running today (2026-09-23).**
> Every addition, tuning, fix, runner-related change, script and command is documented with commit
> hashes so each point can be argued from evidence.
>
> **Sources:** `techno-magento` git history (`79665b2bb..af5ff8e7d`, 31 CI/CD commits), the live
> `.gitlab-ci.yml` + `.gitlab/ci/*.yml` + `scripts/ci/*` on branch `dev`, and the full report
> [`CI_CDARCHITECTURE_MAX.md`](./CI_CDARCHITECTURE_MAX.md).
> **Dashboard:** `/docs` (Reports & Docs) and `/cicd-comparison` ·
> API: `api/cicd.php?action=doc&file=docs/CD_JULY1_VS_TODAY.md`.

---

## 1. TL;DR — the argument in 10 lines

| # | Topic | July 1 (Damien) | Today (2026-09-23) |
|---|-------|-----------------|---------------------|
| 1 | CI image | Private `registry.gitlab.com/.../docker-magento-php-fpm` — **never published, every job died at `pull access denied`** | Public `markoshust/magento-php:8.2-fpm` (`1618b24fe`) |
| 2 | Stages | `release` → `deploy` | `release` → `deploy` → **`promote`** (new stage) |
| 3 | Environments | `tsdnd`, `dev`, `production` branches | `tsdnd`, `dev`, `master` (production branch deleted 2026-08-31, `d6a2dba85`) |
| 4 | Promotion | None — each env rebuilt independently | **build-once-promote**: `promote:dev-to-tsdnd` + `promote:tsdnd-to-master` (manual, `c67eff919`) |
| 5 | Deploy safety | Inline SSH heredoc, artifact in `/tmp`, **no rollback** | Private `.deploy-tmp/`, `activate-release.sh` with **rollback-on-failure trap** (`7865d70ab`) |
| 6 | Memory | No tuning → OOM on this host | `MALLOC_ARENA_MAX=2`, `PHP_CLI_MEMORY_LIMIT=4G`, drop-caches, cPanel 195 MiB cap fix (11 commits) |
| 7 | Release build | 5 linear commands, no validation | 90m timeout, `COMPOSER_AUTH` validation, overlayfs-safe vendor cache, `build-info.json` in artifact |
| 8 | Verification | `app:config:status` only | `verify-cache.sh` + `warmup-after-deploy.sh` + **`VERIFY_STRICT=1` session-leak guard on prod** |
| 9 | Runner | Tag `ded701-runner-production`, private image | **Same runner/tag — no re-registration, no `config.toml` change**; image swap + job-level memory guards only |
| 10 | Result | Pipeline could never go green (private image) | **First green pipeline `e406fce1b` on 2026-09-20**; dev artifact `20260922144237` |

---

## 2. What Damien presented on 2026-07-01 (commit `79665b2bb`)

Commit: `ci: GitLab CD pipeline (from 6725ae0f) on top of prod master 8aea9efe3` —
**10 files, 565 insertions(+), 444 deletions(−)**: created `.gitlab-ci.yml`,
`.gitlab/ci/{release,deploy,ssh}.yml`, `docs/CD.md` (248 lines); removed `app/etc/env.php`
(405 lines) from git; updated `.gitignore`, `README.md`, `app/etc/config.php`.

**Design as presented:**
- One branch = one environment; push builds the artifact; **nothing deploys without a manual click**.
- Release-based layout: timestamped `releases/`, persistent `shared/`, atomic `current` symlink,
  `KEEP_RELEASES=5` housekeeping — **this layout is still in use**, now identical across all 3 envs.
- Stages: `release` (automatic on 3 branches) → `deploy:<env>` (manual).

### 2.1 Original `.gitlab-ci.yml` (62 lines, verbatim)

```yaml
include:
  - local: '.gitlab/ci/ssh.yml'
  - local: '.gitlab/ci/release.yml'
  - local: '.gitlab/ci/deploy.yml'

stages:
  - release
  - deploy

default:
  image: registry.gitlab.com/technowebmaster-group/docker-magento-php-fpm/php-fpm:${PHP_VERSION}
  tags:
    - ded701-runner-production

variables:
  PHP_VERSION: "8.2"
  MAGE_MODE: "production"
  COMPOSER_FLAGS: "--no-dev --prefer-dist --no-interaction --no-progress"
  SC_LANGUAGES: "fr_FR en_US"
  SC_JOBS: "4"
  ARTIFACT: "release_${CI_PIPELINE_ID}.tar.gz"
  DEPLOY_PORT: "22"
  GIT_DEPTH: "10"
  KEEP_RELEASES: "5"   # releases kept on the server for housekeeping

# --- Release: builds the artifact automatically on any of the 3 branches -----
release:
  extends: .release
  rules:
    - if: '$CI_PIPELINE_SOURCE == "schedule"'
      when: never
    - if: '$CI_COMMIT_BRANCH == "tsdnd" || $CI_COMMIT_BRANCH == "dev" || $CI_COMMIT_BRANCH == "production"'

# --- env tsdnd ---------------------------------------------------------------
deploy:tsdnd:
  extends: .deploy
  environment: { name: tsdnd, url: $WEB_BASE_URL }
  rules: [{ if: '$CI_COMMIT_BRANCH == "tsdnd"', when: manual }]

# --- env dev -----------------------------------------------------------------
deploy:dev:
  extends: .deploy
  environment: { name: dev, url: $WEB_BASE_URL }
  rules: [{ if: '$CI_COMMIT_BRANCH == "dev"', when: manual }]

# --- env production ----------------------------------------------------------
deploy:production:
  extends: .deploy
  environment: { name: production, url: $WEB_BASE_URL }
  rules: [{ if: '$CI_COMMIT_BRANCH == "production"', when: manual }]
```

> **Fatal flaw:** the default image was never published — every job stopped at `pull access denied`.
> The pipeline as presented could not run at all.

### 2.2 Original `.gitlab/ci/release.yml` (32 lines, verbatim)

```yaml
.release:
  stage: release
  script:
    - composer install ${COMPOSER_FLAGS}
    - php bin/magento setup:di:compile --no-ansi
    - composer dump-autoload -o --apcu --no-dev
    - php bin/magento setup:static-content:deploy -f ${SC_LANGUAGES} -j ${SC_JOBS}
    - |
      # Write the archive OUTSIDE the tree being archived, otherwise tar reads the file it
      # is still writing and exits 1 with "file changed as we read it". Move it back after.
      tar -zcf "/tmp/${ARTIFACT}" \
        --exclude='./release_*.tar.gz' --exclude=./.git --exclude=./.gitlab \
        --exclude=./.gitlab-ci.yml --exclude=./docker --exclude='./docker-compose*.yml' \
        --exclude=./.env --exclude='./.env.*' --exclude='./.htaccess*' \
        --exclude=./auth.json --exclude=./app/etc/env.php --exclude=./pub/media \
        --exclude=./node_modules --exclude='./*.md' --exclude=./docs .
      mv "/tmp/${ARTIFACT}" "./${ARTIFACT}"
  artifacts:
    name: "${ARTIFACT}"
    expire_in: 1 week
    paths: ["${ARTIFACT}"]
```

Five linear commands. No timeout, no `interruptible: false`, no auth validation, no memory guards,
no vendor reuse, no `build-info.json`. **`di:compile` ran in `release` at this stage** (see §7.1).

### 2.3 Original `.gitlab/ci/deploy.yml` (40 lines, verbatim)

```yaml
.deploy:
  extends: .ssh_remote
  stage: deploy
  needs: ["release"]
  script:
    - ROOT="${DEPLOY_PATH}"
    - SHARED="${ROOT}/shared"
    - RELEASES="${ROOT}/releases"
    - RELEASE="${RELEASES}/$(date -u +%Y%m%d%H%M%S)"
    - scp -P "$DEPLOY_PORT" "$ARTIFACT" "${DEPLOY_USER}@${DEPLOY_HOST}:/tmp/${ARTIFACT}"
    - |
      $SSH bash -se <<REMOTE
        set -euo pipefail
        mkdir -p "$RELEASES" "$SHARED/app/etc" "$SHARED/pub/media" "$SHARED/var"
        test -f "$SHARED/app/etc/env.php" || { echo "FATAL: $SHARED/app/etc/env.php is missing, create it once by hand"; exit 1; }
        rm -rf "$RELEASE" && mkdir -p "$RELEASE"
        tar -xzf "/tmp/${ARTIFACT}" -C "$RELEASE" && rm -f "/tmp/${ARTIFACT}"
        find "$RELEASE" -type d -exec chmod 775 {} + ; find "$RELEASE" -type f -exec chmod 664 {} +
        chmod u+x "$RELEASE/bin/magento"
        ln -sfn "$SHARED/app/etc/env.php" "$RELEASE/app/etc/env.php"
        rm -rf "$RELEASE/pub/media" && ln -sfn "$SHARED/pub/media" "$RELEASE/pub/media"
        rm -rf "$RELEASE/var"       && ln -sfn "$SHARED/var"       "$RELEASE/var"
        cd "$RELEASE"
        php bin/magento maintenance:enable
        php bin/magento app:config:import -n
        php bin/magento setup:upgrade --keep-generated
        php bin/magento cache:flush
        ln -sfn "$RELEASE" "$ROOT/current"
        php bin/magento maintenance:disable
        cd "$RELEASES" && ls -1dt */ | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -rf
      REMOTE
    - $SSH "cd ${ROOT}/current && php bin/magento app:config:status"
```

**Gaps in the original deploy (all later fixed):** artifact staged in world-readable `/tmp`, no
private staging dir, **no rollback** — a failed `setup:upgrade` could leave `current` pointing at
broken code or maintenance mode stuck ON with no trap; no OPcache/Varnish purge, no cache
verification, no warmup, no resource pre-flight, no `deployed_version.txt` check, no
`build-info.json` provenance.

### 2.4 Original `.gitlab/ci/ssh.yml` (8 lines, verbatim)

```yaml
.ssh_remote:
  before_script:
    - mkdir -p ~/.ssh && chmod 700 ~/.ssh
    - echo "$DEPLOY_KEY" | tr -d '\r' > ~/.ssh/id_deploy && chmod 600 ~/.ssh/id_deploy
    - eval "$(ssh-agent -s)" && ssh-add ~/.ssh/id_deploy
    - ssh-keyscan -p "$DEPLOY_PORT" "$DEPLOY_HOST" >> ~/.ssh/known_hosts 2>/dev/null
  variables:
    SSH: "ssh -p ${DEPLOY_PORT} ${DEPLOY_USER}@${DEPLOY_HOST}"
```

Hardened later (`3fa7dad00`): `printf` + file-copy path, `ssh-keygen -y` validation of the key
before use, `known_hosts` hygiene.

---

## 3. What runs today (2026-09-23) — the same 4 files, transformed

### 3.1 `.gitlab-ci.yml` today — line-by-line diff

| Line / block | July 1 | Today | Commit |
|---|---|---|---|
| `include:` | ssh, release, deploy | + **`promote.yml`** | `c67eff919` |
| `stages:` | release, deploy | + **promote** | `c67eff919` |
| `default.image` | private `registry.gitlab.com/...php-fpm` (**broken**) | **`markoshust/magento-php:${PHP_VERSION}-fpm`** (public) | `1618b24fe` |
| `default.tags` | `ded701-runner-production` | `ded701-runner-production` (**unchanged**) | — |
| release rule branch | `tsdnd \|\| dev \|\| production` | `tsdnd \|\| dev \|\| master` | `d6a2dba85` |
| production job | `deploy:production` | **`deploy:master`** | `d6a2dba85` |
| deploy variables | none | per-env `VARNISH_DOMAIN`, `VERIFY_STRICT`, `WARMUP_MODE` | `052a6b822`, `b12f55ba4`, `6858d1a4f` |
| promote jobs | — | **`promote:dev-to-tsdnd`** + **`promote:tsdnd-to-master`** (manual, `interruptible: false`, 30m) | `c67eff919` + fixes |
| prod Varnish guard | — | `VARNISH_DOMAIN=technostationery.com`, `VERIFY_STRICT=1` | `a27b76485` |

The runner tag `ded701-runner-production` is the **only** `default:` field that survived
untouched — see §5 for the full runner analysis.

### 3.2 `release.yml` today — 5 commands became a hardened build (10,894 bytes vs 32 lines)

```yaml
.release:
  stage: release
  interruptible: false      # never ship a half-built tarball
  timeout: 90m              # composer + SCD are slow on this runner
  variables:
    COMPOSER_AUDIT_ABANDONED: "ignore"
    MALLOC_ARENA_MAX: "2"            # ← glibc arena cap: the #1 mmap() OOM fix
    COMPOSER_MEMORY_LIMIT: "-1"
  before_script:
    - test "$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" = "8.2"
    - php --version && composer --version
  script:
    # 1. COMPOSER_AUTH gate — verifies repo.magento.com + composer.bsscommerce.com
    #    creds exist WITHOUT printing secrets (clear fatal message if missing)
    # 2. remove magento/composer-dependency-version-audit-plugin (breaks CI)
    # 3. _wipe_vendor() — overlayfs-safe cleanup: if rm -rf fails on stale
    #    lower-layer inodes, rename() aside + background-delete, recreate
    # 4. lock-hash vendor cache — md5sum(composer.lock+json) →
    #    /tmp/composer_vendor_<hash>; reuse vendor/ when hash matches
    # 5. composer install / dump-autoload -o --no-dev, each step preceded by:
    #       export MALLOC_ARENA_MAX=2
    #       export PHP_CLI_MEMORY_LIMIT=4G
    #       export COMPOSER_MEMORY_LIMIT=-1
    # 6. setup:static-content:deploy -f --no-ansi -j ${SC_JOBS}
    #       (non-fatal fallback: "can be done on server via dashboard")
    # 7. write pub/static/.htaccess + deployed_version.txt fallback
    # 8. write build-info.json INTO the artifact:
    #       {pipeline_id, commit_sha, commit_short_sha, branch, built_at, artifact}
    # 9. tar with expanded excludes: .gitlab, patches, scripts, tests,
    #       app/code/Mab/*/Test, env.php, auth.json, media, node_modules
```

Every step above is an **addition** — July 1 had none of validation/caching/memory/provenance.

### 3.3 `deploy.yml` today — staged, verified, rollback-safe (2,904 bytes)

```
1. [PREFLIGHT]  SSH in; print ulimit DATA/RSS/nofile/nproc (host+user);
                run /usr/local/sbin/mab-ci-raise-limits --pid $$ --check
                (warns if session memory-capped → fix: provision-host-limits.sh)
                non-fatal here; activation script is the real gate
2. mkdir -p ${DEPLOY_PATH}/.deploy-tmp && chmod 700     ← private staging dir
3. scp artifact → .deploy-tmp/${ARTIFACT}               ← NOT /tmp anymore
4. scp scripts/ci/{activate-release.sh, verify-cache.sh, warmup-after-deploy.sh}
                → .deploy-tmp/                          ← shipped with the job
                (target account cannot read dashboard or repo)
5. SSH: chmod +x; run activate-release.sh with:
     ROOT, TARBALL, KEEP_RELEASES, WEB_BASE_URL, CI_SCRIPTS_DIR,
     VARNISH_DOMAIN, VARNISH_HOST, VERIFY_STRICT, WARMUP_MODE,
     RELEASE_LABEL='built by pipeline N from branch@sha'
   on failure: trap rolls back to previous release + disables maintenance
   then rm -f all four shipped files
6. $SSH "cd ${DEPLOY_PATH}/current && php bin/magento app:config:status"
```

`activate-release.sh` (12,581 bytes, webapp repo) runs the full sequence:
extract → permissions → shared symlinks → `maintenance:enable` → `app:config:import` →
`setup:upgrade --keep-generated` → `setup:di:compile --jobs=2` **on the target** → `cache:flush`
→ OPcache HTTP purge → `mab:cache:purge-all` → atomic symlink swap → `maintenance:disable` →
`verify-cache.sh` → `warmup-after-deploy.sh` → KEEP_RELEASES housekeeping — all under
`trap 'rollback' ERR` between maintenance:enable and maintenance:disable.

### 3.4 `promote.yml` — a stage that did not exist on July 1

Two manual jobs implementing **build-once, deploy-everywhere** (`c67eff919`):

```yaml
.promote:
  extends: .ssh_remote
  stage: promote
  needs: []
  interruptible: false    # never cancel between tar-extract and symlink swap
  timeout: 30m
  rules:
    - if: '$CI_PIPELINE_SOURCE == "schedule"'  # schedule must never auto-promote
      when: never
    - when: manual
  script:
    # promote-release.sh runs ON THE RUNNER: streams the source env's ACTIVE
    # release over SSH, re-packs excluding per-env symlinks, activates on the
    # target with the same rollback-safe sequence a fresh deploy uses, then
    # ships + runs the standard post-activation cache verification + warmup.
    - >-
      DEPLOY_HOST=… SOURCE_USER=… SOURCE_ROOT=… TARGET_USER=… TARGET_ROOT=…
      KEEP_RELEASES=5 VERIFY_STRICT=… VARNISH_DOMAIN=… DRY_RUN=${DRY_RUN:-0}
      bash scripts/ci/promote-release.sh

promote:dev-to-tsdnd:      # dev → tsdnd (isolated testing)
  PROMOTE_SOURCE_USER: dev  / DEPLOY_USER: tsdnd
  VARNISH_DOMAIN: ""  VERIFY_STRICT: "0"      # tsdnd is Apache-only → app checks

promote:tsdnd-to-master:   # tsdnd → master (production, dashboard task #51)
  PROMOTE_SOURCE_USER: tsdnd / DEPLOY_USER: technadminy7
  VARNISH_DOMAIN: technostationery.com  VERIFY_STRICT: "1"  # leak guard FAILS job
```

Hardening commits after creation: `6858d1a4f` (explicit fallbacks for every var),
`b12f55ba4` (non-fatal `.htaccess`), `5a2c47c94` (skip `setup:upgrade` on promote — schema
already migrated; simplify to dev→tsdnd→master only), `a27b76485` (scoped URL + schema-change
warning), `b970ba8a5` (capital `-P` for scp), `6d79b7d00` (memory guards in `promote-activate.sh`).

---

## 4. Complete commit timeline — every CI/CD change since July 1 (31 commits)

Full command: `git log --oneline 79665b2bb..HEAD -- .gitlab-ci.yml .gitlab/ scripts/ci/`

### Phase A — resurrection (2026-08-29 → 08-31): make the pipeline runnable at all

| Date | Commit | Change |
|---|---|---|
| 08-29 | `1618b24fe` | **use the known-working public CI image, not the never-published private one** |
| 08-29 | `3fa7dad00` | harden SSH key handling in `.ssh_remote` |
| 08-29 | `49f7a9396` | **harden release stage** — auth validation, Amasty self-heal, SCD retry (audited) |
| 08-29 | `7865d70ab` | **harden deploy stage** — private staging dir, upload-shell mitigation, **rollback-on-failure trap** (audited) |
| 08-31 | `d6a2dba85` | resolve dead `production` branch wiring (→ `master`) + Composer overlay-fs race |
| 08-31 | `c67eff919` | **add build-once-deploy-everywhere promotion pipeline**; fix Playwright test discovery |
| 08-31 | `5e081ea2f` | root cause of persistent release failure — **sudo required for vendor/ cleanup** |

### Phase B — overlayfs & vendor-cache saga (2026-09-01 → 09-02)

| Date | Commit | Change |
|---|---|---|
| 09-01 | `b970ba8a5` | capital `-P` port flag for scp in `promote-release.sh` |
| 09-01 | `eec0e00ed` | **conditional composer install** via lockfile hash + vendor cache |
| 09-01 | `1b7c17d03` | grant `u+w`/777 before vendor cleanup (Permission denied) |
| 09-01 | `91db40edd` | fix CI pipeline, Amasty Groupcat bug, captcha cron, register composer patches |
| 09-01 | `2e2207ab8` | **mv-to-tmp fallback** for overlayfs stuck vendor dirs |
| 09-01 | `d6ebe4db0` | use **/dev/shm (tmpfs)** for vendor during composer install |
| 09-01 | `f603ead67` | **rename-aside strategy** for overlayfs vendor cleanup (final form of `_wipe_vendor`) |
| 09-01 | `5a2c47c94` | promote: skip `setup:upgrade`, simplify to dev→tsdnd→master only |
| 09-02 | `a27b76485` | audit fixes for promote flow: scoped URL, schema-change warning, docs |
| 09-02 | `677cfb3a9` | activation: **displace real `pub/` dir before symlinking** |

### Phase C — promote hardening (2026-09-12 → 09-14)

| Date | Commit | Change |
|---|---|---|
| 09-12 | `b12f55ba4` | harden promote jobs: fallback variables + non-fatal `.htaccess` creation |
| 09-12 | `6858d1a4f` | provide explicit values and fallbacks for promote jobs |
| 09-14 | `f0bbc995c` | **cap glibc malloc arenas during deploy** to avoid DI-compile OOM (`MALLOC_ARENA_MAX=2`) |

### Phase D — memory-safe builds, the 195 MiB wall & host provisioning (2026-09-19 → 09-22)

| Date | Commit | Change |
|---|---|---|
| 09-19 | `052a6b822` | **memory-safe builds**: deploy/promote scripts, cache verification, and fixes (umbrella commit: `activate-release.sh`, `verify-cache.sh`, `warmup-after-deploy.sh`, `MALLOC_ARENA_MAX`, `PHP_CLI_MEMORY_LIMIT=4G`) |
| 09-21 | `6d79b7d00` | add memory guards to `promote-activate.sh` to prevent mmap() failures |
| 09-21 | `5b4c9e27e` | drop page cache before DI compile to prevent mmap() failures |
| 09-21 | `ebdfb437c` | reduce DI compile memory footprint (`--jobs=2`, `memory_limit=2G`) |
| 09-21 | `45ed388da` | remove invalid `--jobs` option from `setup:di:compile` (Magento 2.4.9 doesn't accept it there) |
| 09-21 | `4199e4fdc` | kill stray `cline` daemon + drop caches before DI compile |
| 09-21 | `fb70c90df` | use `tee` for `drop_caches` to work with sudoers |
| 09-22 | `f7e552d19` | restore original working scripts (revert churn from 4199/fb70) |
| 09-22 | **`caa89a694`** | **deploys died with mmap() ENOMEM — cPanel caps deploy accounts at 195 MiB (`RLIMIT_DATA`)** |
| 09-22 | **`21e07a737`** | `provision-host-limits.sh` must patch `/etc/profile` + `/etc/bashrc` too (raise limits for every new shell, not just CI sessions) |

**Result:** first fully green pipeline `e406fce1b` (`techno-magento-af5ff8e7d-on-dev`) on
**2026-09-20**, writing `build-info.json` and updating dev to release `20260922144237`.

---

## 5. GitLab Runner — what changed, what did NOT (meeting section)

**Direct answer: the runner itself was never reconfigured.** No runner re-registration, no
`config.toml` edit, no runner upgrade, no new runner host — verifiable from the fact that
`default.tags: [ded701-runner-production]` is byte-identical between `79665b2bb` (Jul 1) and
`af5ff8e7d` (today), and no commit in the 31-commit list touches runner configuration (runner
config lives outside the git repo, in the runner service's `config.toml`).

| Aspect | July 1 | Today | Kind of change |
|---|---|---|---|
| Runner tag | `ded701-runner-production` | `ded701-runner-production` | **None** |
| Runner host / registration | ded701 runner | same | **None** |
| Executor config (`config.toml`) | untouched | untouched | **None** |
| Container image (job-level) | private, never pullable | public `markoshust/magento-php:8.2-fpm` | **Job config** (`1618b24fe`) |
| Job memory behaviour | none | `MALLOC_ARENA_MAX=2`, `COMPOSER_MEMORY_LIMIT=-1`, `PHP_CLI_MEMORY_LIMIT=4G` | **Job config** (`f0bbc995c`, `052a6b822`) |
| Job timing/safety | no timeout flags | `timeout: 90m/30m`, `interruptible: false` on release/deploy/promote | **Job config** |
| Host-side limits (deploy target, not runner) | none | `mab-ci-raise-limits` + `provision-host-limits.sh` patching `/etc/profile` + `/etc/bashrc` | **Target host** (`caa89a694`, `21e07a737`) |
| Runner-side failure modes encountered | n/a (never ran) | overlayfs stuck `vendor/` dirs, glibc arena OOM, tmpfs sizing — all worked around **inside job scripts** | **Job scripts** (Phase B/D commits) |

**Argue this way in the meeting:** every "runner" problem we hit was solved without touching the
runner — either by fixing the job (image, env vars, script guards) or provisioning the *target*
host. If a true runner-side change is ever needed (bigger executor, cache config, concurrent
job limits), that is a `config.toml` change on ded701 and should be proposed separately —
candidate discussion item §8.9.

## 6. Scripts & commands inventory — July 1 vs today

### 6.1 Where the logic lived

| | July 1 | Today |
|---|---|---|
| Deploy logic | **inline heredoc inside `.gitlab/ci/deploy.yml`** — impossible to test outside a pipeline | **5 standalone scripts in the webapp repo**, shipped per-job: testable, reviewable, reusable by promote |
| Promote logic | did not exist | `promote-release.sh` (runner side) + `promote-activate.sh` (target side) |
| Verification | `app:config:status` one-liner | `verify-cache.sh` + `warmup-after-deploy.sh` |
| Host provisioning | none | `provision-host-limits.sh` + `/usr/local/sbin/mab-ci-raise-limits` |

### 6.2 `webapp/scripts/ci/` today (July 1 had **no** `scripts/ci/` directory)

| File | Size | Role | Key commands inside |
|---|---|---|---|
| `activate-release.sh` | 12,581 B | Full activation with rollback trap | `tar -xzf`, `maintenance:enable`, `app:config:import -n`, `setup:upgrade --keep-generated`, `setup:di:compile`, `cache:flush`, OPcache HTTP purge, `mab:cache:purge-all`, `ln -sfn` atomic swap, `maintenance:disable`, `trap … ERR → rollback` |
| `promote-release.sh` | 7,404 B | Runner-side: stream source env's active release → re-pack → deploy to target | `ssh`, `tar` (exclude per-env symlinks), `scp -P`, invokes target activation; supports `DRY_RUN=1` |
| `promote-activate.sh` | 10,100 B | Target-side activation used by promote | same activation core + memory guards (`6d79b7d00`) |
| `verify-cache.sh` | 6,171 B | Post-deploy cache/session verification | HTTP probes, `shared/cache-verify.json` write, session-leak check under `VERIFY_STRICT=1` |
| `warmup-after-deploy.sh` | 3,717 B | Post-deploy warmup | curls key pages (`/`, cart, checkout, login) to repopulate FPC |
| `provision-host-limits.sh` | 11,919 B | One-shot host provisioning (runs **on the target host**) | raises `RLIMIT_DATA`/`RLIMIT_AS` for deploy accounts, patches `/etc/profile` + `/etc/bashrc` (`21e07a737`), installs `mab-ci-raise-limits` helper |
| `host/` | dir | Helpers installed on hosts | `mab-ci-raise-limits` (`--check` used by `[PREFLIGHT]`) |

### 6.3 Commands added to the release path (none existed July 1)

```bash
# validation (49f7a9396)
test "$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" = "8.2"
php -r '<validate COMPOSER_AUTH JSON + required repos, never print secrets>'

# overlayfs-safe vendor cleanup (5e081ea2f → eec0e00ed → f603ead67, final form)
mv vendor "vendor.stale.$(date +%s).$$" && mkdir vendor   # rename-aside, then bg rm

# conditional install (eec0e00ed)
LOCK_HASH=$(md5sum composer.lock composer.json | md5sum | awk '{print $1}')
[ -f "/tmp/composer_vendor_${LOCK_HASH}/vendor/autoload.php" ] && cp -a … || composer install …

# memory guards on every heavy step (f0bbc995c, 052a6b822)
export MALLOC_ARENA_MAX=2
export PHP_CLI_MEMORY_LIMIT=4G
export COMPOSER_MEMORY_LIMIT=-1

# provenance (052a6b822)
cat > build-info.json << … pipeline_id/commit_sha/branch/built_at/artifact …
```

### 6.4 Commands added to the deploy path

```bash
# pre-flight (before artifact upload!)
$SSH 'echo "[PREFLIGHT] …ulimits…";
      sudo -n /usr/local/sbin/mab-ci-raise-limits --pid $$ --check || echo "WARN…"'

# private staging (7865d70ab)
REMOTE_TMP="${DEPLOY_PATH}/.deploy-tmp"; mkdir -p "$REMOTE_TMP"; chmod 700 "$REMOTE_TMP"
scp -P "$DEPLOY_PORT" "$ARTIFACT" scripts/ci/{activate,verify,warmup}-*.sh → "$REMOTE_TMP/"

# activation core (052a6b822+) — all under trap rollback
php bin/magento maintenance:enable && app:config:import && setup:upgrade \
  && setup:di:compile && cache:flush && <opcache purge> && mab:cache:purge-all \
  && ln -sfn "$RELEASE" "$ROOT/current" && maintenance:disable

# post-verify (052a6b822)
bash verify-cache.sh && bash warmup-after-deploy.sh
rm -f "$REMOTE_TMP"/{artifact,activate-release.sh,verify-cache.sh,warmup-after-deploy.sh}
```

## 7. Detailed analysis — additions, tunings, fixes

### 7.1 Design decisions that CHANGED (each one is a meeting debate)

| # | July 1 design | Today | Why it changed | Commit |
|---|---|---|---|---|
| 7.1.1 | `di:compile` in **release** (build-time) | `di:compile` in **deploy/activation** (target-time) | Release has no DB/env.php; DI must reflect the target env's enabled modules; compile moved next to maintenance window where `--jobs=2` + memory guards + rollback exist | `ebdfb437c`, `45ed388da`, `052a6b822` |
| 7.1.2 | branch `production` | branch `master` (`production` deleted) | dead branch wiring; `master` carries prod CI/CD vars (environment_scope) | `d6a2dba85` |
| 7.1.3 | each env builds its own artifact | **build-once-promote**: dev builds, promote ships same tree tsdnd→master | identical binaries across envs; promote strips only per-env symlinks when re-packing | `c67eff919`, `5a2c47c94` |
| 7.1.4 | `setup:upgrade` on every deploy incl. promote | **skipped on promote** (schema already migrated) | avoids double-migrating shared-schema envs; schema-change warning instead | `5a2c47c94`, `a27b76485` |
| 7.1.5 | deploy logic inline in heredoc | activate/verify/warmup are **shipped files** (scp per job, rm after) | target account can't read dashboard/repo; files travel with the job | `7865d70ab`, `052a6b822` |
| 7.1.6 | artifact to `/tmp` (world-readable) | artifact to **`.deploy-tmp/` (chmod 700)** | upload-shell mitigation, no artifact left in shared tmp | `7865d70ab` |
| 7.1.7 | release cancellable anytime | `interruptible: false` on release/deploy/promote | never cancel mid-build or between extract and symlink swap | `49f7a9396`, `7865d70ab`, `c67eff919` |
| 7.1.8 | SCD failure = job failure | SCD failure **non-fatal** with warning | runner memory ceiling; static content can be deployed server-side via dashboard | `052a6b822` |

### 7.2 ADDITIONS (things that simply did not exist on July 1)

1. **`promote` stage** + `promote.yml` + both manual promote jobs (`c67eff919` + fixes).
2. **`scripts/ci/` suite** — 6 scripts + `host/` helpers (§6.2), all new.
3. **`build-info.json`** inside the artifact → `current/build-info.json` in every env; feeds
   dashboard `api/cicd.php?action=releases` and `pub/cicd-status-mab.php`.
4. **`.promoted-from` provenance file** written by promote (release list shows "promoted from …").
5. **`[PREFLIGHT]` ulimit gate** + `mab-ci-raise-limits --check` (`caa89a694`).
6. **Host provisioning**: `provision-host-limits.sh` raising cPanel `RLIMIT_DATA` (195 MiB wall),
   patched into `/etc/profile` + `/etc/bashrc` (`21e07a737`).
7. **Cache/session verification** (`verify-cache.sh`) incl. `VERIFY_STRICT=1` session-leak guard
   on production that **fails the job** on leak.
8. **Warmup pass** (`warmup-after-deploy.sh`) after every deploy/promote.
9. **`COMPOSER_AUTH` validation** with non-printing fatal errors (`49f7a9396`).
10. **Vendor cache by lock hash** (`eec0e00ed`) + overlayfs rename-aside cleanup (`f603ead67`).
11. **Per-env job variables**: `VARNISH_DOMAIN`, `VARNISH_HOST`, `VERIFY_STRICT`, `WARMUP_MODE`.
12. **`DRY_RUN=1` mode** for promote (rehearse without activating).
13. **Dashboard integration**: `/cicd` live page, `/cicd-report` full report, `/docs` hub,
    `/cicd-comparison` (this document), API `action=standards|releases|report|docs|doc`.
14. **`stages: promote`** — the pipeline now has **3** stages, not 2 (often forgotten when
    counting "what's new").

### 7.3 TUNINGS (existed, but adjusted for this host)

1. `MALLOC_ARENA_MAX=2` everywhere heavy runs — glibc arena fragmentation was the #1 source of
   `mmap(): Cannot allocate memory` (`f0bbc995c`, `052a6b822`).
2. `PHP_CLI_MEMORY_LIMIT=4G` + `COMPOSER_MEMORY_LIMIT=-1` exports before each heavy step.
3. `timeout: 90m` release / `30m` deploy & promote (defaults too small for composer+SCD).
4. `setup:di:compile` footprint: `memory_limit=2G` + page-cache drop before compile, then
   **removal of the invalid `--jobs` flag** after Magento rejected it (`ebdfb437c` → `45ed388da`).
5. Composer: `COMPOSER_AUDIT_ABANDONED=ignore`; removed
   `magento/composer-dependency-version-audit-plugin` (broke non-interactive CI).
6. `SC_LANGUAGES`/`SC_JOBS` retained but SCD made resilient (retry then non-fatal fallback).
7. `KEEP_RELEASES: "5"` — value unchanged, enforcement moved into `activate-release.sh`.
8. `GIT_DEPTH: "10"`, `DEPLOY_PORT: "22"`, artifact naming/expiry — **unchanged** from Damien's
   original (credit where due: the variable block survived intact).

### 7.4 FIXES (broken → working, with failure symptom)

| Symptom | Root cause | Fix | Commit |
|---|---|---|---|
| Every job: `pull access denied` | private image never published | public `markoshust/magento-php:8.2-fpm` | `1618b24fe` |
| `deploy:production` never triggered / vars missing | `production` branch deleted; job wired to dead branch | rename job+rule to `master` | `d6a2dba85` |
| release fails `rm -rf vendor/` (Permission denied / stuck) | overlayfs lower-layer inode unlink | u+w/777 → mv-to-tmp → tmpfs → **rename-aside** (final) | `1b7c17d03`, `2e2207ab8`, `d6ebe4db0`, `f603ead67`, `5e081ea2f` |
| composer install ~10+ min every run | no cache | lock-hash vendor cache in `/tmp` | `eec0e00ed` |
| DI compile OOM / `mmap(): Cannot allocate memory` | glibc arenas + no memory caps | `MALLOC_ARENA_MAX=2` + memory exports + page-cache drop | `f0bbc995c`, `5b4c9e27e`, `4199e4fdc` |
| deploy dies even with CI guards | **cPanel 195 MiB `RLIMIT_DATA` on deploy accounts** | `provision-host-limits.sh` raises limits for all shells | `caa89a694`, `21e07a737` |
| failed upgrade left site broken / maintenance ON | no rollback | `trap 'rollback' ERR` between maintenance:enable/disable | `7865d70ab` |
| weak SSH key handling | raw `echo "$DEPLOY_KEY"`, no validation | `ssh-keygen -y` validation, printf, known_hosts hygiene | `3fa7dad00` |
| promote double-migrated schema / unclear var failures | promote copied full deploy flow | skip `setup:upgrade` on promote; explicit fallbacks everywhere | `5a2c47c94`, `6858d1a4f`, `b12f55ba4` |
| real `pub/` clobbered by symlink step | `ln -sfn` onto existing dir | displace real `pub/` before symlinking | `677cfb3a9` |
| promote scp used wrong port | lowercase `-p` vs scp's `-P` | capital `-P` | `b970ba8a5` |
| drop_caches failed under sudoers | direct write needs tty | `tee` wrapper | `fb70c90df` |
| cline-kill / drop-caches experiment broke scripts | over-aggressive guards | restore original working scripts | `f7e552d19` |

## 8. Discussion points for the meeting (argue these)

1. **Was `di:compile` in release (July 1) actually better?** Faster CI vs correctness per env.
   Today it runs in activation under maintenance — slower deploys, but rollback-protected.
   Motion: keep target-time compile? Or move back for pure-config-only releases?
2. **Build-once-promote vs rebuild-per-env.** Promote re-packs the source env's active release —
   is re-packing acceptable ("same tree") or should the original CI artifact be promoted instead?
   What breaks when source env has local uncommitted drift?
3. **`setup:upgrade` skipped on promote** — right for shared-schema topologies, but what happens
   when a promote is the *first* deploy to prod of a module needing data patches? Who confirms
   the warning was read? (Motion: require `VERIFY_STRICT`-style explicit var on schema-change
   promotes.)
4. **SCD non-fatal fallback** — if SCD OOMs in CI we ship the artifact anyway and rely on
   server-side SCD via dashboard. Is a release with stale static content acceptable for
   production? (Motion: make SCD fatal for `master`-branch releases only.)
5. **Runner: nothing was changed — should something be?** Candidates on ded701: raise
   `limit`/`output_limit`, enable distributed cache between runs, bump concurrent job count,
   pin a docker executor version. All `config.toml` changes — needs owner sign-off. (§5)
6. **cPanel 195 MiB wall** is patched via `provision-host-limits.sh` on deploy accounts —
   but that edits `/etc/profile` + `/etc/bashrc` server-wide for those users. Risk review:
   does raising `RLIMIT_DATA` for `tsdnd`/`technadminy7` conflict with any hosting policy?
7. **`VERIFY_STRICT=1` on production only** — should tsdnd also run strict (it's Apache-only,
   Varnish checks would need stubs)? Cost: promote:dev-to-tsdnd gets slower/fragile.
8. **Rollback coverage** — trap covers maintenance window only. Failures *after*
   `maintenance:disable` (verify/warmup) leave the new release live with a failed job red.
   Acceptable? (Motion: auto-rollback when verify fails on `master`.)
9. **`KEEP_RELEASES=5` housekeeping runs inside activation** — a promote that fails mid-way may
   still have pruned old releases. Should pruning move to a separate post-success step?
10. **Credit review** — the branch/env model, tar-outside-tree trick, shared/ layout,
    `KEEP_RELEASES`, artifact naming/expiry from Damien's July 1 design all survived unchanged
    and form the backbone of today's pipeline. Worth stating explicitly.

---

## 9. Appendix — file sizes then vs now

| File | July 1 | Today |
|---|---|---|
| `.gitlab-ci.yml` | 62 lines | ~100 lines, 3 stages |
| `.gitlab/ci/release.yml` | 32 lines | 10,894 B |
| `.gitlab/ci/deploy.yml` | 40 lines | 2,904 B |
| `.gitlab/ci/promote.yml` | — | 2,811 B |
| `.gitlab/ci/ssh.yml` | 8 lines | 556 B |
| `webapp/scripts/ci/` | — (dir absent) | 7 entries, ~52 KB total |
| `docs/CD.md` | 248 lines (new) | updated to 2026-09-22 dev-first standard |

**Full evidence:** `git show 79665b2bb` (original), `git log 79665b2bb..af5ff8e7d --
.gitlab-ci.yml .gitlab/ scripts/ci/` (31 commits), dashboard `/cicd-report`
(`docs/CI_CDARCHITECTURE_MAX.md`), this file at `/cicd-comparison`.

*Generated 2026-09-23 for the Damien-CI/CD review meeting. Owner: mounirAb.*









