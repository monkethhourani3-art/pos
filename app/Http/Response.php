<?php
/**
 * Response Class
 * Restaurant POS System
 */

namespace App\Http;

class Response
{
    protected $content;
    protected $status = 200;
    protected $headers = [];
    protected $version = '1.1';

    public function __construct($content = '', $status = 200, array $headers = [])
    {
        $this->setContent($content);
        $this->setStatusCode($status);
        $this->setHeaders($headers);
    }

    /**
     * Create JSON response
     */
    public static function json($data, $status = 200, array $headers = [])
    {
        $headers = array_merge(['Content-Type' => 'application/json; charset=UTF-8'], $headers);
        
        return new static(json_encode($data, JSON_UNESCAPED_UNICODE), $status, $headers);
    }

    /**
     * Create HTML response
     */
    public static function html($content, $status = 200, array $headers = [])
    {
        $headers = array_merge(['Content-Type' => 'text/html; charset=UTF-8'], $headers);
        return new static($content, $status, $headers);
    }

    /**
     * Create redirect response
     */
 redirect($url    public static function, $status = 302)
    {
        $headers = ['Location' => $url];
        return new static('', $status, $headers);
    }

    /**
     * Create error response
     */
    public static function error($message, $status = 500)
    {
        $content = "<!DOCTYPE html>
<html lang='ar' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>خطأ {$status}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .error { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        .error h1 { color: #e74c3c; margin-bottom: 20px; }
        .error p { color: #7f8c8d; line-height: 1.6; }
        .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='error'>
        <h1>خطأ {$status}</h1>
        <p>{$message}</p>
        <a href='/' class='btn'>العودة للرئيسية</a>
    </div>
</body>
</html>";
        
        return new static($content, $status, [
            'Content-Type' => 'text/html; charset=UTF-8'
        ]);
    }

    /**
     * Set response content
     */
    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Get response content
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set status code
     */
    public function setStatusCode($code)
    {
        $this->status = $code;
        return $this;
    }

    /**
     * Get status code
     */
    public function getStatusCode()
    {
        return $this->status;
    }

    /**
     * Set header
     */
    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Set multiple headers
     */
    public function setHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }
        return $this;
    }

    /**
     * Get header value
     */
    public function getHeader($name, $default = null)
    {
        return $this->headers[$name] ?? $default;
    }

    /**
     * Get all headers
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Set response version
     */
    public function setProtocolVersion($version)
    {
        $this->version = $version;
        return $this;
    }

    /**
     * Get response version
     */
    public function getProtocolVersion()
    {
        return $this->version;
    }

    /**
     * Send response to client
     */
    public function send()
    {
        // Send headers
        $this->sendHeaders();
        
        // Send content
        echo $this->content;
    }

    /**
     * Send HTTP headers
     */
    protected function sendHeaders()
    {
        // Status line
        $statusText = $this->getStatusText($this->status);
        header("HTTP/{$this->version} {$this->status} {$statusText}");
        
        // Headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    /**
     * Get status text
     */
    protected function getStatusText($code)
    {
        $statusTexts = [
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            414 => 'URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Range Not Satisfiable',
            417 => 'Expectation Failed',
            418 => "I'm a teapot",
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            451 => 'Unavailable For Legal Reasons',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            510 => 'Not Extended',
            511 => 'Network Authentication Required'
        ];
        
        return $statusTexts[$code] ?? 'Unknown';
    }

    /**
     * Create response with view
     */
    public static function view($view, $data = [], $status = 200, array $headers = [])
    {
        // Simple view rendering - can be enhanced with a templating engine
        $content = '';
        
        if (file_exists(APP_PATH . '/Views/' . $view . '.php')) {
            extract($data);
            ob_start();
            include APP_PATH . '/Views/' . $view . '.php';
            $content = ob_get_clean();
        }
        
        return new static($content, $status, $headers);
    }

    /**
     * Download file
     */
    public static function download($file, $filename = null, $status = 200)
    {
        if (!file_exists($file)) {
            return static::error('File not found', 404);
        }
        
        if ($filename === null) {
            $filename = basename($file);
        }
        
        $headers = [
            'Content-Type' => mime_content_type($file),
            'Content-Length' => filesize($file),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, must-revalidate',
            'Pragma' => 'public',
            'Expires' => '0'
        ];
        
        return new static(file_get_contents($file), $status, $headers);
    }

    /**
     * Get response as string
     */
    public function __toString()
    {
        return $this->content;
    }
}