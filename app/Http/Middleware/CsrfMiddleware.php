<?php
/**
 * CSRF Protection Middleware
 * Restaurant POS System
 */

namespace App\Http\Middleware;

use App\Http\Request;
use App\Support\Facades\Session;
use App\Support\Facades\Log;

class CsrfMiddleware
{
    protected $except = [
        '/webhooks/payment',
        '/api/webhooks',
    ];

    /**
     * Handle the request
     */
    public function handle(Request $request)
    {
        // Skip CSRF check for certain methods
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return true;
        }
        
        // Skip CSRF check for excepted routes
        foreach ($this->except as $except) {
            if (strpos($request->path(), $except) === 0) {
                return true;
            }
        }
        
        // Get token from request
        $token = $request->token();
        
        if (!$token) {
            Log::warning('CSRF token missing', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'uri' => $request->uri()
            ]);
            
            throw new \App\Exceptions\CsrfTokenException('رمز الحماية مطلوب');
        }
        
        // Verify token
        if (!Session::verifyCsrfToken($token)) {
            Log::warning('CSRF token mismatch', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'uri' => $request->uri(),
                'provided_token' => $token
            ]);
            
            throw new \App\Exceptions\CsrfTokenException('رمز الحماية غير صحيح');
        }
        
        return true;
    }
}