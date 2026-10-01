<?php
/**
 * CI/CD Pipeline API Service
 *
 * ENVIRONMENT STANDARD (2026-09-22 audit — dev-first, build-once-promote):
 *   dev   = /home/dev/public_html          (LEAD — all development happens here,
 *           branch `dev`, always ahead of tsdnd/master; ONLY env with a git repo)
 *   tsdnd = /home/tsdnd/public_html        (staging mirror — receives dev builds
 *           ONLY via GitLab promote:dev-to-tsdnd, never by direct code edits)
 *   prod  = /home/technadminy7/public_html (production — receives tested builds
 *           ONLY via GitLab promote:tsdnd-to-master, never by direct code edits)
 *
 * All three environments share the SAME folder standard:
 *   current -> releases/<timestamp>/   (atomic symlink cutover)
 *   pub     -> current/pub              (Apache docroot target)
 *   shared/{app/etc/env.php,pub/media,var}  (per-env state, symlinked in)
 *   releases/  (last KEEP_RELEASES=5 kept)   .deploy-tmp/ (empty unless a job runs)
 *
 * This API exposes read-only status helpers for dev/tsdnd (+ blocked prod) and
 * job runners for NON-PRODUCTION environments only. PRODUCTION
 * (technadminy7) is NEVER touched by the runners — production promotion goes
 * through the GitLab `promote:tsdnd-to-master` manual job (see docs/CD.md in
 * the techno-magento repo). Track that work in dashboard task #51.
 *
 * Operations:
 * - Build (clean, compile, deploy static content)
 * - Flush caches (Redis, Magento, Varnish, OPcache)
 * - Test runners (HTTP checks, log analysis, module status)
 * - Database migrations (sync structure/data between tsdnd/dev)
 * - Code migrations (rsync app/code between environments)
 * - Module management (enable/disable on tsdnd/dev)
 * - Reindex, health checks
 * - standards (this file's canonical env/folder standard as JSON for the UI)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Security: Only allow authenticated users ──
require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/config.php';
Config::load();
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// DB credentials from .env (never hardcoded)
$dbPass = Config::get('db.pass', '');

// ── Environment Configuration ──
// STANDARD: dev is the LEAD environment (all development + git repo live here).
// tsdnd is the staging mirror (receives dev builds via GitLab promotion only).
// There is no `beta` environment on this host — the legacy `beta` key is kept
// as a deprecated alias of tsdnd so old callers fail soft, not hard.
$ENVIRONMENTS = [
    'dev' => [
        'name' => 'Development (LEAD — all changes start here)',
        'path' => '/home/dev/public_html',
        'user' => 'dev',
        'db' => 'dev_dBT8x12y22',
        'url' => 'https://dev.technostationery.com',
        'redis_dbs' => [5, 6, 7],
        'allowed' => true,
        'role' => 'lead',
        'branch' => 'dev',
    ],
    'tsdnd' => [
        'name' => 'Staging mirror (promoted dev builds only)',
        'path' => '/home/tsdnd/public_html',
        'user' => 'tsdnd',
        'db' => 'tsdnd_dBT8x12y22',
        'url' => 'https://tsdnd.technostationery.com',
        'redis_dbs' => [3, 4, 8],
        'allowed' => true,
        'role' => 'staging',
        'branch' => 'tsdnd',
    ],
    // DEPRECATED alias: no /home/beta exists on this host. Resolves to tsdnd
    // so legacy callers keep working while they are migrated to 'tsdnd'.
    'beta' => [
        'name' => 'DEPRECATED alias of tsdnd (use tsdnd)',
        'path' => '/home/tsdnd/public_html',
        'user' => 'tsdnd',
        'db' => 'tsdnd_dBT8x12y22',
        'url' => 'https://tsdnd.technostationery.com',
        'redis_dbs' => [3, 4, 8],
        'allowed' => true,
        'role' => 'staging',
        'deprecated_alias_of' => 'tsdnd',
    ],
];

// PRODUCTION is EXPLICITLY excluded
$PRODUCTION = [
    'path' => '/home/technadminy7/public_html',
    'user' => 'technadminy7',
];

$PHP_BIN = '/opt/cpanel/ea-php82/root/usr/bin/php';
$LOG_DIR = '/home/dashboard/public_html/logs/cicd';
$PID_DIR = '/home/dashboard/public_html/tmp/cicd';

@mkdir($LOG_DIR, 0755, true);
@mkdir($PID_DIR, 0755, true);

// ── Helper Functions ──

function getEnvConfig(string $env): ?array {
    global $ENVIRONMENTS;
    return $ENVIRONMENTS[$env] ?? null;
}

/**
 * Resolve the ACTIVE Magento root for a release-based environment.
 * Standard layout: $path/current -> releases/<ts>/. All bin/magento commands
 * must run from there — the legacy flat-install bin/ dirs still sitting at the
 * docroot root of tsdnd/production are STALE (pre-rebuild) and must never be
 * executed; dev has no root bin/ at all. Falls back to $path for a hypothetical
 * legacy flat install that still has bin/magento inside current AND at root.
 */
function magentoRoot(array $config): string {
    $path = rtrim($config['path'] ?? '', '/');
    if ($path !== '' && is_file("{$path}/current/bin/magento")) return "{$path}/current";
    return $path; // legacy flat layout (bin/magento directly at root)
}

function isProduction(string $env): bool {
    return in_array($env, ['prod', 'production', 'technadminy7']);
}

function generateJobId(): string {
    return 'cicd_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6);
}

function writeJobStatus(string $jobId, array $status): void {
    $file = "/home/dashboard/public_html/tmp/cicd/{$jobId}.json";
    $status['timestamp'] = date('Y-m-d H:i:s');
    @file_put_contents($file, json_encode($status, JSON_PRETTY_PRINT), LOCK_EX);
}

function readJobStatus(string $jobId): ?array {
    $file = "/home/dashboard/public_html/tmp/cicd/{$jobId}.json";
    if (!file_exists($file)) return null;
    return json_decode(file_get_contents($file), true);
}

function getRecentJobs(int $limit = 20): array {
    $files = glob("/home/dashboard/public_html/tmp/cicd/cicd_*.json");
    if (!$files) return [];
    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
    $jobs = [];
    foreach (array_slice($files, 0, $limit) as $f) {
        $data = json_decode(file_get_contents($f), true);
        if ($data) $jobs[] = $data;
    }
    return $jobs;
}

/**
 * Run a shell command and collect its output.
 *
 * The dashboard FPM pool sets disable_functions = exec,passthru,shell_exec,system
 * (deliberate hardening), so those four functions do not exist and calling them
 * throws "Call to undefined function ..." -> HTTP 500 on every local job action.
 * proc_open is NOT disabled and is what the rest of this codebase already uses
 * (see MonitorApi::cmd), so it is the portable choice here.
 *
 * @return array{lines: string[], code: int}
 */
function shellCapture(string $command, int $timeout = 120): array
{
    if (!function_exists('proc_open')) {
        return ['lines' => ['ERROR: proc_open unavailable on this SAPI'], 'code' => 127];
    }
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open($command, $descriptors, $pipes);
    if (!is_resource($proc)) {
        return ['lines' => ['ERROR: could not start command'], 'code' => 127];
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $deadline = time() + $timeout;
    $status = ['running' => true, 'exitcode' => -1];
    while (true) {
        $stdout .= (string)stream_get_contents($pipes[1]);
        $stderr .= (string)stream_get_contents($pipes[2]);
        $status = proc_get_status($proc);
        if (!$status['running']) break;
        if (time() > $deadline) {
            proc_terminate($proc, 9);
            break;
        }
        usleep(50000);
    }
    $stdout .= (string)stream_get_contents($pipes[1]);
    $stderr .= (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);

    $text = trim($stdout . ($stderr !== '' ? ($stdout !== '' ? "\n" : '') . $stderr : ''));
    $lines = ($text === '') ? [] : preg_split('/\r?\n/', $text);

    return ['lines' => $lines, 'code' => (int)$exit];
}

/**
 * Start a background job writing all output to $logFile.
 *
 * Same contract as the previous shell_exec version - records the child pid so
 * isJobRunning()/killJob() keep working; only the spawning call changed.
 * setsid detaches the child from the FPM worker's process group so it survives
 * the request that started it.
 */
function execAsync(string $command, string $jobId, string $logFile): void
{
    $pidFile = "/home/dashboard/public_html/tmp/cicd/{$jobId}.pid";
    @mkdir(dirname($logFile), 0755, true);
    @mkdir(dirname($pidFile), 0755, true);

    if (!function_exists('proc_open')) {
        @file_put_contents($logFile, "ERROR: proc_open unavailable - cannot start job\n");
        writeJobStatus($jobId, ['status' => 'failed', 'message' => 'proc_open unavailable']);
        return;
    }

    // The redirect MUST sit outside the inner sh -c, attached to setsid itself.
    // Appending ">> log" inside the inner string would bind only to the LAST
    // command of a compound list ("echo A && echo B >> log"), silently dropping
    // everything written before it. Wrapping the whole thing (as the previous
    // shell_exec version did with braces) captures all output.
    $cmd = 'setsid /bin/sh -c ' . escapeshellarg($command)
         . ' >> ' . escapeshellarg($logFile) . ' 2>&1 & echo $!';
    $res = shellCapture($cmd, 15);
    $pid = trim((string)($res['lines'][0] ?? ''));

    // Touch the log so the UI has a file to read even before the first flush.
    if (!file_exists($logFile)) {
        @file_put_contents($logFile, "Starting: {$command}\n\n");
    }

    if ($pid !== '' && ctype_digit($pid)) {
        @file_put_contents($pidFile, $pid);
    } else {
        @file_put_contents($logFile, "ERROR: could not start background job\n", FILE_APPEND);
        writeJobStatus($jobId, ['status' => 'failed', 'message' => 'Could not start background job']);
    }
}

function isJobRunning(string $jobId): bool {
    $pidFile = "/home/dashboard/public_html/tmp/cicd/{$jobId}.pid";
    if (!file_exists($pidFile)) return false;
    $pid = (int)trim((string)file_get_contents($pidFile));
    if ($pid <= 0) return false;
    // Signal 0 only probes for existence, so no shell is needed.
    if (function_exists('posix_kill')) return @posix_kill($pid, 0);
    return shellCapture('ps -p ' . escapeshellarg((string)$pid), 10)['code'] === 0;
}

function killJob(string $jobId): bool {
    $pidFile = "/home/dashboard/public_html/tmp/cicd/{$jobId}.pid";
    if (!file_exists($pidFile)) return false;
    $pid = (int)trim((string)file_get_contents($pidFile));
    if ($pid <= 0) return false;
    if (function_exists('posix_kill')) {
        @posix_kill($pid, 15);
        usleep(300000);
        @posix_kill($pid, 9);
    } else {
        shellCapture('kill ' . escapeshellarg((string)$pid) . ' 2>/dev/null', 5);
        shellCapture('kill -9 ' . escapeshellarg((string)$pid) . ' 2>/dev/null', 5);
    }
    @unlink($pidFile);
    writeJobStatus($jobId, ['status' => 'cancelled', 'message' => 'Job cancelled by user']);
    return true;
}

function getJobLog(string $jobId, int $lines = 50): array {
    $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
    if (!file_exists($logFile)) return ['lines' => [], 'total' => 0];
    // Pure-PHP tail: tail/wc needed exec(), which this pool disables.
    $contents = @file_get_contents($logFile);
    if ($contents === false) return ['lines' => [], 'total' => 0];
    $all   = preg_split('/\r?\n/', rtrim($contents, "\r\n"));
    $total = count($all);
    return ['lines' => array_slice($all, -max(1, $lines)), 'total' => $total];
}

function runMagentoCommand(string $env, string $command, string $jobId): string {
    $config = getEnvConfig($env);
    if (!$config) return "ERROR: Invalid environment";
    
    $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
    $mroot = magentoRoot($config);
    $fullCmd = "cd {$mroot} && {$PHP_BIN} bin/magento {$command} 2>&1";
    execAsync($fullCmd, $jobId, $logFile);
        return "Command started: {$command}";
}

/**
 * Fetch the live build/release info for an environment over HTTP.
 *
 * The dashboard user is filesystem-isolated from the per-env cPanel accounts
 * (dev/tsdnd/prod), so it cannot `file_get_contents` a release's build-info.json
 * directly (Permission denied). Now that pub/build-info.php serves the same
 * build facts publicly (no token, Cache-Control: no-store) this API simply
 * polls that endpoint — it always reflects whichever release symlink is
 * currently active, immune to opcache/Varnish staleness (no-store). Local file
 * read is the fallback for environments that don't expose the public endpoint
 * (e.g. production, which is read-only and promoted only via GitLab).
 *
 * Returns the public endpoint's shape: {environment,current_release,checked_at,
 *   cache_verify, releases:[{id,is_current,build_info,promoted_from}]} or null.
 */
function fetchBuildInfo(string $envUrl, ?string $envPath = null): ?array
{
    $data = null;

    if ($envUrl !== '') {
        $url = rtrim($envUrl, '/') . '/build-info.php';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            ]);
            $raw  = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($code === 200 && $raw !== false && $err === '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }

        // Fallback to fopen-based HTTP if the curl extension is unavailable.
        if (!$data) {
            $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 15, 'ignore_errors' => true]]);
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }
    }

    // Local FS fallback (works only when perms allow; otherwise stays null).
    if (!$data && $envPath) {
        $root = rtrim($envPath, '/');
        $current = @readlink($root . '/current');
        $currentId = $current ? basename($current) : null;
        $releases = [];
        $currentBuild = null;
        $promotedFrom = null;
        $releasesDir = $root . '/releases';
        if (is_dir($releasesDir)) {
            foreach (scandir($releasesDir) as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $p = $releasesDir . '/' . $entry;
                if (!is_dir($p)) continue;
                $bi = null;
                if (is_file($p . '/build-info.json')) {
                    $raw = @file_get_contents($p . '/build-info.json');
                    if ($raw !== false) {
                        $d = json_decode($raw, true);
                        if (is_array($d)) $bi = $d;
                    }
                }
                $isCurrent = $entry === $currentId;
                if ($isCurrent) {
                    $currentBuild = $bi;
                    $pf = @file_get_contents($p . '/.promoted-from');
                    $promotedFrom = ($pf !== false && trim($pf) !== '') ? trim($pf) : null;
                }
                $releases[] = ['id' => $entry, 'is_current' => $isCurrent, 'build_info' => $bi, 'promoted_from' => $promotedFrom];
            }
            usort($releases, function ($a, $b) { return strcmp((string) $b['id'], (string) $a['id']); });
        }
        if ($releases || $currentId) {
            $data = [
                'environment'  => basename(rtrim($root, '/')),
                'current_release' => $currentId,
                'checked_at'   => null,
                'cache_verify' => null,
                'releases'     => $releases,
                '_local'       => true,
            ];
            // Stash current build/promoted_from on the env payload for parity.
            $data['current_build']    = $currentBuild;
            $data['promoted_from']    = $promotedFrom;
        }
    }

    return $data;
}



// ── Route Actions ──

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'environments':
        // The deprecated `beta` alias is server-side only — the UI lists the
        // three canonical environments (dev lead / tsdnd staging / prod blocked).
        $visible = array_filter($ENVIRONMENTS, function ($key) {
            return $key !== 'beta';
        }, ARRAY_FILTER_USE_KEY);
        echo json_encode(['success' => true, 'environments' => $visible]);
        break;

    case 'standards':
        // Canonical environment + folder standard for the /cicd dashboard page.
        // Lets the UI document the dev-first rule and the identical folder
        // layout without hardcoding it in the frontend bundle.
        $visible = array_filter($ENVIRONMENTS, function ($key) {
            return $key !== 'beta';
        }, ARRAY_FILTER_USE_KEY);
        echo json_encode(['success' => true, 'standard' => [
            'policy' => 'dev-first: all development in /home/dev/public_html/webapp (branch dev, always ahead); tsdnd receives dev builds via GitLab promote:dev-to-tsdnd; production via promote:tsdnd-to-master (dashboard task #51). Direct edits on tsdnd/production are prohibited.',
            'folders' => [
                'current' => 'current -> releases/<timestamp>/ (atomic symlink cutover)',
                'pub' => 'pub -> current/pub (Apache docroot target)',
                'shared' => 'shared/{app/etc/env.php,pub/media,var} (per-env state, symlinked into each release)',
                'releases' => 'releases/ keeps last KEEP_RELEASES=5; build-info.json + .promoted-from record provenance',
                'deploy_tmp' => '.deploy-tmp/ empty unless a deploy/promote job is running',
            ],
            'known_drift_2026_09_22' => [
                'prod_pub_htaccess_differs_by_design' => 'production sits behind Varnish (.dz redirect + /sysadminy + proxy rules); dev/tsdnd share the Apache-direct variant',
                'prod_shared_has_no_opcache_token' => 'FIXED 2026-09-22 — shared/opcache-purge-token created on production (64-hex, technadminy7-owned)',
                'redis_db5_collision' => 'FIXED 2026-09-22 — tsdnd sessions renumbered Redis db5 -> db8 (env.php.bak-20260922-redisfix); db5 is now exclusively the dev cache frontend',
            ],
            'fixes_2026_09_22' => [
                'mmap_ENOMEM_root_cause' => 'cPanel Limit Protections capped deploy shells at ~195 MiB (ulimit -d/-m 200000); setup:di:compile peaks ~390 MiB — fixed via provision-host-limits.sh + ensure_build_limits() fail-fast gate',
                'tmp_cleaned' => 'stale Sep-01 activate-release.sh removed from tsdnd .deploy-tmp; incomplete 20260919142334 junk release removed from dev releases/',
            ],
            'environments' => $visible,
            'production' => $PRODUCTION + ['note' => 'read-only from this API; promotion only via GitLab manual job'],
        ]], JSON_PRETTY_PRINT);
        break;

    case 'rollback':
        // Cut an environment back to a previously deployed release by
        // atomically repointing the `current` symlink (the same mechanism the
        // deploy/promote jobs use), then flushing caches so the storefront
        // never serves a mix of two releases.
        //
        // Deliberately NOT exposed for production: prod changes must flow
        // through the GitLab promote:tsdnd-to-master manual job so they stay
        // auditable. Rolling prod back by symlink would silently bypass that.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Rollback requires POST']);
            break;
        }

        // Destructive action: restrict to admins/editors. A viewer who can log
        // in must not be able to repoint a live environment.
        $role = strtolower((string)($_SESSION['role'] ?? 'viewer'));
        if (!in_array($role, ['admin', 'editor'], true)) {
            http_response_code(403);
            echo json_encode(['error' => 'Rollback requires admin or editor role']);
            break;
        }

        $envKey    = $_POST['env'] ?? '';
        $releaseId = trim($_POST['release'] ?? '');
        $confirm   = ($_POST['confirm'] ?? '') === 'ROLLBACK';

        if (!in_array($envKey, ['dev', 'tsdnd'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Rollback is only available for dev and tsdnd. Production rollbacks must run through the GitLab promote:tsdnd-to-master job.']);
            break;
        }
        if (!preg_match('/^\d{8,14}$/', $releaseId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid release id']);
            break;
        }
        if (!$confirm) {
            http_response_code(400);
            echo json_encode(['error' => 'Confirmation required']);
            break;
        }

        $root        = rtrim($ENVIRONMENTS[$envKey]['path'] ?? '', '/');
        $target      = "$root/releases/$releaseId";
        $currentLink = "$root/current";
        if ($root === '' || !is_dir($target)) {
            http_response_code(404);
            echo json_encode(['error' => "Release $releaseId not found for $envKey"]);
            break;
        }

        $liveTarget = @readlink($currentLink);
        if ($liveTarget && basename($liveTarget) === $releaseId) {
            http_response_code(400);
            echo json_encode(['error' => 'That release is already live']);
            break;
        }
        $previous = $liveTarget ? basename($liveTarget) : null;

        // Atomic symlink swap: symlink to a temp name, then rename over the live
        // link (rename is atomic, so the site never sees a missing `current`).
        // Done in-process rather than by shelling out to a second php -r: exec()
        // is disabled on this FPM pool.
        $tmpLink = $currentLink . '.rb.' . getmypid();
        if (!@symlink($target, $tmpLink)) {
            http_response_code(500);
            echo json_encode(['error' => 'Rollback failed: could not create temporary symlink']);
            break;
        }
        if (!@rename($tmpLink, $currentLink)) {
            @unlink($tmpLink);
            http_response_code(500);
            echo json_encode(['error' => 'Rollback failed: could not swap the current symlink']);
            break;
        }

        // Flush Magento caches inside the restored release so no generated
        // code or config cache from the newer release survives the cutover.
        if (is_file("$target/bin/magento")) {
            shellCapture(sprintf('cd %s && %s -d memory_limit=-1 bin/magento cache:flush 2>&1 | tail -2',
                escapeshellarg($target), escapeshellarg($PHP_BIN)), 300);
        }

        echo json_encode([
            'success'      => true,
            'env'          => $envKey,
            'release'      => $releaseId,
            'previous'     => $previous,
            'message'      => 'Rolled back ' . $envKey . ' from ' . ($previous ?: 'unknown') . ' to ' . $releaseId,
            'cache_flush'  => $clean ? trim(implode(' | ', $clean)) : 'skipped (no bin/magento in release)',
        ]);
        break;

    case 'releases':
        // Per-environment build info for the /cicd page. Includes PRODUCTION
        // as a READ-ONLY target (file reads only — no commands are executed
        // against it; production changes still flow exclusively through the
        // GitLab promote:tsdnd-to-master job). The deprecated `beta` alias is
        // not listed.
        $targets = [];
        foreach (['dev', 'tsdnd'] as $k) {
            if (isset($ENVIRONMENTS[$k])) $targets[$k] = $ENVIRONMENTS[$k];
        }
        $targets['production'] = [
            'name' => 'Production (READ-ONLY — promote via GitLab)',
            'path' => $PRODUCTION['path'],
            'url'  => 'https://technostationery.com',
            'read_only' => true,
        ];
                $out = [];
        foreach ($targets as $key => $env) {
            $root  = $env['path'];
            $live  = fetchBuildInfo($env['url'] ?? '', $root);
            $releases = [];
            $currentBuild = $promotedFrom = $currentId = null;
            if (is_array($live)) {
                $currentId = $live['current_release'] ?? null;
                foreach (($live['releases'] ?? []) as $r) {
                    $releases[] = [
                        'id' => $r['id'] ?? null,
                        'is_current' => !empty($r['is_current']),
                        'build_info' => $r['build_info'] ?? null,
                        'promoted_from' => $r['promoted_from'] ?? null,
                    ];
                    if (!empty($r['is_current'])) {
                        $currentBuild  = $r['build_info'] ?? null;
                        $promotedFrom  = $r['promoted_from'] ?? null;
                    }
                }
                // FS-fallback shape stashes these directly on the payload.
                if (isset($live['current_build'])) {
                    $currentBuild = $live['current_build'];
                    $promotedFrom = $live['promoted_from'] ?? $promotedFrom;
                }
                usort($releases, function ($a, $b) { return strcmp((string) $b['id'], (string) $a['id']); });
            }
            $out[$key] = [
                'env' => $key,
                'name' => $env['name'],
                'url' => $env['url'] ?? null,
                'root' => $root,
                'read_only' => !empty($env['read_only']),
                'current_release' => $currentId,
                'current_build' => $currentBuild,
                'promoted_from' => $promotedFrom,
                'release_count' => count($releases),
                'releases' => $releases,
                'last_release' => reset($releases) ?: null,
                'last_checked' => $live['checked_at'] ?? null,
                'cache_verify' => $live['cache_verify'] ?? null,
                'source' => !empty($live['_local']) ? 'local' : 'http',
            ];
        }
        echo json_encode(['success' => true, 'releases' => $out], JSON_PRETTY_PRINT);
        break;

    case 'report': {
        // Full CI/CD change report (docs/CI_CDARCHITECTURE_MAX.md) served as HTML
        // for the /cicd-report dashboard page.
        function _md_inline(string $text): string {
            $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
            $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
            $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text);
            $text = preg_replace('/\~\~([^\~]+)\~\~/', '<del>$1</del>', $text);
            $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);
            return $text;
        }
        function _md_to_html(string $md): string {
            $md = str_replace(["\r\n", "\r"], "\n", $md);
            $blocks = [];
            while (preg_match('/```(\w*)\n(.*?)```/s', $md, $m)) {
                $ph = "\n__CB" . count($blocks) . "__\n";
                $lang = $m[1] ? " class=\"language-{$m[1]}\"" : '';
                $blocks[$ph] = '<pre><code' . $lang . '>' . htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8') . '</code></pre>';
                $md = preg_replace('/```(\w*)\n(.*?)```/s', $ph, $md, 1);
            }
            $md = preg_replace_callback('/^\|(.+)\|\s*$\n^\|[\s\-|:]+\|\s*$\n((?:^\|.+\|\s*$\n?)+)/m', function($m) {
                $header = array_map('trim', explode('|', $m[1]));
                $header = array_filter($header, fn($h) => $h !== '');
                preg_match_all('/^\|.+\|\s*$/', $m[2], $dm);
                $rows = [];
                foreach ($dm[0] as $line) {
                    $cells = array_map('trim', explode('|', $line));
                    $cells = array_filter($cells, fn($c) => $c !== '');
                    if (count($cells) === count($header)) $rows[] = $cells;
                }
                $out = "<table>\n<thead><tr>";
                foreach ($header as $h) $out .= "<th>" . _md_inline($h) . "</th>";
                $out .= "</tr></thead>\n<tbody>\n";
                foreach ($rows as $row) {
                    $out .= "<tr>";
                    foreach ($row as $c) $out .= "<td>" . _md_inline($c) . "</td>";
                    $out .= "</tr>\n";
                }
                return $out . "</tbody>\n</table>\n";
            }, $md);
            $md = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $md);
            $md = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $md);
            $md = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $md);
            $md = preg_replace('/^(?:---|\*\*\*|___)$/m', '<hr>', $md);
            $md = preg_replace_callback('/^(?:   )?(?:[-*]) (.+)$/m', fn($m) => "<li>" . _md_inline($m[1]) . "</li>", $md);
            $md = preg_replace('/((?:<li>.*<\/li>\n?)+)/', '<ul>$1</ul>', $md);
            $md = preg_replace_callback('/^(?:   )?(\d+)\. (.+)$/m', fn($m) => "<li>" . _md_inline($m[2]) . "</li>", $md);
            $md = preg_replace('/((?:<li>.*<\/li>\n?)+)/', '<ol>$1</ol>', $md);
            $lines = explode("\n", $md);
            $out = '';
            $buf = '';
            foreach ($lines as $line) {
                $t = trim($line);
                if ($t === '') {
                    if ($buf !== '') { $out .= "<p>" . _md_inline(trim($buf)) . "</p>\n"; $buf = ''; }
                    $out .= "\n";
                } elseif (preg_match('/^<(h[1-3]|ul|ol|li|pre|table|thead|tbody|tr|th|td|hr)/', $t)) {
                    if ($buf !== '') { $out .= "<p>" . _md_inline(trim($buf)) . "</p>\n"; $buf = ''; }
                    $out .= $line . "\n";
                } else {
                    $buf .= ($buf ? ' ' : '') . $line;
                }
            }
            if ($buf !== '') $out .= "<p>" . _md_inline(trim($buf)) . "</p>\n";
            $md = $out;
            foreach ($blocks as $ph => $html) $md = str_replace($ph, $html, $md);
            return $md;
        }
        $reportFile = __DIR__ . '/../docs/CI_CDARCHITECTURE_MAX.md';
        if (!file_exists($reportFile)) {
            http_response_code(404);
            echo json_encode(['error' => 'Report file not found']);
            break;
        }
        $md = file_get_contents($reportFile);
        $html = _md_to_html($md);
        echo json_encode([
            'success' => true,
            'report' => [
                'title'     => 'CI/CD Pipeline Evolution & Dashboard Integration — Full Change Report',
                'project'   => 'techno-magento (technowebmaster-group/techno-magento)',
                'dashboard' => 'mounirtms/dashboard (/home/dashboard/public_html)',
                'coverage'  => '2026-07-01 → 2026-09-22',
                'owner'     => 'mounirAb',
                'generated' => '2026-09-22 (post fixes + build)',
                'content'   => $html,
            ],
        ], JSON_PRETTY_PRINT);
        break;
    }

    case 'docs': {
        // Catalog of markdown reports/docs for the /docs hub.
        require_once __DIR__ . '/cicd_md.php';
        echo json_encode(['success' => true, 'docs' => _cicd_doc_catalog()], JSON_PRETTY_PRINT);
        break;
    }

    case 'doc': {
        // Serve one catalog doc as HTML: api/cicd.php?action=doc&file=docs/CD_JULY1_VS_TODAY.md
        require_once __DIR__ . '/cicd_md.php';
        $file = trim($_GET['file'] ?? '');
        $hit = null;
        foreach (_cicd_doc_catalog() as $d) {
            if ($d['file'] === $file) { $hit = $d; break; }
        }
        if (!$hit) {
            http_response_code(404);
            echo json_encode(['error' => 'Unknown doc: ' . $file]);
            break;
        }
        $abs = realpath(__DIR__ . '/../' . $hit['file']);
        if (!$abs || !is_file($abs)) {
            http_response_code(404);
            echo json_encode(['error' => 'Doc file missing: ' . $file]);
            break;
        }
        echo json_encode([
            'success' => true,
            'doc' => [
                'file'     => $hit['file'],
                'title'    => $hit['title'],
                'category' => $hit['category'],
                'mtime'    => $hit['mtime'],
                'html'     => _md_to_html(file_get_contents($abs)),
            ],
        ], JSON_PRETTY_PRINT);
        break;
    }

        case 'jobs':
        // Attach this environment's CURRENT build info (pipeline id / commit /
        // release / promo source) to every recent action/job, so the /cicd page
        // can display "which build did this action run against" alongside each
        // job log/status. Built info is polled live over HTTP from each env's
        // public /build-info.php (the dashboard user cannot read the env FS
        // directly), with a small per-env cache so we only hit each endpoint once.
        $snapshots = [];
        foreach (['dev', 'tsdnd'] as $k) {
            $cfg = getEnvConfig($k);
            if ($cfg) {
                $snap = fetchBuildInfo($cfg['url'] ?? '', $cfg['path'] ?? null);
                if (is_array($snap)) $snapshots[$k] = $snap;
            }
        }
        $jobs = getRecentJobs(30);
        foreach ($jobs as &$j) {
            $env = $j['env'] ?? '';
            if (isset($snapshots[$env])) {
                $s  = $snapshots[$env];
                $cur = null;
                foreach ($s['releases'] ?? [] as $r) {
                    if (!empty($r['is_current'])) { $cur = $r['build_info'] ?? null; break; }
                }
                if ($cur === null && isset($s['current_build'])) $cur = $s['current_build'];
                $j['build'] = [
                    'environment'      => $s['environment'] ?? $env,
                    'current_release'  => $s['current_release'] ?? null,
                    'pipeline_id'      => $cur['pipeline_id'] ?? null,
                    'commit_sha'       => $cur['commit_sha'] ?? null,
                    'commit_short_sha' => $cur['commit_short_sha'] ?? null,
                    'branch'           => $cur['branch'] ?? null,
                    'built_at'         => $cur['built_at'] ?? null,
                    'promoted_from'    => $cur['promoted_from'] ?? $s['promoted_from'] ?? null,
                ];
            }
        }
        unset($j);
        echo json_encode(['success' => true, 'jobs' => $jobs, 'current_builds' => $snapshots], JSON_PRETTY_PRINT);
        break;

    case 'git_status':
        $repo = dirname(__DIR__);
        $git = function (string $cmd, int $timeout = 5) use ($repo) {
            $full = 'cd ' . escapeshellarg($repo) . ' && ' . $cmd . ' 2>/dev/null </dev/null';
            $res  = shellCapture($full, $timeout);
            return $res['code'] === 0 ? trim(implode("\n", $res['lines'])) : '';
        };
        $buildAssets = glob(__DIR__ . '/../build/assets/index-*.js');
        $bundleFile  = $buildAssets ? basename($buildAssets[0]) : 'n/a';
        $bundleSize  = $buildAssets ? round(filesize($buildAssets[0]) / 1024) : 0;
        $stamp = '';
        $stampHtml = file_get_contents(__DIR__ . '/../build/index.html');
        if ($stampHtml && preg_match('/BUILD_STAMP[^0-9]*([0-9]+)/', $stampHtml, $m)) $stamp = $m[1];

        $commits = [];
        $log = $git("git log -6 --pretty=format:'%h|%s|%ai'");
        if ($log) {
            foreach (explode("\n", $log) as $line) {
                $parts = explode('|', $line, 3);
                if (count($parts) === 3) {
                    $commits[] = [
                        'hash'    => $parts[0],
                        'subject' => $parts[1],
                        'date'    => date('M j, Y', strtotime($parts[2])),
                    ];
                }
            }
        }

        $head = $git('git rev-parse --short HEAD');
        $branch = $git('git rev-parse --abbrev-ref HEAD');
        $totalCommits = (int)$git('git rev-list --count HEAD');
        $aheadBehind = $git("git rev-list --left-right --count origin/main...HEAD");
        $ab = explode("\t", $aheadBehind);

        echo json_encode([
            'success' => true,
            'repo'    => basename($repo),
            'branch'  => $branch ?: 'main',
            'head'    => $head ?: 'n/a',
            'total_commits' => $totalCommits,
            'ahead'   => isset($ab[1]) ? (int)$ab[1] : 0,
            'behind'  => isset($ab[0]) ? (int)$ab[0] : 0,
            'commits' => $commits,
            'build'   => [
                'bundle'     => $bundleFile,
                'size_kb'    => $bundleSize,
                'stamp'      => $stamp,
                'assets'     => count(glob(__DIR__ . '/../build/assets/index-*.js')),
            ],
            'timestamp' => time(),
        ]);
        break;

    case 'job_status':
        $jobId = $_GET['job_id'] ?? '';
        $status = readJobStatus($jobId);
        if (!$status) {
            echo json_encode(['error' => 'Job not found']);
            break;
        }
        $running = isJobRunning($jobId);
        $status['running'] = $running;
        $log = getJobLog($jobId, 100);
        $status['log'] = $log['lines'];
        $status['log_total'] = $log['total'];
        echo json_encode($status);
        break;

    case 'job_kill':
        $jobId = $_POST['job_id'] ?? '';
        $result = killJob($jobId);
        echo json_encode(['success' => $result, 'message' => $result ? 'Job killed' : 'Job not found']);
        break;

    case 'build':
        $env = $_POST['env'] ?? '';
        $type = $_POST['type'] ?? 'full'; // full, quick, static-only, compile-only
        
        if (isProduction($env)) {
            echo json_encode(['error' => 'PRODUCTION operations are blocked']);
            break;
        }
        
        $config = getEnvConfig($env);
        if (!$config) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $jobId = generateJobId();
        $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
        
        $script = "/home/dashboard/public_html/scripts/deployment/deploy.sh";
        $envPath = $config['path'] ?? '';
        $mroot = magentoRoot($config);
        if ($type === 'full') {
            $cmd = "bash {$script} {$env} 2>&1";
        } elseif ($type === 'quick') {
            $cmd = "bash {$script} {$env} --quick 2>&1";
        } elseif ($type === 'flush') {
            $cmd = "bash {$script} {$env} flush 2>&1";
        } elseif ($type === 'static-only') {
            $cmd = "cd {$mroot} && {$PHP_BIN} bin/magento setup:static-content:deploy fr_FR en_US -f 2>&1";
        } elseif ($type === 'compile-only') {
            $cmd = "cd {$mroot} && {$PHP_BIN} -d memory_limit=2G bin/magento setup:di:compile 2>&1";
        } else {
            echo json_encode(['error' => 'Invalid build type']);
            break;
        }
        
        writeJobStatus($jobId, [
            'id' => $jobId,
            'type' => 'build',
            'subtype' => $type,
            'env' => $env,
            'status' => 'running',
            'message' => "Starting {$type} build on {$config['name']}",
        ]);
        
        execAsync($cmd, $jobId, $logFile);
        
        echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => "Build started on {$config['name']}"]);
        break;

    case 'test':
        $env = $_POST['env'] ?? '';
        $type = $_POST['type'] ?? 'http'; // http, comprehensive, module-status, logs
        
        if (isProduction($env)) {
            echo json_encode(['error' => 'PRODUCTION operations are blocked']);
            break;
        }
        
        $config = getEnvConfig($env);
        if (!$config) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $jobId = generateJobId();
        $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
        $mroot = magentoRoot($config);
        
        switch ($type) {
            case 'http':
                $cmd = "echo '=== HTTP Health Check ===' && for page in / /checkout/cart/ /customer/account/login/ /contact/; do status=\$(curl -s -o /dev/null -w '%{http_code}' {$config['url']}\$page 2>/dev/null); echo \"\$page -> HTTP \$status\"; done && echo '=== Done ==='";
                break;
            case 'comprehensive':
                // Deep HTTP check (the old comprehensive_test.sh never shipped with the release).
                $cmd = "echo '=== Deep HTTP Check: {$config['name']} ===' && for page in / /checkout/ /checkout/cart/ /customer/account/login/ /wishlist/ /contact/ /searchresult/?q=test; do status=\$(curl -s -o /dev/null -w '%{http_code}' {$config['url']}\$page 2>/dev/null); echo \"\$page -> HTTP \$status\"; done; echo '=== Cache verify report ==='; cat {$config['path']}/shared/cache-verify.json 2>/dev/null || echo '(none)'; echo '=== Done ==='";
                break;
            case 'module-status':
                $cmd = "cd {$mroot} && {$PHP_BIN} bin/magento module:status 2>&1";
                break;
            case 'logs':
                $cmd = "echo '=== Exception Log (last 50 lines) ===' && tail -50 {$mroot}/var/log/exception.log 2>/dev/null && echo '=== System Log Errors ===' && grep -ciE 'ERR|CRIT|EMERG' {$mroot}/var/log/system.log 2>/dev/null && echo '=== PHP Error Log (last 20) ===' && tail -20 {$config['path']}/error_log 2>/dev/null";
                break;
            case 'indexer':
                $cmd = "cd {$mroot} && {$PHP_BIN} bin/magento indexer:status 2>&1";
                break;
            case 'cache-status':
                $cmd = "cd {$mroot} && {$PHP_BIN} bin/magento cache:status 2>&1";
                break;
            default:
                echo json_encode(['error' => 'Invalid test type']);
                break;
        }
        
        writeJobStatus($jobId, [
            'id' => $jobId,
            'type' => 'test',
            'subtype' => $type,
            'env' => $env,
            'status' => 'running',
            'message' => "Running {$type} tests on {$config['name']}",
        ]);
        
        execAsync($cmd, $jobId, $logFile);
        
        echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => "Test started on {$config['name']}"]);
        break;

    case 'migrate_db':
        $source = $_POST['source'] ?? '';
        $target = $_POST['target'] ?? '';
        $mode = $_POST['mode'] ?? 'structure'; // structure, structure+data, selective
        
        if (isProduction($source) || isProduction($target)) {
            echo json_encode(['error' => 'PRODUCTION operations are blocked']);
            break;
        }
        
        $srcConfig = getEnvConfig($source);
        $tgtConfig = getEnvConfig($target);
        if (!$srcConfig || !$tgtConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $jobId = generateJobId();
        $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
        
        $srcDb = $srcConfig['db'];
        $tgtDb = $tgtConfig['db'];
        
        $cmd = "echo '=== DB Migration: {$srcDb} -> {$tgtDb} ===' && echo 'Mode: {$mode}' && ";
        
        if ($mode === 'structure') {
            $cmd .= "/opt/mariadb10.6/mariadb/bin/mysqldump -u root -p'{$dbPass}' -h 127.0.0.1 -P 3307 --no-data --routines --triggers {$srcDb} 2>/dev/null | /opt/mariadb10.6/mariadb/bin/mysql -u root -p'{$dbPass}' -h 127.0.0.1 -P 3307 {$tgtDb} 2>&1 && echo 'Structure migration complete'";
        } elseif ($mode === 'structure+data') {
            $cmd .= "/opt/mariadb10.6/mariadb/bin/mysqldump -u root -p'{$dbPass}' -h 127.0.0.1 -P 3307 --single-transaction --routines --triggers {$srcDb} 2>/dev/null | /opt/mariadb10.6/mariadb/bin/mysql -u root -p'{$dbPass}' -h 127.0.0.1 -P 3307 {$tgtDb} 2>&1 && echo 'Full migration complete'";
        } else {
            echo json_encode(['error' => 'Invalid migration mode']);
            break;
        }
        
        writeJobStatus($jobId, [
            'id' => $jobId,
            'type' => 'migrate_db',
            'subtype' => $mode,
            'source' => $source,
            'target' => $target,
            'status' => 'running',
            'message' => "DB migration {$source} -> {$target} ({$mode})",
        ]);
        
        execAsync($cmd, $jobId, $logFile);
        
        echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => "DB migration started: {$source} -> {$target}"]);
        break;

    case 'migrate_code':
        $source = $_POST['source'] ?? '';
        $target = $_POST['target'] ?? '';
        $scope = $_POST['scope'] ?? 'modules'; // modules, theme, full-code
        
        if (isProduction($source) || isProduction($target)) {
            echo json_encode(['error' => 'PRODUCTION operations are blocked']);
            break;
        }
        
        $srcConfig = getEnvConfig($source);
        $tgtConfig = getEnvConfig($target);
        if (!$srcConfig || !$tgtConfig) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $jobId = generateJobId();
        $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
        
        // Sync the ACTIVE releases (current/), never the legacy flat-install
        // leftovers that still sit at the docroot root of tsdnd/production.
        $srcPath = magentoRoot($srcConfig);
        $tgtPath = magentoRoot($tgtConfig);
        
        if ($scope === 'modules') {
            $cmd = "echo '=== Code Migration: Modules ===' && rsync -avz --delete --exclude='vendor' --exclude='var' --exclude='generated' --exclude='pub/static' --exclude='pub/media' --exclude='.git' {$srcPath}/app/code/ {$tgtPath}/app/code/ 2>&1 && echo 'Module sync complete' && echo 'Setting permissions...' && chown -R {$tgtConfig['user']}:{$tgtConfig['user']} {$tgtPath}/app/code/ && chmod -R 775 {$tgtPath}/app/code/ && echo 'Permissions set'";
        } elseif ($scope === 'theme') {
            $cmd = "echo '=== Code Migration: Theme ===' && rsync -avz --delete {$srcPath}/app/design/ {$tgtPath}/app/design/ 2>&1 && chown -R {$tgtConfig['user']}:{$tgtConfig['user']} {$tgtPath}/app/design/ && chmod -R 775 {$tgtPath}/app/design/ && echo 'Theme sync complete'";
        } elseif ($scope === 'full-code') {
            $cmd = "echo '=== Code Migration: Full Code ===' && rsync -avz --delete --exclude='app/etc/env.php' --exclude='var/' --exclude='generated/' --exclude='pub/static/' --exclude='pub/media/' --exclude='vendor/' --exclude='.git/' {$srcPath}/ {$tgtPath}/ 2>&1 && chown -R {$tgtConfig['user']}:{$tgtConfig['user']} {$tgtPath}/ && chmod -R 775 {$tgtPath}/ && echo 'Full code sync complete'";
        } else {
            echo json_encode(['error' => 'Invalid scope']);
            break;
        }
        
        writeJobStatus($jobId, [
            'id' => $jobId,
            'type' => 'migrate_code',
            'subtype' => $scope,
            'source' => $source,
            'target' => $target,
            'status' => 'running',
            'message' => "Code migration {$source} -> {$target} ({$scope})",
        ]);
        
        execAsync($cmd, $jobId, $logFile);
        
        echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => "Code migration started: {$source} -> {$target}"]);
        break;

    case 'module_toggle':
        $env = $_POST['env'] ?? '';
        $module = $_POST['module'] ?? '';
        $action_type = $_POST['action_type'] ?? 'enable'; // enable, disable
        
        if (isProduction($env)) {
            echo json_encode(['error' => 'PRODUCTION operations are blocked']);
            break;
        }
        
        $config = getEnvConfig($env);
        if (!$config) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $jobId = generateJobId();
        $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
        
        $mroot = magentoRoot($config);
        $cmd = "cd {$mroot} && {$PHP_BIN} bin/magento module:{$action_type} " . escapeshellarg($module) . " 2>&1";
        
        writeJobStatus($jobId, [
            'id' => $jobId,
            'type' => 'module_toggle',
            'subtype' => $action_type,
            'env' => $env,
            'module' => $module,
            'status' => 'running',
            'message' => "{$action_type} {$module} on {$config['name']}",
        ]);
        
        execAsync($cmd, $jobId, $logFile);
        
        echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => "Module {$action_type} started"]);
        break;

    case 'reindex':
        $env = $_POST['env'] ?? '';
        
        if (isProduction($env)) {
            echo json_encode(['error' => 'PRODUCTION operations are blocked']);
            break;
        }
        
        $config = getEnvConfig($env);
        if (!$config) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $jobId = generateJobId();
        $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
        
        $mroot = magentoRoot($config);
        $cmd = "cd {$mroot} && {$PHP_BIN} -d memory_limit=2G bin/magento indexer:reindex 2>&1";
        
        writeJobStatus($jobId, [
            'id' => $jobId,
            'type' => 'reindex',
            'env' => $env,
            'status' => 'running',
            'message' => "Reindexing {$config['name']}",
        ]);
        
        execAsync($cmd, $jobId, $logFile);
        
        echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => "Reindex started on {$config['name']}"]);
        break;

    case 'health':
        // Read-only diagnostic (deploy:mode:show, cache:status, indexer:status,
        // curl, du), so GET is safe and expected here. Reading $_POST['env'] only
        // meant every GET caller - a browser bookmark, curl, the CI/CD page's
        // status poll - silently got "Invalid environment" back. GET first, then
        // POST. The state-changing actions in this file (build, reindex,
        // module_toggle, run_script, rollback) deliberately stay POST-only.
        $env = $_GET['env'] ?? $_POST['env'] ?? '';
        
        if (isProduction($env)) {
            echo json_encode(['error' => 'PRODUCTION operations are blocked']);
            break;
        }
        
        $config = getEnvConfig($env);
        if (!$config) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $jobId = generateJobId();
        $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
        
        $mroot = magentoRoot($config);
        $cmd = "echo '=== Health Check: {$config['name']} ===' && echo 'PHP: ' && {$PHP_BIN} -v | head -1 && echo 'Magento: ' && cd {$mroot} && {$PHP_BIN} bin/magento --version 2>&1 && echo 'Mode: ' && {$PHP_BIN} bin/magento deploy:mode:show 2>&1 && echo 'Cache: ' && {$PHP_BIN} bin/magento cache:status 2>&1 && echo 'Indexers: ' && {$PHP_BIN} bin/magento indexer:status 2>&1 | head -15 && echo 'HTTP: ' && curl -s -o /dev/null -w '%{http_code}' {$config['url']}/ 2>/dev/null && echo '' && echo 'Disk: ' && du -sh . 2>/dev/null | cut -f1 && echo '=== Done ==='";
        
        writeJobStatus($jobId, [
            'id' => $jobId,
            'type' => 'health',
            'env' => $env,
            'status' => 'running',
            'message' => "Health check on {$config['name']}",
        ]);
        
        execAsync($cmd, $jobId, $logFile);
        
        echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => "Health check started on {$config['name']}"]);
        break;

    case 'scripts':
        // List available scripts for an environment
        $env = $_POST['env'] ?? 'dev';
        $config = getEnvConfig($env);
        if (!$config) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        $scripts = [];
        $path = $config['path'];
        
        // Shell scripts in root
        foreach (glob("{$path}/*.sh") as $sh) {
            $scripts[] = [
                'name' => basename($sh),
                'path' => $sh,
                'type' => 'shell',
                'size' => round(filesize($sh) / 1024, 1) . 'KB',
            ];
        }
        
        // PHP scripts in scripts/
        $scriptDirs = ["{$path}/scripts", "/home/dashboard/public_html/scripts"];
        foreach ($scriptDirs as $dir) {
            if (is_dir($dir)) {
                foreach (glob("{$dir}/**/*.php") as $php) {
                    $scripts[] = [
                        'name' => basename($php),
                        'path' => $php,
                        'type' => 'php',
                        'size' => round(filesize($php) / 1024, 1) . 'KB',
                    ];
                }
            }
        }
        
        echo json_encode(['success' => true, 'scripts' => $scripts, 'count' => count($scripts)]);
        break;

    case 'run_script':
        $env = $_POST['env'] ?? '';
        $scriptPath = $_POST['script_path'] ?? '';
        
        if (isProduction($env)) {
            echo json_encode(['error' => 'PRODUCTION operations are blocked']);
            break;
        }
        
        $config = getEnvConfig($env);
        if (!$config) {
            echo json_encode(['error' => 'Invalid environment']);
            break;
        }
        
        // Security: ensure script is in an allowed path
        $realPath = realpath($scriptPath);
        if (!$realPath || (strpos($realPath, $config['path']) !== 0 && strpos($realPath, '/home/dashboard/public_html/scripts') !== 0)) {
            echo json_encode(['error' => 'Script path not allowed']);
            break;
        }
        
        $jobId = generateJobId();
        $logFile = "/home/dashboard/public_html/logs/cicd/{$jobId}.log";
        
        if (pathinfo($scriptPath, PATHINFO_EXTENSION) === 'sh') {
            $cmd = "bash " . escapeshellarg($scriptPath) . " 2>&1";
        } else {
            $cmd = "cd " . escapeshellarg(dirname($scriptPath)) . " && {$PHP_BIN} " . escapeshellarg($scriptPath) . " 2>&1";
        }
        
        writeJobStatus($jobId, [
            'id' => $jobId,
            'type' => 'run_script',
            'env' => $env,
            'script' => basename($scriptPath),
            'status' => 'running',
            'message' => "Running script on {$config['name']}",
        ]);
        
        execAsync($cmd, $jobId, $logFile);
        
        echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => "Script started"]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action: ' . $action]);
}
