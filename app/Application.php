<?php
/**
 * Application Class
 * Restaurant POS System
 */

namespace App;

use App\Http\Request;
use App\Http\Response;
use App\Routing\Router;
use App\Database\Database;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Support\Facades\Log;

class Application
{
    protected $router;
    protected $db;
    protected $config;
    protected $middleware = [];
    protected $routes = [];

    public function __construct()
    {
        $this->bootstrap();
    }

    /**
     * Bootstrap the application
     */
    protected function bootstrap()
    {
        // Load configuration
        $this->config = require CONFIG_PATH . '/app.php';
        
        // Initialize database
        $this->db = new Database();
        
        // Initialize router
        $this->router = new Router();
        
        // Load routes
        $this->loadRoutes();
        
        // Register global middleware
        $this->registerMiddleware();
    }

    /**
     * Boot the application
     */
    public function boot()
    {
        // Initialize authentication
        Auth::boot();
        
        // Load application settings
        $this->loadSettings();
        
        Log::info('Application booted successfully');
    }

    /**
     * Load routes
     */
    protected function loadRoutes()
    {
        $routesPath = APP_PATH . '/routes';
        
        if (file_exists($routesPath)) {
            $files = glob($routesPath . '/*.php');
            foreach ($files as $file) {
                require_once $file;
            }
        }
    }

    /**
     * Register global middleware
     */
    protected function registerMiddleware()
    {
        // Add middleware classes here
        $this->middleware = [
            'auth' => \App\Http\Middleware\AuthMiddleware::class,
            'guest' => \App\Http\Middleware\GuestMiddleware::class,
            'csrf' => \App\Http\Middleware\CsrfMiddleware::class,
            'permissions' => \App\Http\Middleware\PermissionMiddleware::class,
            'language' => \App\Http\Middleware\LanguageMiddleware::class,
        ];
    }

    /**
     * Load application settings
     */
    protected function loadSettings()
    {
        // Load settings from database
        try {
            $settings = $this->db->table('settings')->get();
            foreach ($settings as $setting) {
                $_ENV['SETTING_' . strtoupper($setting->key)] = $setting->value;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to load settings: ' . $e->getMessage());
        }
    }

    /**
     * Handle HTTP request
     */
    public function handle($method, $uri)
    {
        try {
            // Create request object
            $request = new Request($method, $uri);
            
            // Add user to request if authenticated
            if (Auth::check()) {
                $request->setUser(Auth::user());
            }
            
            // Find route
            $route = $this->router->match($method, $uri);
            
            if (!$route) {
                return $this->notFound();
            }
            
            // Add route parameters to request
            if ($route->getParameters()) {
                $request->merge($route->getParameters());
            }
            
            // Apply middleware
            $this->applyMiddleware($route, $request);
            
            // Execute route
            $response = $route->run($request);
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('Route execution failed: ' . $e->getMessage());
            return $this->serverError($e);
        }
    }

    /**
     * Apply middleware to route
     */
    protected function applyMiddleware($route, $request)
    {
        $middleware = $route->getMiddleware();
        
        foreach ($middleware as $middlewareName) {
            if (isset($this->middleware[$middlewareName])) {
                $middlewareClass = $this->middleware[$middlewareName];
                $middlewareInstance = new $middlewareClass();
                $middlewareInstance->handle($request);
            }
        }
    }

    /**
     * Handle 404 Not Found
     */
    protected function notFound()
    {
        return new Response('الصفحة غير موجودة', 404, [
            'Content-Type' => 'text/html; charset=UTF-8'
        ]);
    }

    /**
     * Handle 500 Server Error
     */
    protected function serverError($exception)
    {
        if (getenv('APP_ENV') === 'production') {
            return new Response('خطأ في الخادم', 500, [
                'Content-Type' => 'text/html; charset=UTF-8'
            ]);
        } else {
            $error = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
            
            return new Response('<pre>' . print_r($error, true) . '</pre>', 500, [
                'Content-Type' => 'text/html; charset=UTF-8'
            ]);
        }
    }

    /**
     * Get router instance
     */
    public function getRouter()
    {
        return $this->router;
    }

    /**
     * Get database instance
     */
    public function getDatabase()
    {
        return $this->db;
    }

    /**
     * Get configuration
     */
    public function getConfig($key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }
        
        return $this->config[$key] ?? $default;
    }

    /**
     * Get environment value
     */
    public function env($key, $default = null)
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}