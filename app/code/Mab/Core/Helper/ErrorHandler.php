<?php
namespace Mab\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Psr\Log\LoggerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Centralized error handling utility for MAB modules
 */
class ErrorHandler extends AbstractHelper
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Data
     */
    private $coreHelper;

    /**
     * @param Context $context
     * @param LoggerInterface $logger
     * @param Data $coreHelper
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        Data $coreHelper
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->coreHelper = $coreHelper;
    }

    /**
     * Handle and log exceptions with context
     *
     * @param \Exception $exception
     * @param string $context
     * @param array $additionalData
     * @param bool $rethrow
     * @return void
     * @throws \Exception
     */
    public function handleException(
        \Exception $exception,
        string $context = '',
        array $additionalData = [],
        bool $rethrow = false
    ): void {
        $logData = [
            'context' => $context,
            'exception_type' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];

        if (!empty($additionalData)) {
            $logData['additional_data'] = $additionalData;
        }

        // Log based on exception type
        if ($exception instanceof LocalizedException || $exception instanceof NoSuchEntityException) {
            $this->logger->warning('[MAB Error Handler] ' . $context, $logData);
        } else {
            $this->logger->error('[MAB Error Handler] ' . $context, $logData);
        }

        if ($rethrow) {
            throw $exception;
        }
    }

    /**
     * Execute a callable with error handling
     *
     * @param callable $callback
     * @param mixed $defaultReturn
     * @param string $context
     * @param array $additionalData
     * @return mixed
     */
    public function executeWithErrorHandling(
        callable $callback,
        $defaultReturn = null,
        string $context = '',
        array $additionalData = []
    ) {
        try {
            return $callback();
        } catch (\Exception $e) {
            $this->handleException($e, $context, $additionalData, false);
            return $defaultReturn;
        }
    }

    /**
     * Validate array key exists and return value or default
     *
     * @param array $array
     * @param string|int $key
     * @param mixed $default
     * @param string $context
     * @return mixed
     */
    public function getArrayValue(array $array, $key, $default = null, string $context = '')
    {
        try {
            if (!array_key_exists($key, $array)) {
                if ($context && $this->coreHelper->isDebugModeEnabled()) {
                    $this->logger->debug("[MAB Error Handler] Array key '{$key}' not found in context: {$context}");
                }
                return $default;
            }
            return $array[$key];
        } catch (\Exception $e) {
            $this->handleException($e, "Error accessing array key '{$key}' in context: {$context}");
            return $default;
        }
    }

    /**
     * Safely decode JSON with error handling
     *
     * @param string $json
     * @param bool $assoc
     * @param mixed $default
     * @param string $context
     * @return mixed
     */
    public function safeJsonDecode(string $json, bool $assoc = true, $default = null, string $context = '')
    {
        try {
            if (empty($json)) {
                return $default;
            }

            $decoded = json_decode($json, $assoc);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('JSON decode error: ' . json_last_error_msg());
            }

            return $decoded;
        } catch (\Exception $e) {
            $this->handleException($e, "JSON decode error in context: {$context}", [
                'json_string' => substr($json, 0, 200) . (strlen($json) > 200 ? '...' : '')
            ]);
            return $default;
        }
    }

    /**
     * Safely encode to JSON with error handling
     *
     * @param mixed $data
     * @param string $default
     * @param string $context
     * @return string
     */
    public function safeJsonEncode($data, string $default = '{}', string $context = ''): string
    {
        try {
            $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('JSON encode error: ' . json_last_error_msg());
            }

            return $encoded;
        } catch (\Exception $e) {
            $this->handleException($e, "JSON encode error in context: {$context}", [
                'data_type' => gettype($data)
            ]);
            return $default;
        }
    }

    /**
     * Validate and sanitize configuration value
     *
     * @param mixed $value
     * @param string $type
     * @param mixed $default
     * @param string $context
     * @return mixed
     */
    public function validateConfigValue($value, string $type, $default = null, string $context = '')
    {
        try {
            switch ($type) {
                case 'bool':
                case 'boolean':
                    return (bool)$value;
                
                case 'int':
                case 'integer':
                    return (int)$value;
                
                case 'float':
                case 'double':
                    return (float)$value;
                
                case 'string':
                    return (string)$value;
                
                case 'array':
                    return is_array($value) ? $value : (array)$value;
                
                case 'json':
                    return is_string($value) ? $this->safeJsonDecode($value, true, $default, $context) : $value;
                
                default:
                    return $value;
            }
        } catch (\Exception $e) {
            $this->handleException($e, "Config value validation error for type '{$type}' in context: {$context}", [
                'value' => is_scalar($value) ? $value : gettype($value)
            ]);
            return $default;
        }
    }

    /**
     * Safely call method on object with error handling
     *
     * @param object $object
     * @param string $method
     * @param array $args
     * @param mixed $default
     * @param string $context
     * @return mixed
     */
    public function safeMethodCall($object, string $method, array $args = [], $default = null, string $context = '')
    {
        try {
            if (!is_object($object)) {
                throw new \InvalidArgumentException('First parameter must be an object');
            }

            if (!method_exists($object, $method)) {
                throw new \BadMethodCallException("Method '{$method}' does not exist on " . get_class($object));
            }

            return call_user_func_array([$object, $method], $args);
        } catch (\Exception $e) {
            $this->handleException($e, "Method call error for '{$method}' in context: {$context}", [
                'object_class' => is_object($object) ? get_class($object) : gettype($object),
                'args_count' => count($args)
            ]);
            return $default;
        }
    }

    /**
     * Create a safe wrapper for configuration access
     *
     * @param callable $configGetter
     * @param mixed $default
     * @param string $context
     * @return mixed
     */
    public function safeConfigAccess(callable $configGetter, $default = null, string $context = '')
    {
        return $this->executeWithErrorHandling($configGetter, $default, "Config access: {$context}");
    }

    /**
     * Log debug information if debug mode is enabled
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debugLog(string $message, array $context = []): void
    {
        if ($this->coreHelper->isDebugModeEnabled()) {
            $this->logger->debug('[MAB Debug] ' . $message, $context);
        }
    }

    /**
     * Log info message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function infoLog(string $message, array $context = []): void
    {
        if ($this->coreHelper->isLoggingEnabled()) {
            $this->logger->info('[MAB Info] ' . $message, $context);
        }
    }

    /**
     * Log warning message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warningLog(string $message, array $context = []): void
    {
        if ($this->coreHelper->isLoggingEnabled()) {
            $this->logger->warning('[MAB Warning] ' . $message, $context);
        }
    }

    /**
     * Log error message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function errorLog(string $message, array $context = []): void
    {
        $this->logger->error('[MAB Error] ' . $message, $context);
    }
}