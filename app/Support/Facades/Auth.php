<?php
/**
 * Authentication Facade
 * Restaurant POS System
 */

namespace App\Support\Facades;

use App\Support\Facades\Facades;

class Auth extends Facades
{
    protected static $app;
    protected static $instance;

    /**
     * Initialize authentication
     */
    public static function boot()
    {
        self::$app = app();
        self::startSession();
    }

    /**
     * Start user session
     */
    protected static function startSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate session ID periodically for security
        if (!isset($_SESSION['_last_regeneration'])) {
            $_SESSION['_last_regeneration'] = time();
        } elseif (time() - $_SESSION['_last_regeneration'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['_last_regeneration'] = time();
        }
    }

    /**
     * Attempt to login user
     */
    public static function attempt($credentials, $remember = false)
    {
        $db = self::$app->getDatabase();
        
        // Get user by username or email
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1 AND deleted_at IS NULL",
            [$credentials['username'], $credentials['username']]
        );
        
        if (!$user) {
            return false;
        }
        
        // Check password
        if (!password_verify($credentials['password'], $user->password_hash)) {
            // Increment login attempts
            self::incrementLoginAttempts($user->id);
            return false;
        }
        
        // Check if account is locked
        if ($user->locked_until && $user->locked_until > date('Y-m-d H:i:s')) {
            return false;
        }
        
        // Login successful
        self::login($user, $remember);
        
        // Reset login attempts
        self::resetLoginAttempts($user->id);
        
        // Update last login
        $db->update('users', 
            ['last_login_at' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$user->id]
        );
        
        return true;
    }

    /**
     * Login user
     */
    public static function login($user, $remember = false)
    {
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_data'] = [
            'id' => $user->id,
            'username' => $user->username,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'branch_id' => $user->branch_id
        ];
        
        // Get user roles and permissions
        $roles = self::getUserRoles($user->id);
        $_SESSION['user_roles'] = $roles['roles'];
        $_SESSION['user_permissions'] = $roles['permissions'];
        
        // Regenerate session ID
        session_regenerate_id(true);
        
        // Remember user if requested
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expires = time() + (30 * 24 * 60 * 60); // 30 days
            
            setcookie('remember_token', $token, $expires, '/', '', false, true);
            
            // Store token in database
            $db = self::$app->getDatabase();
            $db->insert('remember_tokens', [
                'user_id' => $user->id,
                'token' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', $expires)
            ]);
        }
    }

    /**
     * Logout user
     */
    public static function logout()
    {
        // Clear remember token
        if (isset($_COOKIE['remember_token'])) {
            $db = self::$app->getDatabase();
            $db->delete('remember_tokens', 'token = ?', [hash('sha256', $_COOKIE['remember_token'])]);
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        // Clear session
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        
        // Destroy session
        session_destroy();
    }

    /**
     * Check if user is authenticated
     */
    public static function check()
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get authenticated user
     */
    public static function user()
    {
        if (!self::check()) {
            return null;
        }
        
        // Return user data from session
        return (object) $_SESSION['user_data'];
    }

    /**
     * Get user ID
     */
    public static function id()
    {
        return self::check() ? $_SESSION['user_id'] : null;
    }

    /**
     * Check if user has role
     */
    public static function hasRole($role)
    {
        if (!self::check()) {
            return false;
        }
        
        $userRoles = $_SESSION['user_roles'] ?? [];
        return in_array($role, $userRoles);
    }

    /**
     * Check if user has permission
     */
    public static function can($permission)
    {
        if (!self::check()) {
            return false;
        }
        
        $userPermissions = $_SESSION['user_permissions'] ?? [];
        return in_array($permission, $userPermissions);
    }

    /**
     * Get user roles and permissions
     */
    protected static function getUserRoles($userId)
    {
        $db = self::$app->getDatabase();
        
        $roles = $db->fetchAll("
            SELECT r.name 
            FROM roles r 
            JOIN user_roles ur ON r.id = ur.role_id 
            WHERE ur.user_id = ? AND r.is_active = 1
        ", [$userId]);
        
        $permissions = $db->fetchAll("
            SELECT DISTINCT p.name 
            FROM permissions p 
            JOIN role_permissions rp ON p.id = rp.permission_id 
            JOIN user_roles ur ON rp.role_id = ur.role_id 
            WHERE ur.user_id = ? AND p.id IS NOT NULL
        ", [$userId]);
        
        return [
            'roles' => array_column($roles, 'name'),
            'permissions' => array_column($permissions, 'name')
        ];
    }

    /**
     * Increment login attempts
     */
    protected static function incrementLoginAttempts($userId)
    {
        $db = self::$app->getDatabase();
        $user = $db->fetchOne("SELECT login_attempts, locked_until FROM users WHERE id = ?", [$userId]);
        
        $attempts = ($user->login_attempts ?? 0) + 1;
        $lockUntil = null;
        
        // Lock account after max attempts
        if ($attempts >= 5) {
            $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        }
        
        $db->update('users', [
            'login_attempts' => $attempts,
            'locked_until' => $lockUntil
        ], 'id = ?', [$userId]);
    }

    /**
     * Reset login attempts
     */
    protected static function resetLoginAttempts($userId)
    {
        $db = self::$app->getDatabase();
        $db->update('users', [
            'login_attempts' => 0,
            'locked_until' => null
        ], 'id = ?', [$userId]);
    }

    /**
     * Check remember token
     */
    public static function checkRememberToken()
    {
        if (isset($_COOKIE['remember_token']) && !self::check()) {
            $db = self::$app->getDatabase();
            
            $token = $db->fetchOne("
                SELECT u.* 
                FROM users u 
                JOIN remember_tokens rt ON u.id = rt.user_id 
                WHERE rt.token = ? AND rt.expires_at > NOW() AND u.is_active = 1
            ", [hash('sha256', $_COOKIE['remember_token'])]);
            
            if ($token) {
                self::login($token, true);
                return true;
            }
        }
        
        return false;
    }

    /**
     * Require authentication
     */
    public static function requireAuth()
    {
        if (!self::check()) {
            throw new \App\Exceptions\UnauthorizedException();
        }
    }

    /**
     * Require role
     */
    public static function requireRole($role)
    {
        self::requireAuth();
        
        if (!self::hasRole($role)) {
            throw new \App\Exceptions\ForbiddenException();
        }
    }

    /**
     * Require permission
     */
    public static function requirePermission($permission)
    {
        self::requireAuth();
        
        if (!self::can($permission)) {
            throw new \App\Exceptions\ForbiddenException();
        }
    }
}