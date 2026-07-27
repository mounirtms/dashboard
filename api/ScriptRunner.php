<?php
/**
 * ScriptRunner Service
 *
 * Secure execution engine for backend scripts with timeout,
 * logging, and isolated environments.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/DatabasePool.php';

class ScriptRunner {
    private $pdo;
    private $scriptsDir;

    public function __construct() {
        Config::load();
        $this->pdo = Config::getPDO();
        $this->scriptsDir = Config::get('paths.scripts', dirname(__DIR__) . '/scripts');
    }

    /**
     * Get list of allowed scripts mapping
     * @return array
     */
    public function getAllowedScripts() {
        return [
            'system_monitor'    => 'monitoring/system_monitor.sh',
            'cpu_monitor'       => 'monitoring/cpu_monitor.sh',
            'queue_monitor'     => 'monitoring/queue_monitor.sh',
            'cpu_optimize'      => 'optimization/cpu_optimize.sh',
            'emergency_throttle'=> 'optimization/emergency_cpu_throttle.sh',
            'queue_optimize'    => 'maintenance/queue_optimize.sh',
            'master_cleanup'    => 'maintenance/master_cleanup.sh',
        ];
    }

    /**
     * Execute a script securely
     *
     * @param string $scriptKey
     * @param array  $args
     * @param int    $userId
     * @param int    $timeout  seconds
     * @return array
     */
    public function execute($scriptKey, $args = [], $userId = null, $timeout = 300) {
        $allowed = $this->getAllowedScripts();

        if (!isset($allowed[$scriptKey])) {
            throw new Exception("Unauthorized or unknown script: $scriptKey");
        }

        $scriptPath = $this->scriptsDir . '/' . $allowed[$scriptKey];
        if (!file_exists($scriptPath)) {
            throw new Exception("Script file not found: " . $allowed[$scriptKey]);
        }

        // Sanitize arguments to prevent injection
        $escapedArgs = array_map('escapeshellarg', $args);
        $commandStr  = 'bash ' . escapeshellarg($scriptPath) . ' ' . implode(' ', $escapedArgs);

        // Get human-readable script name from key
        $scriptName = str_replace('_', ' ', $scriptKey);

        // Record execution start — store both script_id (key) and script_name (human label)
        $stmt = $this->pdo->prepare(
            "INSERT INTO script_executions (script_id, script_name, executed_by, status, started_at)
             VALUES (?, ?, 'running', NOW())"
        );
        $stmt->execute([$scriptKey, $scriptName, $userId]);
        $executionId = $this->pdo->lastInsertId();

        $descriptorspec = [
            0 => ["pipe", "r"],
            1 => ["pipe", "w"],
            2 => ["pipe", "w"],
        ];

        $startTime   = microtime(true);
        $process     = proc_open($commandStr, $descriptorspec, $pipes, $this->scriptsDir);

        if (!is_resource($process)) {
            $this->updateExecution($executionId, 'failed', -1, "Failed to start process.", $startTime);
            throw new Exception("Failed to start process.");
        }

        stream_set_blocking($pipes[1], 0);
        stream_set_blocking($pipes[2], 0);

        $output   = '';
        $error    = '';
        $status   = 'running';
        $exitCode = -1;

        while (true) {
            $statusArray = proc_get_status($process);
            if (!$statusArray['running']) {
                $exitCode = $statusArray['exitcode'];
                $status   = ($exitCode === 0) ? 'completed' : 'failed';
                break;
            }

            if ((microtime(true) - $startTime) > $timeout) {
                proc_terminate($process, 9); // SIGKILL
                $status  = 'timeout';
                $error  .= "\n[Execution Timed Out after {$timeout} seconds]";
                break;
            }

            $read    = [$pipes[1], $pipes[2]];
            $write   = null;
            $except  = null;
            $changed = stream_select($read, $write, $except, 1);

            if ($changed > 0) {
                if (in_array($pipes[1], $read)) $output .= stream_get_contents($pipes[1]);
                if (in_array($pipes[2], $read)) $error  .= stream_get_contents($pipes[2]);
            }
        }

        $output .= stream_get_contents($pipes[1]);
        $error  .= stream_get_contents($pipes[2]);

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $fullOutput = trim("STDOUT:\n$output\nSTDERR:\n$error");

        $this->updateExecution($executionId, $status, $exitCode, $fullOutput, $startTime);

        return [
            'execution_id' => $executionId,
            'status'       => $status,
            'exit_code'    => $exitCode,
            'output'       => $fullOutput,
        ];
    }

    private function updateExecution($executionId, $status, $exitCode, $output, $startTime = null) {
        $durationMs = $startTime ? (int)round((microtime(true) - $startTime) * 1000) : null;

        // Support both finished_at (new schema) and completed_at (old schema) gracefully
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE script_executions
                 SET status = ?, exit_code = ?, output = ?, finished_at = NOW(), duration_ms = ?
                 WHERE id = ?"
            );
            $stmt->execute([$status, $exitCode, $output, $durationMs, $executionId]);
        } catch (\PDOException $e) {
            // Fall back to completed_at if schema not yet migrated
            $stmt = $this->pdo->prepare(
                "UPDATE script_executions
                 SET status = ?, exit_code = ?, output = ?, completed_at = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([$status, $exitCode, $output, $executionId]);
        }
    }

    public function getLogs($limit = 50) {
        $stmt = $this->pdo->prepare(
            "SELECT id, script_id, script_name, status, exit_code, output,
                    started_at, finished_at, duration_ms, executed_by
             FROM script_executions
             ORDER BY id DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
