<?php
/**
 * Restaurant POS System - Entry Point
 * PHP 8.2+ Required
 */

// Error reporting for development
if (getenv('APP_ENV') !== 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set timezone
date_default_timezone_set('Asia/Baghdad');

// Define constants
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('PUBLIC_PATH', __DIR__);

// Autoload dependencies
require_once ROOT_PATH . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
if (file_exists(ROOT_PATH . '/.env')) {
    $dotenv->load();
}

// Bootstrap application
try {
    // Initialize session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Initialize application
    $app = new App\Application();
    $app->boot();
    
    // Handle request
    $response = $app->handle($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    
    // Send response
    $response->send();
    
} catch (Exception $e) {
    // Log error
    error_log("Application Error: " . $e->getMessage());
    
    // Show error page in production
    if (getenv('APP_ENV') === 'production') {
        http_response_code(500);
        echo '<!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>خطأ في النظام</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px auto; max-width: 500px; }
            </style>
        </head>
        <body>
            <div class="error">
                <h1>عذراً، حدث خطأ في النظام</h1>
                <p>يرجى المحاولة مرة أخرى لاحقاً أو الاتصال بالدعم الفني</p>
            </div>
        </body>
        </html>';
    } else {
        // Show detailed error in development
        echo '<h1>Application Error</h1>';
        echo '<pre>' . $e->getMessage() . '</pre>';
        echo '<pre>' . $e->getTraceAsString() . '</pre>';
    }
}