<?php
/**
 * Route Class
 * Restaurant POS System
 */

namespace App\Routing;

class Route
{
    protected $methods;
    protected $uri;
    protected $action;
    protected $parameters = [];
    protected $middleware = [];
    protected $where = [];
    protected $patterns = [];
    protected $name = null;

    public function __construct($methods, $uri, $action)
    {
        $this->methods = is_array($methods) ? $methods : [$methods];
        $this->uri = $uri;
        $this->action = $action;
    }

    /**
     * Check if route matches request
     */
    public function matches($method, $uri)
    {
        // Check HTTP method
        if (!in_array($method, $this->methods) && !in_array('ANY', $this->methods)) {
            return false;
        }

        // Check URI pattern
        $regex = $this->getCompiledRegex();
        return preg_match($regex, $uri, $matches);
    }

    /**
     * Get compiled regex for route
     */
    protected function getCompiledRegex()
    {
        $regex = $this->uri;
        
        // Replace parameters with regex patterns
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function($matches) {
            $param = $matches[1];
            
            if (isset($this->where[$param])) {
                return '(' . $this->where[$param] . ')';
            }
            
            if (isset($this->patterns[$param])) {
                return '(' . $this->patterns[$param] . ')';
            }
            
            return '([^\/]+)';
        }, $regex);
        
        return '#^' . $regex . '$#';
    }

    /**
     * Extract parameters from URI
     */
    public function extractParameters($uri)
    {
        $regex = $this->getCompiledRegex();
        if (preg_match($regex, $uri, $matches)) {
            array_shift($matches); // Remove full match
            
            $parameters = [];
            $paramNames = $this->getParameterNames();
            
            foreach ($paramNames as $index => $name) {
                $parameters[$name] = $matches[$index] ?? null;
            }
            
            return $parameters;
        }
        
        return [];
    }

    /**
     * Get parameter names from URI
     */
    protected function getParameterNames()
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $this->uri, $matches);
        return $matches[1];
    }

    /**
     * Set parameter patterns
     */
    public function setPatterns($patterns)
    {
        $this->patterns = $patterns;
        return $this;
    }

    /**
     * Add parameter constraint
     */
    public function where($parameters, $pattern = null)
    {
        if (is_string($parameters)) {
            $parameters = [$parameters => $pattern];
        }
        
        $this->where = array_merge($this->where, $parameters);
        return $this;
    }

    /**
     * Set route name
     */
    public function name($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Add middleware
     */
    public function middleware($middleware)
    {
        $middleware = is_array($middleware) ? $middleware : func_get_args();
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /**
     * Get middleware
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }

    /**
     * Get route parameters
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Set route parameters
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Run route action
     */
    public function run($request = null)
    {
        $action = $this->action;
        
        if (is_callable($action)) {
            return call_user_func($action, $request);
        }
        
        if (is_array($action)) {
            $controller = new $action[0]();
            $method = $action[1];
            return call_user_func([$controller, $method], $request);
        }
        
        throw new \Exception('Invalid route action');
    }

    /**
     * Get route methods
     */
    public function getMethods()
    {
        return $this->methods;
    }

    /**
     * Get route URI
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Get route action
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Get route name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set route name
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }
}