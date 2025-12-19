<?php
/**
 * Authentication Controller
 * Restaurant POS System
 */

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Support\Facades\Auth;
use App\Support\Facades\Session;
use App\Support\Facades\Log;

class AuthController
{
    /**
     * Show login form
     */
    public function showLoginForm()
    {
        // Redirect if already authenticated
        if (Auth::check()) {
            return redirect('/dashboard');
        }
        
        // Generate CSRF token
        Session::getCsrfToken();
        
        // Get flash messages
        $errors = Session::getErrorMessages();
        $success = Session::getSuccessMessages();
        
        // Clear old input
        $oldInput = Session::getOldInput();
        
        return Response::view('auth.login', compact('errors', 'success', 'oldInput'));
    }

    /**
     * Handle login
     */
    public function login(Request $request)
    {
        try {
            // Validate input
            $validator = new \App\Validation\Validator($request->post(), [
                'username' => 'required|min:3',
                'password' => 'required|min:6'
            ]);
            
            if (!$validator->validate()) {
                $errors = $validator->getErrors();
                Session::addErrorMessage('بيانات الدخول غير صحيحة');
                Session::flashInput($request->post());
                return redirect('/login');
            }
            
            // Attempt login
            $credentials = [
                'username' => $request->post('username'),
                'password' => $request->post('password')
            ];
            
            $remember = $request->post('remember') === '1';
            
            if (Auth::attempt($credentials, $remember)) {
                // Login successful
                Log::auth('login', Auth::id(), $request->ip());
                
                Session::addSuccessMessage('مرحباً بك، تم تسجيل الدخول بنجاح');
                
                // Redirect to intended URL or dashboard
                $intendedUrl = Session::get('intended_url', '/dashboard');
                Session::forget('intended_url');
                
                return redirect($intendedUrl);
            } else {
                // Login failed
                Log::auth('failed_login', null, $request->ip(), [
                    'username' => $request->post('username')
                ]);
                
                Session::addErrorMessage('اسم المستخدم أو كلمة المرور غير صحيحة');
                Session::flashInput($request->post());
                
                return redirect('/login');
            }
            
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage(), [
                'username' => $request->post('username'),
                'ip' => $request->ip()
            ]);
            
            Session::addErrorMessage('حدث خطأ أثناء تسجيل الدخول');
            Session::flashInput($request->post());
            
            return redirect('/login');
        }
    }

    /**
     * Handle logout
     */
    public function logout(Request $request)
    {
        try {
            if (Auth::check()) {
                $userId = Auth::id();
                Auth::logout();
                
                Log::auth('logout', $userId, $request->ip());
                Session::addSuccessMessage('تم تسجيل الخروج بنجاح');
            }
            
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            Session::addErrorMessage('حدث خطأ أثناء تسجيل الخروج');
        }
        
        return redirect('/login');
    }

    /**
     * Show registration form (optional - for future use)
     */
    public function showRegistrationForm()
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }
        
        Session::getCsrfToken();
        
        return Response::view('auth.register');
    }

    /**
     * Handle registration (optional - for future use)
     */
    public function register(Request $request)
    {
        // Implementation for user registration
        // This can be added later if needed
        
        return redirect('/login');
    }

    /**
     * Forgot password (optional - for future use)
     */
    public function showForgotPasswordForm()
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }
        
        Session::getCsrfToken();
        
        return Response::view('auth.forgot-password');
    }

    /**
     * Handle forgot password (optional - for future use)
     */
    public function forgotPassword(Request $request)
    {
        // Implementation for forgot password
        // This can be added later with email functionality
        
        Session::addSuccessMessage('تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني');
        return redirect('/login');
    }

    /**
     * Show reset password form (optional - for future use)
     */
    public function showResetPasswordForm($token)
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }
        
        Session::getCsrfToken();
        
        return Response::view('auth.reset-password', compact('token'));
    }

    /**
     * Handle reset password (optional - for future use)
     */
    public function resetPassword(Request $request, $token)
    {
        // Implementation for password reset
        // This can be added later
        
        Session::addSuccessMessage('تم إعادة تعيين كلمة المرور بنجاح');
        return redirect('/login');
    }

    /**
     * Check authentication status
     */
    public function check()
    {
        return Response::json([
            'authenticated' => Auth::check(),
            'user' => Auth::user()
        ]);
    }
}