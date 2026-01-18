<?php
class User {
    public $id;
    public $name;
    public $code;
    public $username;
    public $password_hash;
    public $position;
    public $permissions = [];
    public $parent_id = null;
    public $subordinates = [];
    public $created_at;
    public $updated_at;
    public $is_active = true;
    public $last_login = null;
    
    // 权限列表
    const PERMISSIONS = [
        'user_create' => '创建用户',
        'user_edit' => '编辑用户',
        'user_delete' => '删除用户',
        'user_view' => '查看用户',
        'todo_manage' => '管理待办',
        'todo_check' => '标记待办',
        'chat_send' => '发送消息',
        'chat_delete' => '删除消息',
        'chat_group' => '群组聊天',
        'application_create' => '创建申请',
        'application_delete' => '删除申请',
        'application_approve' => '批准申请',
        'application_manage' => '管理申请',
        'permission_assign' => '分配权限',
        'system_config' => '系统配置',
        'notification_view' => '查看通知',
        'notification_delete' => '删除通知'
    ];
    
    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        
        if (empty($this->id)) {
            $this->id = 'user_' . uniqid();
        }
        
        if (empty($this->created_at)) {
            $this->created_at = date('Y-m-d H:i:s');
        }
        
        $this->updated_at = date('Y-m-d H:i:s');
    }
    
    public function save() {
        $filename = USERS_DIR . $this->id . '.json';
        $data = get_object_vars($this);
        return file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    public static function load($user_id) {
        $filename = USERS_DIR . $user_id . '.json';
        if (file_exists($filename)) {
            $data = json_decode(file_get_contents($filename), true);
            if ($data !== null) {
                return new User($data);
            }
        }
        
        // 如果找不到文件，遍历所有用户文件查找匹配的ID
        $files = glob(USERS_DIR . '*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['id']) && $data['id'] === $user_id) {
                return new User($data);
            }
        }
        
        return null;
    }
    
    public static function loadByUsername($username) {
        $files = glob(USERS_DIR . '*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['username']) && $data['username'] === $username) {
                return new User($data);
            }
        }
        return null;
    }
    
    public static function getAllUsers() {
        $users = [];
        $files = glob(USERS_DIR . '*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $users[] = new User($data);
            }
        }
        return $users;
    }
    
    public static function getAdmins() {
        $admins = [];
        $users = self::getAllUsers();
        foreach ($users as $user) {
            if ($user->isAdmin() || $user->hasPermission('application_approve')) {
                $admins[] = $user;
            }
        }
        return $admins;
    }
    
    public function delete() {
        $filename = USERS_DIR . $this->id . '.json';
        if (file_exists($filename)) {
            return unlink($filename);
        }
        return false;
    }
    
    public function isAdmin() {
        return $this->username === 'admin';
    }
    
    public function hasPermission($permission) {
        if ($this->isAdmin()) return true;
        return in_array($permission, $this->permissions);
    }
    
    public function canOperateUser($target_user) {
        if ($this->isAdmin()) return true;
        if ($this->id === $target_user->id) return false;
        return $this->isSuperiorOf($target_user);
    }
    
    public function isSuperiorOf($target_user) {
        $current_parent_id = $target_user->parent_id;
        while ($current_parent_id !== null) {
            if ($current_parent_id === $this->id) return true;
            $parent = self::load($current_parent_id);
            if (!$parent) break;
            $current_parent_id = $parent->parent_id;
        }
        return false;
    }
    
    public function getSubordinates($include_indirect = true) {
        $all = [];
        $direct = [];
        
        // 获取直接下属
        foreach ($this->subordinates as $sub_id) {
            $sub = self::load($sub_id);
            if ($sub) {
                $direct[] = $sub;
                $all[] = $sub;
            }
        }
        
        if ($include_indirect) {
            foreach ($direct as $sub) {
                $all = array_merge($all, $sub->getSubordinates(true));
            }
        }
        
        return $all;
    }
    
    public function getSubordinateIds($include_indirect = true) {
        $subordinates = $this->getSubordinates($include_indirect);
        return array_map(function($user) {
            return $user->id;
        }, $subordinates);
    }
    
    public static function createUser($data, $creator) {
        $user = new User($data);
        $user->parent_id = $creator->id;
        
        if ($user->save()) {
            $creator->subordinates[] = $user->id;
            $creator->save();
            return $user;
        }
        return false;
    }
    
    public function updatePermissions($new_permissions, $modifier) {
        if (!$modifier->hasPermission('permission_assign')) return false;
        
        // 检查新权限是否是修改者权限的子集
        foreach ($new_permissions as $permission) {
            if (!$modifier->hasPermission($permission)) {
                return false;
            }
        }
        
        $this->permissions = $new_permissions;
        $this->updated_at = date('Y-m-d H:i:s');
        return $this->save();
    }
}
?>
