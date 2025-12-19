<?php
/**
 * Session Facade
 * Restaurant POS System
 */

namespace App\Support\Facades;

class Session
{
    /**
     * Start session
     */
    public static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Get session value
     */
    public static function get($key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set session value
     */
    public static function set($key, $value)
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Set multiple session values
     */
    public static function put(array $values)
    {
        self::start();
        foreach ($values as $key => $value) {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * Check if session key exists
     */
    public static function has($key)
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session value
     */
    public static function forget($key)
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Remove multiple session keys
     */
    public static function forgetMultiple(array $keys)
    {
        self::start();
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Get all session data
     */
    public static function all()
    {
        self::start();
        return $_SESSION;
    }

    /**
     * Flush session (remove all data)
     */
    public static function flush()
    {
        self::start();
        $_SESSION = [];
    }

    /**
     * Regenerate session ID
     */
    public static function regenerate()
    {
        self::start();
        session_regenerate_id(true);
    }

    /**
     * Get flash data
     */
    public static function getFlash($key, $default = null)
    {
        self::start();
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /**
     * Set flash data
     */
    public static function setFlash($key, $value)
    {
        self::start();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Keep flash data for next request
     */
    public static function keepFlash($key)
    {
        self::start();
        if (isset($_SESSION['_flash'][$key])) {
            $_SESSION['_flash_old'][$key] = $_SESSION['_flash'][$key];
        }
    }

    /**
     * Flash old input
     */
    public static function flashInput($data)
    {
        self::start();
        $_SESSION['_old_input'] = $data;
    }

    /**
     * Get old input
     */
    public static function getOldInput($key = null, $default = null)
    {
        self::start();
        if ($key === null) {
            return $_SESSION['_old_input'] ?? [];
        }
        return $_SESSION['_old_input'][$key] ?? $default;
    }

    /**
     * Get CSRF token
     */
    public static function getCsrfToken()
    {
        self::start();
        
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['_csrf_token'];
    }

    /**
     * Verify CSRF token
     */
    public static function verifyCsrfToken($token)
    {
        return hash_equals(self::getCsrfToken(), $token);
    }

    /**
     * Get token for forms
     */
    public static function token()
    {
        return self::getCsrfToken();
    }

    /**
     * Generate and store random token
     */
    public static function tokenInput()
    {
        return '<input type="hidden" name="_token" value="' . self::getCsrfToken() . '">';
    }

    /**
     * Get session ID
     */
    public static function getId()
    {
        self::start();
        return session_id();
    }

    /**
     * Get session name
     */
    public static function getName()
    {
        self::start();
        return session_name();
    }

    /**
     * Set session name
     */
    public static function setName($name)
    {
        self::start();
        session_name($name);
    }

    /**
     * Save session data
     */
    public static function save()
    {
        self::start();
        session_write_close();
    }

    /**
     * Destroy session
     */
    public static function destroy()
    {
        self::start();
        session_destroy();
        $_SESSION = [];
    }

    /**
     * Get flash messages
     */
    public static function getMessages($type = null)
    {
        self::start();
        
        if ($type === null) {
            return $_SESSION['_messages'] ?? [];
        }
        
        $messages = $_SESSION['_messages'][$type] ?? [];
        unset($_SESSION['_messages'][$type]);
        
        return $messages;
    }

    /**
     * Add flash message
     */
    public static function addMessage($type, $message)
    {
        self::start();
        
        if (!isset($_SESSION['_messages'])) {
            $_SESSION['_messages'] = [];
        }
        
        if (!isset($_SESSION['_messages'][$type])) {
            $_SESSION['_messages'][$type] = [];
        }
        
        $_SESSION['_messages'][$type][] = $message;
    }

    /**
     * Get success messages
     */
    public static function getSuccessMessages()
    {
        return self::getMessages('success');
    }

    /**
     * Add success message
     */
    public static function addSuccessMessage($message)
    {
        self::addMessage('success', $message);
    }

    /**
     * Get error messages
     */
    public static function getErrorMessages()
    {
        return self::getMessages('error');
    }

    /**
     * Add error message
     */
    public static function addErrorMessage($message)
    {
        self::addMessage('error', $message);
    }

    /**
     * Get warning messages
     */
    public static function getWarningMessages()
    {
        return self::getMessages('warning');
    }

    /**
     * Add warning message
     */
    public static function addWarningMessage($message)
    {
        self::addMessage('warning', $message);
    }

    /**
     * Get info messages
     */
    public static function getInfoMessages()
    {
        return self::getMessages('info');
    }

    /**
     * Add info message
     */
    public static function addInfoMessage($message)
    {
        self::addMessage('info', $message);
    }

    /**
     * Check if user is guest
     */
    public static function isGuest()
    {
        return !isset($_SESSION['user_id']);
    }

    /**
     * Set user in session
     */
    public static function setUser($user)
    {
        self::start();
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_data'] = [
            'id' => $user->id,
            'username' => $user->username,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email
        ];
    }

    /**
     * Get user from session
     */
    public static function getUser()
    {
        self::start();
        return $_SESSION['user_data'] ?? null;
    }

    /**
     * Remove user from session
     */
    public static function removeUser()
    {
        self::start();
        unset($_SESSION['user_id'], $_SESSION['user_data']);
    }
}