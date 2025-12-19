<?php
/**
 * Authentication Middleware
 * Restaurant POS System
 */

namespace App\Http\Middleware;

use App\Http\Request;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Support\Facades\Log;

class AuthMiddleware
{
    /**
     * Handle the request
     */
    public function handle(Request $request)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            // Check remember token
            if (!Auth::checkRememberToken()) {
                // Store intended URL
                Session::set('intended_url', $request->url());
                
                // Redirect to login
                throw new \App\Exceptions\UnauthorizedException('يجب تسجيل الدخول أولاً');
            }
        }
        
        // Log successful authentication check
        if (Auth::check()) {
            Log::auth('access', Auth::id(), $request->ip());
        }
        
        return true;
    }
}