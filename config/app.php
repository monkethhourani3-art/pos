<?php
/**
 * Application Configuration
 * Restaurant POS System
 */

return [
    'name' => 'Restaurant POS System',
    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'Asia/Baghdad',
    'locale' => 'ar',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'maintenance' => [
        'driver' => 'file',
    ],
    
    'providers' => [
        // Core Service Providers
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
    ],
    
    'aliases' => [
        'App' => Illuminate\Support\Facades\App::class,
        'Arr' => Illuminate\Support\Arr::class,
        'Artisan' => Illuminate\Support\Facades\Artisan::class,
        'Auth' => Illuminate\Support\Facades\Auth::class,
        'Blade' => Illuminate\Support\Facades\Blade::class,
        'Broadcast' => Illuminate\Support\Facades\Broadcast::class,
        'Bus' => Illuminate\Support\Facades\Bus::class,
        'Cache' => Illuminate\Support\Facades\Cache::class,
        'Config' => Illuminate\Support\Facades\Config::class,
        'Cookie' => Illuminate\Support\Facades\Cookie::class,
        'Crypt' => Illuminate\Support\Facades\Crypt::class,
        'DB' => Illuminate\Support\Facades\DB::class,
        'Eloquent' => Illuminate\Database\Eloquent\Model::class,
        'Event' => Illuminate\Support\Facades\Event::class,
        'File' => Illuminate\Support\Facades\File::class,
        'Gate' => Illuminate\Support\Facades\Gate::class,
        'Hash' => Illuminate\Support\Facades\Hash::class,
        'Http' => Illuminate\Support\Facades\Http::class,
        'Lang' => Illuminate\Support\Facades\Lang::class,
        'Log' => Illuminate\Support\Facades\Log::class,
        'Mail' => Illuminate\Support\Facades\Mail::class,
        'Notification' => Illuminate\Support\Facades\Notification::class,
        'Password' => Illuminate\Support\Facades\Password::class,
        'Queue' => Illuminate\Support\Facades\Queue::class,
        'Redirect' => Illuminate\Support\Facades\Redirect::class,
        'Redis' => Illuminate\Support\Facades\Redis::class,
        'Request' => Illuminate\Support\Facades\Request::class,
        'Response' => Illuminate\Support\Facades\Response::class,
        'Route' => Illuminate\Support\Facades\Route::class,
        'Schema' => Illuminate\Support\Facades\Schema::class,
        'Session' => Illuminate\Support\Facades\Session::class,
        'Storage' => Illuminate\Support\Facades\Storage::class,
        'Str' => Illuminate\Support\Str::class,
        'URL' => Illuminate\Support\Facades\URL::class,
        'Validator' => Illuminate\Support\Facades\Validator::class,
        'View' => Illuminate\Support\Facades\View::class,
    ],
    
    'restaurant' => [
        'name' => env('RESTAURANT_NAME', 'Restaurant POS'),
        'currency' => env('CURRENCY', 'IQD'),
        'currency_symbol' => env('CURRENCY_SYMBOL', 'د.ع'),
        'tax_rate' => env('TAX_RATE', 15.0),
        'service_charge' => env('SERVICE_CHARGE', 10.0),
        'kitchen_polling_interval' => env('KITCHEN_POLLING_INTERVAL', 3),
        'receipt_footer' => env('RECEIPT_FOOTER', 'Thank you for your visit'),
        'timezone' => 'Asia/Baghdad',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i:s',
        'language' => env('APP_LOCALE', 'ar'),
    ],
    
    'security' => [
        'password_min_length' => 8,
        'session_lifetime' => 120, // minutes
        'max_login_attempts' => 5,
        'lockout_duration' => 15, // minutes
        'csrf_token_lifetime' => 3600, // seconds
        'rate_limit_requests' => 60,
        'rate_limit_window' => 60, // seconds
    ],
    
    'filesystems' => [
        'default' => env('FILESYSTEM_DISK', 'local'),
        'disks' => [
            'local' => [
                'driver' => 'local',
                'root' => storage_path('app'),
            ],
            'public' => [
                'driver' => 'local',
                'root' => storage_path('app/public'),
                'url' => env('APP_URL').'/storage',
                'visibility' => 'public',
            ],
            'uploads' => [
                'driver' => 'local',
                'root' => public_path('uploads'),
                'visibility' => 'public',
            ],
        ],
    ],
    
    'logging' => [
        'default' => env('LOG_CHANNEL', 'stack'),
        'channels' => [
            'stack' => [
                'driver' => 'stack',
                'channels' => ['daily'],
                'ignore_exceptions' => false,
            ],
            'single' => [
                'driver' => 'single',
                'path' => storage_path('logs/app.log'),
                'level' => 'debug',
            ],
            'daily' => [
                'driver' => 'daily',
                'path' => storage_path('logs/app.log'),
                'level' => 'debug',
                'days' => 14,
            ],
            'slack' => [
                'driver' => 'slack',
                'url' => env('LOG_SLACK_WEBHOOK_URL'),
                'username' => 'Laravel Log',
                'emoji' => ':boom:',
                'level' => 'critical',
            ],
        ],
    ],
];