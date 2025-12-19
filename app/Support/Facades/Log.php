<?php
/**
 * Log Facade
 * Restaurant POS System
 */

namespace App\Support\Facades;

class Log
{
    protected static $logFile;
    protected static $levels = [
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
        'alert' => 6,
        'emergency' => 7
    ];

    /**
     * Initialize logging
     */
    public static function init()
    {
        self::$logFile = STORAGE_PATH . '/logs/app.log';
        
        // Create logs directory if it doesn't exist
        if (!file_exists(dirname(self::$logFile))) {
            mkdir(dirname(self::$logFile), 0755, true);
        }
    }

    /**
     * Log debug message
     */
    public static function debug($message, $context = [])
    {
        self::log('debug', $message, $context);
    }

    /**
     * Log info message
     */
    public static function info($message, $context = [])
    {
        self::log('info', $message, $context);
    }

    /**
     * Log notice message
     */
    public static function notice($message, $context = [])
    {
        self::log('notice', $message, $context);
    }

    /**
     * Log warning message
     */
    public static function warning($message, $context = [])
    {
        self::log('warning', $message, $context);
    }

    /**
     * Log error message
     */
    public static function error($message, $context = [])
    {
        self::log('error', $message, $context);
    }

    /**
     * Log critical message
     */
    public static function critical($message, $context = [])
    {
        self::log('critical', $message, $context);
    }

    /**
     * Log alert message
     */
    public static function alert($message, $context = [])
    {
        self::log('alert', $message, $context);
    }

    /**
     * Log emergency message
     */
    public static function emergency($message, $context = [])
    {
        self::log('emergency', $message, $context);
    }

    /**
     * Log message with level
     */
    public static function log($level, $message, $context = [])
    {
        self::init();
        
        if (!isset(self::$levels[$level])) {
            $level = 'info';
        }
        
        // Check minimum log level
        $minLevel = env('LOG_LEVEL', 'debug');
        if (self::$levels[$level] < self::$levels[$minLevel]) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        
        $logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;
        
        // Write to file
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Also log to database if enabled
        if (env('LOG_DATABASE', false)) {
            self::logToDatabase($level, $message, $context);
        }
    }

    /**
     * Log to database
     */
    protected static function logToDatabase($level, $message, $context = [])
    {
        try {
            $app = app();
            if (!$app) {
                return;
            }
            
            $db = $app->getDatabase();
            
            $db->insert('audit_logs', [
                'action' => $level,
                'old_values' => null,
                'new_values' => json_encode(['message' => $message, 'context' => $context]),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (\Exception $e) {
            // Silently fail if database logging fails
            file_put_contents(self::$logFile, "[{$timestamp}] WARNING: Failed to log to database: {$e->getMessage()}" . PHP_EOL, FILE_APPEND);
        }
    }

    /**
     * Get log file content
     */
    public static function getLogContent($lines = 100)
    {
        self::init();
        
        if (!file_exists(self::$logFile)) {
            return [];
        }
        
        $content = file_get_contents(self::$logFile);
        $logLines = explode(PHP_EOL, $content);
        
        return array_slice(array_filter($logLines), -$lines);
    }

    /**
     * Clear log file
     */
    public static function clear()
    {
        self::init();
        file_put_contents(self::$logFile, '');
    }

    /**
     * Get log file size
     */
    public static function getLogSize()
    {
        self::init();
        return file_exists(self::$logFile) ? filesize(self::$logFile) : 0;
    }

    /**
     * Get available log levels
     */
    public static function getLevels()
    {
        return array_keys(self::$levels);
    }

    /**
     * Get current log level
     */
    public static function getCurrentLevel()
    {
        return env('LOG_LEVEL', 'debug');
    }

    /**
     * Set log level
     */
    public static function setLevel($level)
    {
        if (isset(self::$levels[$level])) {
            $_ENV['LOG_LEVEL'] = $level;
        }
    }

    /**
     * Check if level should be logged
     */
    public static function shouldLog($level)
    {
        if (!isset(self::$levels[$level])) {
            return false;
        }
        
        $currentLevel = self::getCurrentLevel();
        return self::$levels[$level] >= self::$levels[$currentLevel];
    }

    /**
     * Format log entry
     */
    protected static function formatLogEntry($level, $message, $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        
        return "[{$timestamp}] {$level}: {$message}{$contextStr}";
    }

    /**
     * Log authentication events
     */
    public static function auth($action, $userId = null, $ip = null)
    {
        $message = "Authentication {$action}";
        $context = [
            'user_id' => $userId,
            'ip_address' => $ip ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        ];
        
        self::info($message, $context);
    }

    /**
     * Log business events
     */
    public static function business($action, $data = [])
    {
        $message = "Business event: {$action}";
        $context = array_merge($data, [
            'user_id' => auth()->id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        self::info($message, $context);
    }

    /**
     * Log system events
     */
    public static function system($message, $context = [])
    {
        $context = array_merge($context, [
            'source' => 'system',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        self::info($message, $context);
    }

    /**
     * Log error with stack trace
     */
    public static function errorWithTrace($message, $exception = null)
    {
        $context = [];
        
        if ($exception instanceof \Exception) {
            $context = [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        } elseif (is_string($exception)) {
            $context['trace'] = $exception;
        }
        
        self::error($message, $context);
    }
}