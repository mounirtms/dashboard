<?php
/**
 * GitLab API CI/CD Integration
 * 
 * Used to trigger GitLab pipelines for Magento (dev, tsdnd, prod deployments).
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/PermissionChecker.php';

if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!PermissionChecker::isAdmin() && !PermissionChecker::hasPermission('can_access_cicd')) {
    http_response_code(403);
    echo json_encode(['error' => 'Insufficient permissions to trigger deployments']);
    exit;
}

// Configuration
$GITLAB_API_URL = 'https://gitlab.com/api/v4';
$PROJECT_ID = '83067970'; // technowebmaster-group/techno-magento
$PRIVATE_TOKEN = 'glpat-TC8N70-9QYn20qTa4VvDeWM6MQpvOjEKdTpud3lwOQ8.01.170cmjo9t'; // Provided by user

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

function gitlabRequest($endpoint, $method = 'GET', $data = []) {
    global $GITLAB_API_URL, $PRIVATE_TOKEN;
    $url = $GITLAB_API_URL . $endpoint;
    
    $ch = curl_init($url);
    $headers = [
        "PRIVATE-TOKEN: $PRIVATE_TOKEN",
        "Content-Type: application/json",
        "Accept: application/json"
    ];
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($data)) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['code' => $httpCode, 'data' => json_decode($response, true) ?? $response];
}

try {
    if ($method === 'GET' && $action === 'pipelines') {
        $page = $_GET['page'] ?? 1;
        $res = gitlabRequest("/projects/{$PROJECT_ID}/pipelines?page={$page}&per_page=20");
        echo json_encode(['success' => true, 'pipelines' => $res['data']]);
        exit;
    }

    if ($method === 'POST' && $action === 'trigger') {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?? $_POST;
        
        $ref = $input['branch'] ?? 'dev';
        $env = $input['env'] ?? 'dev'; // dev, tsdnd, prod
        if ($env === 'prod') $env = 'production';
        
        // 1. Create Pipeline
        $res = gitlabRequest("/projects/{$PROJECT_ID}/pipeline", 'POST', ['ref' => $ref]);
        if ($res['code'] < 200 || $res['code'] >= 300) {
            http_response_code(400);
            echo json_encode(['error' => 'Failed to create pipeline', 'details' => $res['data']]);
            exit;
        }
        $pipelineId = $res['data']['id'];

        // 2. Fetch jobs and play the manual deploy job
        // It might take a moment for jobs to be generated, so we check.
        sleep(2);
        $jobsRes = gitlabRequest("/projects/{$PROJECT_ID}/pipelines/{$pipelineId}/jobs");
        if ($jobsRes['code'] >= 200 && $jobsRes['code'] < 300) {
            $jobs = $jobsRes['data'];
            $deployJobId = null;
            $targetJobName = "deploy:$env";
            foreach ($jobs as $job) {
                if ($job['name'] === $targetJobName) {
                    $deployJobId = $job['id'];
                    break;
                }
            }
            if ($deployJobId) {
                $playRes = gitlabRequest("/projects/{$PROJECT_ID}/jobs/{$deployJobId}/play", 'POST');
                echo json_encode([
                    'success' => true, 
                    'pipeline' => $res['data'],
                    'job' => $playRes['data'],
                    'message' => "Pipeline $pipelineId created and deployment job triggered for $env."
                ]);
                exit;
            } else {
                echo json_encode([
                    'success' => true,
                    'pipeline' => $res['data'],
                    'message' => "Pipeline $pipelineId created, but deploy job for $env was not found. (It may be pending release)."
                ]);
                exit;
            }
        }
        
        echo json_encode(['success' => true, 'pipeline' => $res['data'], 'message' => "Pipeline triggered for $env on branch $ref"]);
        exit;
    }

    if ($method === 'GET' && $action === 'pipeline_status') {
        $pipelineId = $_GET['id'] ?? '';
        if (!$pipelineId) {
            http_response_code(400);
            echo json_encode(['error' => 'Pipeline ID required']);
            exit;
        }
        $res = gitlabRequest("/projects/{$PROJECT_ID}/pipelines/{$pipelineId}");
        echo json_encode(['success' => true, 'pipeline' => $res['data']]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
