<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/models.php';

class Auth {
    public static function login($username, $password) {
        $user = User::loadByUsername($username);
        if (!$user) return ['success' => false, 'message' => '用户不存在'];
        if (!$user->is_active) return ['success' => false, 'message' => '账户已被禁用'];
        
        if (password_verify($password, $user->password_hash)) {
            $_SESSION['user_id'] = $user->id;
            $_SESSION['username'] = $user->username;
            $_SESSION['user_name'] = $user->name;
            $_SESSION['is_admin'] = $user->isAdmin();
            $_SESSION['permissions'] = $user->permissions;
            
            $user->last_login = date('Y-m-d H:i:s');
            $user->save();
            
            return ['success' => true, 'user' => $user];
        }
        
        return ['success' => false, 'message' => '密码错误'];
    }
    
    public static function logout() {
        session_destroy();
        return true;
    }
    
    public static function check() {
        return isset($_SESSION['user_id']);
    }
    
    public static function getCurrentUser() {
        if (!self::check()) return null;
        return User::load($_SESSION['user_id']);
    }
    
    public static function requireLogin() {
        if (!self::check()) {
            header('Location: index.php');
            exit();
        }
    }
    
    public static function requirePermission($permission) {
        $user = self::getCurrentUser();
        if (!$user || !$user->hasPermission($permission)) {
            header('Location: dashboard.php');
            exit();
        }
    }
    
    public static function initializeSystem($admin_name, $admin_password) {
        $system_data = json_decode(file_get_contents(SYSTEM_FILE), true);
        if ($system_data['initialized']) {
            return ['success' => false, 'message' => '系统已初始化'];
        }
        
        $admin = new User([
            'name' => $admin_name,
            'code' => 'ADMIN001',
            'username' => 'admin',
            'password_hash' => password_hash($admin_password, PASSWORD_DEFAULT),
            'position' => '系统管理员',
            'permissions' => array_keys(User::PERMISSIONS)
        ]);
        
        if ($admin->save()) {
            $system_data['initialized'] = true;
            $system_data['admin_created'] = true;
            $system_data['init_time'] = date('Y-m-d H:i:s');
            file_put_contents(SYSTEM_FILE, json_encode($system_data, JSON_PRETTY_PRINT));
            return ['success' => true];
        }
        
        return ['success' => false, 'message' => '初始化失败'];
    }
}
?>
