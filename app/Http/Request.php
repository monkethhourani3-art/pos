<?php
/**
 * Request Class
 * Restaurant POS System
 */

namespace App\Http;

class Request
{
    protected $method;
    protected $uri;
    protected $headers = [];
    protected $server = [];
    protected $get = [];
    protected $post = [];
    protected $files = [];
    protected $cookies = [];
    protected $attributes = [];
    protected $user = null;

    public function __construct($method, $uri)
    {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->headers = $this->parseHeaders();
        $this->server = $_SERVER;
        $this->get = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->cookies = $_COOKIE;
    }

    /**
     * Parse HTTP headers
     */
    protected function parseHeaders()
    {
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = strtolower(substr($key, 5));
                $header = str_replace('_', '-', $header);
                $headers[$header] = $value;
            }
        }
        
        // Add content type if available
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }
        
        return $headers;
    }

    /**
     * Get request method
     */
    public function method()
    {
        return $this->method;
    }

    /**
     * Get request URI
     */
    public function uri()
    {
        return $this->uri;
    }

    /**
     * Get full URL
     */
    public function url()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'];
        return $protocol . '://' . $host . $this->uri();
    }

    /**
     * Get path (URI without query string)
     */
    public function path()
    {
        return parse_url($this->uri(), PHP_URL_PATH);
    }

    /**
     * Get query string
     */
    public function queryString()
    {
        return parse_url($this->uri(), PHP_URL_QUERY) ?? '';
    }

    /**
     * Get header value
     */
    public function header($key, $default = null)
    {
        $key = strtolower($key);
        return $this->headers[$key] ?? $default;
    }

    /**
     * Get all headers
     */
    public function headers()
    {
        return $this->headers;
    }

    /**
     * Get input value
     */
    public function input($key = null, $default = null)
    {
        if ($key === null) {
            return array_merge($this->get, $this->post);
        }
        
        if (isset($this->post[$key])) {
            return $this->post[$key];
        }
        
        if (isset($this->get[$key])) {
            return $this->get[$key];
        }
        
        return $default;
    }

    /**
     * Get GET parameter
     */
    public function get($key = null, $default = null)
    {
        if ($key === null) {
            return $this->get;
        }
        
        return $this->get[$key] ?? $default;
    }

    /**
     * Get POST parameter
     */
    public function post($key = null, $default = null)
    {
        if ($key === null) {
            return $this->post;
        }
        
        return $this->post[$key] ?? $default;
    }

    /**
     * Get file upload
     */
    public function file($key = null, $default = null)
    {
        if ($key === null) {
            return $this->files;
        }
        
        return $this->files[$key] ?? $default;
    }

    /**
     * Get cookie value
     */
    public function cookie($key = null, $default = null)
    {
        if ($key === null) {
            return $this->cookies;
        }
        
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Get server value
     */
    public function server($key = null, $default = null)
    {
        if ($key === null) {
            return $this->server;
        }
        
        return $this->server[$key] ?? $default;
    }

    /**
     * Check if request is AJAX
     */
    public function isAjax()
    {
        return strtolower($this->header('x-requested-with')) === 'xmlhttprequest';
    }

    /**
     * Check if request accepts JSON
     */
    public function acceptsJson()
    {
        $accept = $this->header('accept', '');
        return strpos($accept, 'application/json') !== false;
    }

    /**
     * Get content type
     */
    public function contentType()
    {
        return $this->header('content-type');
    }

    /**
     * Get client IP
     */
    public function ip()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get user agent
     */
    public function userAgent()
    {
        return $this->header('user-agent', '');
    }

    /**
     * Get referer
     */
    public function referer()
    {
        return $this->header('referer', '');
    }

    /**
     * Set attribute
     */
    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Get attribute
     */
    public function getAttribute($key, $default = null)
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Get all attributes
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Merge attributes
     */
    public function merge(array $attributes)
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    /**
     * Set user
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Get user
     */
    public function user()
    {
        return $this->user;
    }

    /**
     * Check if user is authenticated
     */
    public function hasUser()
    {
        return $this->user !== null;
    }

    /**
     * Get CSRF token
     */
    public function token($token = '_token')
    {
        return $this->post($token) ?: $this->get($token);
    }

    /**
     * Check if request is secure (HTTPS)
     */
    public function isSecure()
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    }

    /**
     * Get protocol
     */
    public function protocol()
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    /**
     * Get host
     */
    public function host()
    {
        return $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'];
    }

    /**
     * Get port
     */
    public function port()
    {
        return $_SERVER['SERVER_PORT'] ?? 80;
    }

    /**
     * Create new request instance
     */
    public static function capture()
    {
        return new static($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    }

    /**
     * Get request as array (for debugging)
     */
    public function toArray()
    {
        return [
            'method' => $this->method(),
            'uri' => $this->uri(),
            'path' => $this->path(),
            'headers' => $this->headers(),
            'get' => $this->get(),
            'post' => $this->post(),
            'files' => $this->file(),
            'cookies' => $this->cookie(),
            'server' => $this->server(),
            'attributes' => $this->getAttributes(),
            'user' => $this->user(),
        ];
    }
}