<?php
/**
 * Global Helper Functions
 * Restaurant POS System
 */

if (!function_exists('env')) {
    /**
     * Get environment variable
     */
    function env($key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);
        return $value !== false ? $value : $default;
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     */
    function config($key = null, $default = null)
    {
        static $config = [];
        
        if ($key === null) {
            return $config;
        }
        
        if (empty($config)) {
            $config = require CONFIG_PATH . '/app.php';
        }
        
        return $config[$key] ?? $default;
    }
}

if (!function_exists('asset')) {
    /**
     * Generate asset URL
     */
    function asset($path)
    {
        $baseUrl = rtrim(env('APP_URL', '/'), '/');
        return $baseUrl . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('url')) {
    /**
     * Generate URL
     */
    function url($path = '')
    {
        $baseUrl = rtrim(env('APP_URL', '/'), '/');
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

if (!function_exists('redirect')) {
    /**
     * Create redirect response
     */
    function redirect($path = '/')
    {
        return new \App\Http\Response('', 302, [
            'Location' => url($path)
        ]);
    }
}

if (!function_exists('old')) {
    /**
     * Get old input value
     */
    function old($key, $default = null)
    {
        return \App\Support\Facades\Session::get('_old_input.' . $key, $default);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Generate CSRF token
     */
    function csrf_token()
    {
        return \App\Support\Facades\Session::get('_csrf_token');
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate CSRF field
     */
    function csrf_field()
    {
        $token = csrf_token();
        return '<input type="hidden" name="_token" value="' . $token . '">';
    }
}

if (!function_exists('format_currency')) {
    /**
     * Format currency
     */
    function format_currency($amount, $symbol = null)
    {
        $currency = $symbol ?: config('restaurant.currency_symbol', 'د.ع');
        $position = config('restaurant.currency_position', 'after');
        
        $formatted = number_format($amount, 0);
        
        if ($position === 'before') {
            return $currency . ' ' . $formatted;
        } else {
            return $formatted . ' ' . $currency;
        }
    }
}

if (!function_exists('format_date')) {
    /**
     * Format date
     */
    function format_date($date, $format = null)
    {
        if (!$format) {
            $format = config('restaurant.date_format', 'Y-m-d');
        }
        
        if ($date instanceof \DateTime) {
            return $date->format($format);
        }
        
        return date($format, strtotime($date));
    }
}

if (!function_exists('format_time')) {
    /**
     * Format time
     */
    function format_time($time, $format = null)
    {
        if (!$format) {
            $format = config('restaurant.time_format', 'H:i:s');
        }
        
        if ($time instanceof \DateTime) {
            return $time->format($format);
        }
        
        return date($format, strtotime($time));
    }
}

if (!function_exists('format_datetime')) {
    /**
     * Format date and time
     */
    function format_datetime($datetime, $format = null)
    {
        if (!$format) {
            $format = config('restaurant.date_format', 'Y-m-d') . ' ' . config('restaurant.time_format', 'H:i:s');
        }
        
        if ($datetime instanceof \DateTime) {
            return $datetime->format($format);
        }
        
        return date($format, strtotime($datetime));
    }
}

if (!function_exists('generate_order_number')) {
    /**
     * Generate order number
     */
    function generate_order_number($date = null)
    {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        // Get next sequence number for today
        $app = new \App\Application();
        $db = $app->getDatabase();
        
        $prefix = str_replace('-', '', $date);
        $lastOrder = $db->fetchOne(
            "SELECT order_number FROM orders WHERE DATE(created_at) = ? ORDER BY order_number DESC LIMIT 1",
            [$date]
        );
        
        $sequence = 1;
        if ($lastOrder && $lastOrder->order_number) {
            $lastSequence = (int) substr($lastOrder->order_number, -4);
            $sequence = $lastSequence + 1;
        }
        
        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('generate_invoice_number')) {
    /**
     * Generate invoice number
     */
    function generate_invoice_number($date = null)
    {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        $app = new \App\Application();
        $db = $app->getDatabase();
        
        $prefix = 'INV-' . str_replace('-', '', $date);
        $lastInvoice = $db->fetchOne(
            "SELECT invoice_number FROM invoices WHERE DATE(created_at) = ? ORDER BY invoice_number DESC LIMIT 1",
            [$date]
        );
        
        $sequence = 1;
        if ($lastInvoice && $lastInvoice->invoice_number) {
            $lastSequence = (int) substr($lastInvoice->invoice_number, -4);
            $sequence = $lastSequence + 1;
        }
        
        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('is_rtl')) {
    /**
     * Check if current language is RTL
     */
    function is_rtl($locale = null)
    {
        if ($locale === null) {
            $locale = app()->getConfig('restaurant.language', 'ar');
        }
        
        $rtlLocales = ['ar', 'he', 'fa', 'ur'];
        return in_array($locale, $rtlLocales);
    }
}

if (!function_exists('app')) {
    /**
     * Get application instance
     */
    function app()
    {
        return \App\Application::getInstance();
    }
}

if (!function_exists('auth')) {
    /**
     * Get auth instance
     */
    function auth()
    {
        return \App\Support\Facades\Auth::instance();
    }
}

if (!function_exists('logger')) {
    /**
     * Log a message
     */
    function logger($message, $level = 'info', $context = [])
    {
        \App\Support\Facades\Log::log($level, $message, $context);
    }
}

if (!function_exists('abort')) {
    /**
     * Abort with HTTP error
     */
    function abort($code, $message = null)
    {
        throw new \App\Exceptions\HttpException($code, $message);
    }
}

if (!function_exists('abort_if')) {
    /**
     * Abort if condition is true
     */
    function abort_if($condition, $code, $message = null)
    {
        if ($condition) {
            abort($code, $message);
        }
    }
}

if (!function_exists('abort_unless')) {
    /**
     * Abort unless condition is true
     */
    function abort_unless($condition, $code, $message = null)
    {
        if (!$condition) {
            abort($code, $message);
        }
    }
}

if (!function_exists('validate')) {
    /**
     * Validate data
     */
    function validate($data, $rules)
    {
        $validator = new \App\Validation\Validator($data, $rules);
        return $validator->validate();
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and die
     */
    function dd(...$vars)
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
        die(1);
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML
     */
    function e($value)
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}