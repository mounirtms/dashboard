<?php
/**
 * GitLab Pipeline Proxy API
 *
 * Proxies requests to the GitLab API for the techno-magento project.
 * Returns live pipeline, branch, and job data to the CI/CD dashboard page.
 * Credentials are stored in config (.env / server-side only — never exposed to client).
 *
 * Endpoints (GET ?action=...):
 *   pipelines      — Latest 20 pipelines across all branches
 *   branches       — Branch list with last commit info
 *   jobs           — Jobs for a specific pipeline (?pipeline_id=NNN)
 *   trigger        — POST: trigger a new pipeline for a branch
 *   pipeline_detail — GET: single pipeline detail (?pipeline_id=NNN)
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/PermissionChecker.php';
Config::load();

// ── Auth ──────────────────────────────────────────────────────────────────────
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

function gitlab_require_read(): void {
    if (empty($_SESSION['logged_in'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

function gitlab_require_write(): void {
    if (empty($_SESSION['logged_in'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    $isAdmin = ($_SESSION['role'] ?? '') === 'admin';
    $hasCicd = PermissionChecker::hasPermission('can_access_cicd');
    if (!$isAdmin && !$hasCicd) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions to trigger/play pipeline jobs']);
        exit;
    }
}

// ── GitLab configuration (.env via Config) ────────────────────────────────────
define('GITLAB_TOKEN',      Config::get('gitlab.token',      ''));
define('GITLAB_PROJECT_ID', Config::get('gitlab.project_id', '83067970'));
define('GITLAB_API',        Config::get('gitlab.api',        'https://gitlab.com/api/v4'));
define('GITLAB_TIMEOUT',    15);

/**
 * Make a GitLab API request.
 *
 * @param string $path    Relative path under /projects/{id}/...
 * @param string $method  GET|POST|PUT
 * @param array  $body    POST body (will be JSON-encoded)
 * @return array{code: int, body: mixed}
 */
function gitlab_request(string $path, string $method = 'GET', array $body = []): array
{
    $url = GITLAB_API . '/projects/' . GITLAB_PROJECT_ID . $path;

    $ch = curl_init($url);
    $headers = [
        'PRIVATE-TOKEN: ' . GITLAB_TOKEN,
        'Content-Type: application/json',
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => GITLAB_TIMEOUT,
        CURLOPT_TIMEOUT        => GITLAB_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    if ($method === 'POST' && !empty($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['code' => 0, 'body' => ['error' => "cURL error: {$curlErr}"]];
    }

    $decoded = json_decode($response, true);
    return ['code' => $httpCode, 'body' => $decoded ?? $response];
}

/**
 * Normalise a GitLab pipeline row to the shape our frontend expects.
 */
function normalise_pipeline(array $p): array
{
    return [
        'id'         => $p['id'],
        'iid'        => $p['iid'] ?? $p['id'],
        'sha'        => $p['sha'] ?? '',
        'short_sha'  => substr($p['sha'] ?? '', 0, 8),
        'ref'        => $p['ref'] ?? '',
        'status'     => $p['status'] ?? 'unknown',
        'source'     => $p['source'] ?? 'push',
        'created_at' => $p['created_at'] ?? null,
        'updated_at' => $p['updated_at'] ?? null,
        'web_url'    => $p['web_url'] ?? '',
        'duration'   => $p['duration'] ?? null,
        'queued_duration' => $p['queued_duration'] ?? null,
    ];
}

/**
 * Normalise a GitLab branch row.
 */
function normalise_branch(array $b): array
{
    $commit = $b['commit'] ?? [];
    return [
        'name'              => $b['name'],
        'protected'         => $b['protected'] ?? false,
        'default'           => $b['default'] ?? false,
        'sha'               => $commit['short_id'] ?? substr($commit['id'] ?? '', 0, 8),
        'full_sha'          => $commit['id'] ?? '',
        'committed_at'      => $commit['committed_date'] ?? $commit['created_at'] ?? null,
        'committer_name'    => $commit['committer_name'] ?? $commit['author_name'] ?? '',
        'commit_title'      => $commit['title'] ?? '',
        'web_url'           => $b['web_url'] ?? '',
        'can_push'          => $b['can_push'] ?? false,
    ];
}

// ── Route dispatch ────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? 'pipelines';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {

        // ── List latest pipelines (all branches) ──────────────────────────────
        case 'pipelines': {
            gitlab_require_read();
            $perPage = min((int)($_GET['per_page'] ?? 30), 100);
            $page    = max((int)($_GET['page']     ?? 1), 1);
            $ref     = $_GET['ref'] ?? '';
            $status  = $_GET['status'] ?? '';

            $qs = http_build_query(array_filter([
                'per_page' => $perPage,
                'page'     => $page,
                'ref'      => $ref,
                'status'   => $status,
                'order_by' => 'id',
                'sort'     => 'desc',
            ]));
            $result = gitlab_request("/pipelines?{$qs}");

            if ($result['code'] !== 200) {
                http_response_code(502);
                echo json_encode(['error' => 'GitLab API error', 'code' => $result['code'], 'detail' => $result['body']]);
                exit;
            }

            $pipelines = array_map('normalise_pipeline', (array)$result['body']);
            echo json_encode(['status' => 'ok', 'pipelines' => $pipelines, 'count' => count($pipelines)]);
            break;
        }

        // ── Single pipeline detail + job summary ──────────────────────────────
        case 'pipeline_detail': {
            gitlab_require_read();
            $id = (int)($_GET['pipeline_id'] ?? 0);
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'pipeline_id required']); exit; }

            $detail = gitlab_request("/pipelines/{$id}");
            $jobs   = gitlab_request("/pipelines/{$id}/jobs?per_page=50");

            if ($detail['code'] !== 200) {
                http_response_code(502);
                echo json_encode(['error' => 'Pipeline not found', 'code' => $detail['code']]);
                exit;
            }

            $normalJobs = array_map(function($j) {
                return [
                    'id'         => $j['id'],
                    'name'       => $j['name'],
                    'stage'      => $j['stage'],
                    'status'     => $j['status'],
                    'duration'   => $j['duration'] ?? null,
                    'started_at' => $j['started_at'] ?? null,
                    'finished_at'=> $j['finished_at'] ?? null,
                    'web_url'    => $j['web_url'] ?? '',
                    'allow_failure' => $j['allow_failure'] ?? false,
                ];
            }, (array)($jobs['body'] ?? []));

            echo json_encode([
                'status'   => 'ok',
                'pipeline' => normalise_pipeline($detail['body']),
                'jobs'     => $normalJobs,
            ]);
            break;
        }

        // ── Branch list ───────────────────────────────────────────────────────
        case 'branches': {
            gitlab_require_read();
            $result = gitlab_request('/repository/branches?per_page=50&order_by=updated_at&sort=desc');

            if ($result['code'] !== 200) {
                http_response_code(502);
                echo json_encode(['error' => 'GitLab API error', 'code' => $result['code']]);
                exit;
            }

            $branches = array_map('normalise_branch', (array)$result['body']);
            echo json_encode(['status' => 'ok', 'branches' => $branches]);
            break;
        }

        // ── Jobs for a pipeline ───────────────────────────────────────────────
        case 'jobs': {
            gitlab_require_read();
            $id = (int)($_GET['pipeline_id'] ?? 0);
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'pipeline_id required']); exit; }

            $result = gitlab_request("/pipelines/{$id}/jobs?per_page=100");

            if ($result['code'] !== 200) {
                http_response_code(502);
                echo json_encode(['error' => 'Jobs not found', 'code' => $result['code']]);
                exit;
            }

            echo json_encode(['status' => 'ok', 'jobs' => $result['body']]);
            break;
        }

        // ── Trigger a new pipeline ────────────────────────────────────────────
        case 'trigger': {
            gitlab_require_write();
            if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST required']); exit; }

            if (!PermissionChecker::isAdmin()) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin role required to trigger pipelines']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $branch = trim($input['branch'] ?? $_POST['branch'] ?? '');
            $allowed = ['tsdnd', 'dev', 'master'];

            if (!in_array($branch, $allowed, true)) {
                http_response_code(400);
                echo json_encode(['error' => "Invalid branch. Allowed: " . implode(', ', $allowed)]);
                exit;
            }

            $result = gitlab_request('/pipeline', 'POST', ['ref' => $branch]);

            if ($result['code'] !== 201) {
                http_response_code(502);
                echo json_encode(['error' => 'Pipeline trigger failed', 'code' => $result['code'], 'detail' => $result['body']]);
                exit;
            }

            $p = normalise_pipeline($result['body']);
            echo json_encode([
                'status'  => 'ok',
                'message' => "Pipeline #{$p['id']} triggered on {$branch}",
                'pipeline' => $p,
            ]);
            break;
        }

        // ── Play a manual job (deploy:*, promote:*) ───────────────────────────
        // POST action=play_job  body: { job_id }  OR { pipeline_id, job_name }
        case 'play_job': {
            gitlab_require_write();
            if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST required']); exit; }

            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $jobId = (int)($input['job_id'] ?? $_POST['job_id'] ?? 0);
            $pipelineId = (int)($input['pipeline_id'] ?? $_POST['pipeline_id'] ?? 0);
            $jobName = trim($input['job_name'] ?? $_POST['job_name'] ?? '');

            if (!$jobId && $pipelineId && $jobName !== '') {
                $jobsRes = gitlab_request("/pipelines/{$pipelineId}/jobs?per_page=100");
                if ($jobsRes['code'] === 200) {
                    foreach ((array)$jobsRes['body'] as $j) {
                        if (($j['name'] ?? '') === $jobName) { $jobId = (int)$j['id']; break; }
                    }
                }
            }

            if (!$jobId) {
                http_response_code(400);
                echo json_encode(['error' => 'job_id or (pipeline_id + job_name) required']);
                exit;
            }

            // Production promote requires admin
            if (!PermissionChecker::isAdmin() && $jobName !== '' && str_starts_with($jobName, 'promote:tsdnd-to-master')) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin role required to promote to production']);
                exit;
            }

            $result = gitlab_request("/jobs/{$jobId}/play", 'POST');
            if ($result['code'] < 200 || $result['code'] >= 300) {
                http_response_code(502);
                echo json_encode(['error' => 'Job play failed', 'code' => $result['code'], 'detail' => $result['body']]);
                exit;
            }

            $job = $result['body'];
            $jobNameOut = (string)($job['name'] ?? $jobName);
            $jobStatusOut = (string)($job['status'] ?? 'unknown');
            echo json_encode([
                'status'  => 'ok',
                'message' => "Job '{$jobNameOut}' triggered (status: {$jobStatusOut})",
                'job'     => [
                    'id'     => $job['id'] ?? $jobId,
                    'name'   => $jobNameOut,
                    'status' => $jobStatusOut,
                    'web_url'=> $job['web_url'] ?? '',
                ],
            ]);
            break;
        }

        // ── Play a named manual job by env shortcut ───────────────────────────
        // POST action=play_env  body: { env: dev|tsdnd|prod, pipeline_id? }
        case 'play_env': {
            gitlab_require_write();
            if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST required']); exit; }

            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $env = strtolower(trim($input['env'] ?? $_POST['env'] ?? ''));
            $map = [
                'dev'   => ['ref' => 'dev',   'job' => 'deploy:dev'],
                'tsdnd' => ['ref' => 'tsdnd', 'job' => 'deploy:tsdnd'],
                'prod'  => ['ref' => 'master','job' => 'deploy:master'],
                'master'=> ['ref' => 'master','job' => 'deploy:master'],
                'promote_dev_tsdnd' => ['ref' => 'dev', 'job' => 'promote:dev-to-tsdnd'],
                'promote_tsdnd_master' => ['ref' => 'tsdnd', 'job' => 'promote:tsdnd-to-master'],
            ];
            if (!isset($map[$env])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid env. Allowed: ' . implode(', ', array_keys($map))]);
                exit;
            }
            if (!PermissionChecker::isAdmin() && ($env === 'prod' || $env === 'master' || $env === 'promote_tsdnd_master')) {
                http_response_code(403);
                echo json_encode(['error' => 'Admin role required for production actions']);
                exit;
            }

            $pipelineId = (int)($input['pipeline_id'] ?? $_POST['pipeline_id'] ?? 0);
            if (!$pipelineId) {
                // Find latest pipeline for that ref
                $ps = gitlab_request('/pipelines?per_page=5&ref=' . urlencode($map[$env]['ref']) . '&order_by=id&sort=desc');
                $list = (array)($ps['body'] ?? []);
                if (!$list) {
                    // Create a new pipeline first (admin only for new pipelines)
                    if (!PermissionChecker::isAdmin()) {
                        http_response_code(404);
                        echo json_encode(['error' => 'No pipeline found on ref and admin required to create one']);
                        exit;
                    }
                    $created = gitlab_request('/pipeline', 'POST', ['ref' => $map[$env]['ref']]);
                    if ($created['code'] !== 201) {
                        http_response_code(502);
                        echo json_encode(['error' => 'Pipeline create failed', 'detail' => $created['body']]);
                        exit;
                    }
                    $pipelineId = (int)($created['body']['id'] ?? 0);
                    // Wait briefly for jobs to be generated
                    usleep(1500000);
                } else {
                    $pipelineId = (int)($list[0]['id'] ?? 0);
                }
            }

            $input2 = ['pipeline_id' => $pipelineId, 'job_name' => $map[$env]['job']];
            // Reuse play_job logic inline
            $jobsRes = gitlab_request("/pipelines/{$pipelineId}/jobs?per_page=100");
            $jobId = 0;
            if ($jobsRes['code'] === 200) {
                foreach ((array)$jobsRes['body'] as $j) {
                    if (($j['name'] ?? '') === $map[$env]['job']) { $jobId = (int)$j['id']; break; }
                }
            }
            if (!$jobId) {
                http_response_code(404);
                echo json_encode(['error' => "Manual job '{$map[$env]['job']}' not found on pipeline #{$pipelineId}", 'pipeline_id' => $pipelineId]);
                exit;
            }
            $result = gitlab_request("/jobs/{$jobId}/play", 'POST');
            if ($result['code'] < 200 || $result['code'] >= 300) {
                http_response_code(502);
                echo json_encode(['error' => 'Job play failed', 'code' => $result['code'], 'detail' => $result['body']]);
                exit;
            }
            $job = $result['body'];
            echo json_encode([
                'status'  => 'ok',
                'message' => "Played {$map[$env]['job']} on pipeline #{$pipelineId}",
                'pipeline_id' => $pipelineId,
                'job' => [
                    'id' => $job['id'] ?? $jobId,
                    'name' => $job['name'] ?? $map[$env]['job'],
                    'status' => $job['status'] ?? 'pending',
                    'web_url' => $job['web_url'] ?? '',
                ],
            ]);
            break;
        }

        // ── Project info ──────────────────────────────────────────────────────
        case 'project': {
            gitlab_require_read();
            $result = gitlab_request('');  // /projects/{id}
            // Strip sensitive fields
            if (is_array($result['body'])) {
                $safe = [
                    'id'                => $result['body']['id']  ?? null,
                    'name'              => $result['body']['name'] ?? '',
                    'path_with_namespace' => $result['body']['path_with_namespace'] ?? '',
                    'description'       => $result['body']['description'] ?? '',
                    'web_url'           => $result['body']['web_url'] ?? '',
                    'default_branch'    => $result['body']['default_branch'] ?? '',
                    'last_activity_at'  => $result['body']['last_activity_at'] ?? null,
                    'visibility'        => $result['body']['visibility'] ?? '',
                    'open_issues_count' => $result['body']['open_issues_count'] ?? 0,
                    'star_count'        => $result['body']['star_count'] ?? 0,
                    'forks_count'       => $result['body']['forks_count'] ?? 0,
                ];
                echo json_encode(['status' => 'ok', 'project' => $safe]);
            } else {
                http_response_code(502);
                echo json_encode(['error' => 'GitLab API error', 'code' => $result['code']]);
            }
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['error' => "Unknown action: {$action}", 'available' => ['pipelines', 'pipeline_detail', 'branches', 'jobs', 'trigger', 'play_job', 'play_env', 'project']]);
    }

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}
