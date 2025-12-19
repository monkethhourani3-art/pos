<?php
/**
 * Permission Middleware
 * Restaurant POS System
 */

namespace App\Http\Middleware;

use App\Http\Request;
use App\Support\Facades\Auth;
use App\Support\Facades\Log;

class PermissionMiddleware
{
    /**
     * Handle the request
     */
    public function handle(Request $request)
    {
        // Get permission from route parameters
        $permission = $this->getPermissionFromRequest($request);
        
        if (!$permission) {
            return true; // No permission required
        }
        
        // Check if user has the required permission
        if (!Auth::can($permission)) {
            Log::warning('Permission denied', [
                'user_id' => Auth::id(),
                'permission' => $permission,
                'uri' => $request->uri(),
                'method' => $request->method()
            ]);
            
            throw new \App\Exceptions\ForbiddenException('ليس لديك صلاحية للوصول إلى هذا المورد');
        }
        
        return true;
    }

    /**
     * Extract permission from request
     */
    protected function getPermissionFromRequest(Request $request)
    {
        // Get permission from route parameters
        $routePermission = $request->getAttribute('permission');
        if ($routePermission) {
            return $routePermission;
        }
        
        // Try to extract from URI
        $uri = $request->path();
        $segments = explode('/', trim($uri, '/'));
        
        if (count($segments) >= 2) {
            $resource = $segments[0];
            $action = $segments[1] ?? 'index';
            
            // Map common actions to permissions
            $actionMap = [
                'index' => 'view',
                'show' => 'view',
                'create' => 'manage',
                'store' => 'manage',
                'edit' => 'manage',
                'update' => 'manage',
                'destroy' => 'manage',
                'delete' => 'manage',
                'toggle' => 'manage'
            ];
            
            $permissionAction = $actionMap[$action] ?? $action;
            return $resource . '.' . $permissionAction;
        }
        
        return null;
    }
}