<?php
/**
 * Router Class
 * Restaurant POS System
 */

namespace App\Routing;

class Router
{
    protected $routes = [];
    protected $groupStack = [];
    protected $patterns = [
        'int' => '\d+',
        'str' => '[a-zA-Z]+',
        'slug' => '[a-zA-Z0-9\-]+',
        'any' => '[^\/]+',
        'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'
    ];

    /**
     * Add GET route
     */
    public function get($uri, $action)
    {
        return $this->addRoute('GET', $uri, $action);
    }

    /**
     * Add POST route
     */
    public function post($uri, $action)
    {
        return $this->addRoute('POST', $uri, $action);
    }

    /**
     * Add PUT route
     */
    public function put($uri, $action)
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    /**
     * Add DELETE route
     */
    public function delete($uri, $action)
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    /**
     * Add PATCH route
     */
    public function patch($uri, $action)
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    /**
     * Add OPTIONS route
     */
    public function options($uri, $action)
    {
        return $this->addRoute('OPTIONS', $uri, $action);
    }

    /**
     * Add route for any method
     */
    public function any($uri, $action)
    {
        return $this->addRoute('ANY', $uri, $action);
    }

    /**
     * Add multiple routes for same URI
     */
    public function match($methods, $uri, $action)
    {
        $methods = is_string($methods) ? explode('|', $methods) : $methods;
        foreach ($methods as $method) {
            $this->addRoute($method, $uri, $action);
        }
    }

    /**
     * Add route group
     */
    public function group($attributes, $callback)
    {
        $this->groupStack[] = $this->mergeGroupAttributes($attributes);
        $callback($this);
        array_pop($this->groupStack);
    }

    /**
     * Add resource routes
     */
    public function resource($name, $controller, $only = null)
    {
        $actions = [
            'index' => 'GET',
            'create' => 'GET',
            'store' => 'POST',
            'show' => 'GET',
            'edit' => 'GET',
            'update' => 'PUT|PATCH',
            'destroy' => 'DELETE'
        ];

        if ($only) {
            $actions = array_intersect_key($actions, array_flip($only));
        }

        foreach ($actions as $action => $method) {
            $uri = $this->getResourceUri($name, $action);
            $this->addRoute($method, $uri, [$controller, $action]);
        }
    }

    /**
     * Add API resource routes
     */
    public function apiResource($name, $controller)
    {
        $actions = [
            'index' => 'GET',
            'show' => 'GET',
            'store' => 'POST',
            'update' => 'PUT|PATCH',
            'destroy' => 'DELETE'
        ];

        foreach ($actions as $action => $method) {
            $uri = $this->getResourceUri($name, $action);
            $this->addRoute($method, $uri, [$controller, $action]);
        }
    }

    /**
     * Get resource URI
     */
    protected function getResourceUri($name, $action)
    {
        $uri = '/' . $name;
        
        if (in_array($action, ['create', 'edit'])) {
            $uri .= '/' . $action;
        } elseif ($action !== 'index') {
            $uri .= '/{id}';
        }
        
        return $uri;
    }

    /**
     * Add route
     */
    protected function addRoute($method, $uri, $action)
    {
        $uri = $this->applyGroupAttributes($uri);
        
        $route = new Route($method, $uri, $action);
        $route->setPatterns($this->patterns);
        
        $this->routes[] = $route;
        
        return $route;
    }

    /**
     * Apply group attributes to URI
     */
    protected function applyGroupAttributes($uri)
    {
        $prefix = '';
        $middleware = [];
        
        foreach ($this->groupStack as $group) {
            $prefix .= '/' . trim($group['prefix'] ?? '', '/');
            
            if (isset($group['middleware'])) {
                $middleware = array_merge($middleware, (array) $group['middleware']);
            }
        }
        
        $uri = '/' . trim($prefix . '/' . trim($uri, '/'), '/');
        
        return $uri;
    }

    /**
     * Merge group attributes
     */
    protected function mergeGroupAttributes($attributes)
    {
        if (empty($this->groupStack)) {
            return $attributes;
        }
        
        $lastGroup = end($this->groupStack);
        
        return array_merge_recursive($lastGroup, $attributes);
    }

    /**
     * Match route against request
     */
    public function match($method, $uri)
    {
        foreach ($this->routes as $route) {
            if ($route->matches($method, $uri)) {
                return $route;
            }
        }
        
        return null;
    }

    /**
     * Get all routes
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Set route patterns
     */
    public function patterns($patterns)
    {
        $this->patterns = array_merge($this->patterns, $patterns);
    }

    /**
     * Generate URL for named route
     */
    public function url($name, $parameters = [])
    {
        // Implementation for named routes
        return '/';
    }

    /**
     * Generate URL with parameters
     */
    public function to($uri, $parameters = [])
    {
        $uri = '/' . ltrim($uri, '/');
        
        foreach ($parameters as $key => $value) {
            $uri = str_replace('{' . $key . '}', $value, $uri);
        }
        
        return $uri;
    }

    /**
     * Redirect to another URL
     */
    public function redirect($from, $to, $status = 302)
    {
        $route = new Route('GET', $from, function() use ($to, $status) {
            return new \App\Http\Response('', $status, [
                'Location' => $to
            ]);
        });
        
        $this->routes[] = $route;
        return $route;
    }
}