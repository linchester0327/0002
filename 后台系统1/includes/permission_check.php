<?php
require_once 'auth.php';

class PermissionCheck {
    public static function canPerform($action, $target_user_id = null) {
        $user = Auth::getCurrentUser();
        if (!$user) return false;
        if ($user->isAdmin()) return true;
        
        $permission_map = [
            'user_create' => 'user_create',
            'user_edit' => 'user_edit',
            'user_delete' => 'user_delete',
            'user_view' => 'user_view',
            'todo_create' => 'todo_manage',
            'todo_assign' => 'todo_manage',
            'todo_check' => 'todo_check',
            'message_send' => 'chat_send',
            'message_delete' => 'chat_delete',
            'application_create' => 'application_create',
            'application_delete' => 'application_delete',
            'permission_assign' => 'permission_assign'
        ];
        
        if (!isset($permission_map[$action])) return false;
        if (!$user->hasPermission($permission_map[$action])) return false;
        
        if ($target_user_id) {
            $target = User::load($target_user_id);
            if (!$target) return false;
            
            if ($target->id === $user->id) {
                $self_allowed = ['todo_check', 'message_send', 'application_create'];
                if (!in_array($action, $self_allowed)) return false;
            }
            
            if (!$user->canOperateUser($target)) return false;
            
            if ($action === 'permission_assign') {
                foreach ($target->permissions as $perm) {
                    if (!$user->hasPermission($perm)) return false;
                }
            }
        }
        
        return true;
    }
    
    public static function require($action, $target_user_id = null) {
        if (!self::canPerform($action, $target_user_id)) {
            header('Location: dashboard.php?msg=权限不足');
            exit();
        }
    }
    
    // 权限缓存机制
    public static function getCachedPermissions($user_id) {
        $cache_key = 'permissions_' . $user_id;
        if (isset($_SESSION[$cache_key])) {
            return $_SESSION[$cache_key];
        }
        return null;
    }
    
    public static function cachePermissions($user_id, $permissions) {
        $cache_key = 'permissions_' . $user_id;
        $_SESSION[$cache_key] = $permissions;
    }
    
    public static function clearPermissionCache($user_id) {
        $cache_key = 'permissions_' . $user_id;
        if (isset($_SESSION[$cache_key])) {
            unset($_SESSION[$cache_key]);
        }
    }
}
?>
