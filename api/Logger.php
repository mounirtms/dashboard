<?php
/**
 * Centralized Logger - Monolog Singleton
 * 
 * Provides structured JSON logging with rotating files,
 * request correlation IDs, and channel-based separation.
 * 
 * Usage:
 *   Logger::info('message', ['context' => 'data']);
 *   Logger::api()->info('API request', [...]);
 *   Logger::auth()->warning('Login failed', [...]);
 */

class Logger {
    private static ?self $instance = null;
    private static ?string $correlationId = null;
    private array $loggers = [];
    private string $logDir;
    private bool $debug;
    private ?\Monolog\Logger $defaultLogger = null;
    private array $processors = [];

    private function __construct() {
        $this->logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }

        // Try to load Config, fall back to env
        if (class_exists('Config')) {
            Config::load();
            $this->debug = Config::get('app.debug', false);
        } else {
            $this->debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        // Generate or read correlation ID
        self::$correlationId = $_SERVER['HTTP_X_CORRELATION_ID']
            ?? bin2hex(random_bytes(8));
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Static convenience methods ──

    public static function info(string $message, array $context = []): void {
        self::getInstance()->defaultLogger()->info($message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::getInstance()->defaultLogger()->error($message, $context);
    }

    public static function warning(string $message, array $context = []): void {
        self::getInstance()->defaultLogger()->warning($message, $context);
    }

    public static function debug(string $message, array $context = []): void {
        self::getInstance()->defaultLogger()->debug($message, $context);
    }

    public static function critical(string $message, array $context = []): void {
        self::getInstance()->defaultLogger()->critical($message, $context);
    }

    // ── Channel-based loggers ──

    public static function api(): \Monolog\Logger {
        return self::getInstance()->channel('api');
    }

    public static function audit(): \Monolog\Logger {
        return self::getInstance()->channel('audit');
    }

    public static function auth(): \Monolog\Logger {
        return self::getInstance()->channel('auth');
    }

    public static function database(): \Monolog\Logger {
        return self::getInstance()->channel('database');
    }

    public static function telegram(): \Monolog\Logger {
        return self::getInstance()->channel('telegram');
    }

    // ── Correlation ID ──

    public static function getCorrelationId(): string {
        return self::$correlationId ?? 'unknown';
    }

    public static function setCorrelationId(string $id): void {
        self::$correlationId = $id;
    }

    // ── Internal ──

    private function defaultLogger(): \Monolog\Logger {
        return $this->channel('app');
    }

    private function channel(string $name): \Monolog\Logger {
        if (!isset($this->loggers[$name])) {
            $this->loggers[$name] = $this->createLogger($name);
        }
        return $this->loggers[$name];
    }

    private function createLogger(string $channel): \Monolog\Logger {
        // Fallback if Monolog not installed
        if (!class_exists(\Monolog\Logger::class)) {
            return new FallbackLogger($channel);
        }

        $logger = new \Monolog\Logger($channel);

        // Rotating file handler - JSON format, 30 days retention
        $handler = new \Monolog\Handler\RotatingFileHandler(
            $this->logDir . '/app.log',
            30,
            \Monolog\Level::Debug
        );
        $handler->setFormatter(new \Monolog\Formatter\JsonFormatter());
        $logger->pushHandler($handler);

        // Processors (Monolog 3 uses immutable LogRecord)
        $correlationId = self::$correlationId ?? 'unknown';
        $url = $_SERVER['REQUEST_URI'] ?? 'cli';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

        $logger->pushProcessor(function ($record) use ($correlationId, $url, $ip, $method) {
            return $record->with(extra: [
                'correlation_id' => $correlationId,
                'url' => $url,
                'ip' => $ip,
                'method' => $method,
            ]);
        });

        if ($this->debug) {
            $logger->pushProcessor(new \Monolog\Processor\IntrospectionProcessor());
        }

        $logger->pushProcessor(new \Monolog\Processor\MemoryUsageProcessor());

        return $logger;
    }
}

/**
 * Fallback logger for when Monolog is not installed.
 * Delegates to PHP's error_log().
 */
class FallbackLogger {
    private string $channel;

    public function __construct(string $channel) {
        $this->channel = $channel;
    }

    public function info(string $message, array $context = []): void {
        error_log("[{$this->channel}] [INFO] $message " . ($context ? json_encode($context) : ''));
    }

    public function error(string $message, array $context = []): void {
        error_log("[{$this->channel}] [ERROR] $message " . ($context ? json_encode($context) : ''));
    }

    public function warning(string $message, array $context = []): void {
        error_log("[{$this->channel}] [WARNING] $message " . ($context ? json_encode($context) : ''));
    }

    public function debug(string $message, array $context = []): void {
        error_log("[{$this->channel}] [DEBUG] $message " . ($context ? json_encode($context) : ''));
    }

    public function critical(string $message, array $context = []): void {
        error_log("[{$this->channel}] [CRITICAL] $message " . ($context ? json_encode($context) : ''));
    }
}
