<?php
/**
 * Guest Middleware
 * Restaurant POS System
 */

namespace App\Http\Middleware;

use App\Http\Request;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;

class GuestMiddleware
{
    /**
     * Handle the request
     */
    public function handle(Request $request)
    {
        // Redirect authenticated users away from guest pages
        if (Auth::check()) {
            $intendedUrl = Session::get('intended_url', '/dashboard');
            return redirect($intendedUrl);
        }
        
        return true;
    }
}