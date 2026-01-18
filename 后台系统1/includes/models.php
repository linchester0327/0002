<?php
// 统一模型文件 - 整合所有模型类

// 用户模型
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
    public $children = [];
    public $created_at;
    public $updated_at;
    public $is_active = true;
    public $last_login = null;
    public $is_department = false;

    
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

// 聊天模型
class Chat {
    public $id;
    public $type = 'private'; // private, group
    public $participants = [];
    public $name; // 群组名称
    public $created_at;
    
    const TYPES = [
        'private' => '私聊',
        'group' => '群聊'
    ];
}

// 消息模型
class Message {
    public $id;
    public $chat_id;
    public $sender_id;
    public $content;
    public $attachments = [];
    public $created_at;
    public $status = 'sent'; // sent, delivered, read
    
    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        
        if (empty($this->id)) {
            $this->id = 'msg_' . uniqid();
        }
        
        if (empty($this->created_at)) {
            $this->created_at = date('Y-m-d H:i:s');
        }
    }
    
    public function save() {
        $filename = CHATS_DIR . $this->chat_id . '_' . $this->id . '.json';
        $data = get_object_vars($this);
        return file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    public static function load($message_id, $chat_id) {
        $filename = CHATS_DIR . $chat_id . '_' . $message_id . '.json';
        if (!file_exists($filename)) return null;
        
        $data = json_decode(file_get_contents($filename), true);
        if ($data === null) return null;
        
        return new Message($data);
    }
    
    public static function getMessagesByChat($chat_id, $limit = 50, $offset = 0) {
        $messages = [];
        $files = glob(CHATS_DIR . $chat_id . '_*.json');
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $messages[] = new Message($data);
            }
        }
        
        // 按时间排序
        usort($messages, function($a, $b) {
            return strtotime($a->created_at) - strtotime($b->created_at);
        });
        
        // 分页
        return array_slice($messages, $offset, $limit);
    }
    
    public function delete() {
        $filename = CHATS_DIR . $this->chat_id . '_' . $this->id . '.json';
        if (file_exists($filename)) {
            return unlink($filename);
        }
        return false;
    }
    
    public function getSender() {
        return User::load($this->sender_id);
    }
    
    public static function createMessage($data) {
        $message = new Message($data);
        return $message->save() ? $message : false;
    }
}

// 聊天管理器
class ChatManager {
    public static function createChat($type, $participants, $name = null) {
        $chat = new Chat();
        $chat->id = 'chat_' . uniqid();
        $chat->type = $type;
        $chat->participants = $participants;
        $chat->name = $name;
        $chat->created_at = date('Y-m-d H:i:s');
        
        $filename = CHATS_DIR . $chat->id . '_info.json';
        $data = get_object_vars($chat);
        return file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT)) ? $chat : false;
    }
    
    public static function loadChat($chat_id) {
        $filename = CHATS_DIR . $chat_id . '_info.json';
        if (!file_exists($filename)) return null;
        
        $data = json_decode(file_get_contents($filename), true);
        if ($data === null) return null;
        
        $chat = new Chat();
        foreach ($data as $key => $value) {
            if (property_exists($chat, $key)) {
                $chat->$key = $value;
            }
        }
        
        return $chat;
    }
    
    public static function getChatsByUser($user_id) {
        $chats = [];
        $files = glob(CHATS_DIR . '*_info.json');
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && in_array($user_id, $data['participants'])) {
                $chat = new Chat();
                foreach ($data as $key => $value) {
                    if (property_exists($chat, $key)) {
                        $chat->$key = $value;
                    }
                }
                $chats[] = $chat;
            }
        }
        
        return $chats;
    }
    
    public static function getOrCreatePrivateChat($user1_id, $user2_id) {
        // 确保用户ID有序，避免重复创建聊天
        $sorted_ids = [$user1_id, $user2_id];
        sort($sorted_ids);
        $chat_key = 'private_' . $sorted_ids[0] . '_' . $sorted_ids[1];
        
        // 查找现有的私聊
        $files = glob(CHATS_DIR . '*_info.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && $data['type'] === 'private') {
                $participants = $data['participants'];
                sort($participants);
                if ($participants === $sorted_ids) {
                    return self::loadChat($data['id']);
                }
            }
        }
        
        // 创建新的私聊
        return self::createChat('private', [$user1_id, $user2_id]);
    }
    
    public static function createGroupChat($name, $participants) {
        return self::createChat('group', $participants, $name);
    }
    
    public static function addParticipant($chat_id, $user_id) {
        $chat = self::loadChat($chat_id);
        if (!$chat || $chat->type !== 'group') return false;
        if (in_array($user_id, $chat->participants)) return true;
        
        $chat->participants[] = $user_id;
        $filename = CHATS_DIR . $chat->id . '_info.json';
        $data = get_object_vars($chat);
        return file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    public static function removeParticipant($chat_id, $user_id) {
        $chat = self::loadChat($chat_id);
        if (!$chat || $chat->type !== 'group') return false;
        
        $key = array_search($user_id, $chat->participants);
        if ($key === false) return true;
        
        unset($chat->participants[$key]);
        $chat->participants = array_values($chat->participants);
        
        $filename = CHATS_DIR . $chat->id . '_info.json';
        $data = get_object_vars($chat);
        return file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    public static function updateGroupName($chat_id, $name) {
        $chat = self::loadChat($chat_id);
        if (!$chat || $chat->type !== 'group') return false;
        
        $chat->name = $name;
        $filename = CHATS_DIR . $chat->id . '_info.json';
        $data = get_object_vars($chat);
        return file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }
}

// 待办事项模型
class Todo {
    public $id;
    public $title;
    public $description;
    public $status = 'pending';
    public $priority = 'medium';
    public $assignee_id;
    public $creator_id;
    public $due_date;
    public $created_at;
    public $updated_at;
    
    const STATUSES = [
        'pending' => '待处理',
        'in_progress' => '进行中',
        'completed' => '已完成'
    ];
    
    const PRIORITIES = [
        'low' => '低',
        'medium' => '中',
        'high' => '高'
    ];
    
    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        
        if (empty($this->id)) {
            $this->id = 'todo_' . uniqid();
        }
        
        if (empty($this->created_at)) {
            $this->created_at = date('Y-m-d H:i:s');
        }
        
        $this->updated_at = date('Y-m-d H:i:s');
    }
    
    public function save() {
        $filename = TODOS_DIR . $this->id . '.json';
        $data = get_object_vars($this);
        return file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    public static function load($todo_id) {
        $filename = TODOS_DIR . $todo_id . '.json';
        if (!file_exists($filename)) return null;
        
        $data = json_decode(file_get_contents($filename), true);
        if ($data === null) return null;
        
        return new Todo($data);
    }
    
    public static function getAllTodos() {
        $todos = [];
        $files = glob(TODOS_DIR . '*.json');
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $todos[] = new Todo($data);
            }
        }
        
        return $todos;
    }
    
    public static function getTodosByUser($user_id, $include_subordinates = false) {
        $todos = [];
        $all_todos = self::getAllTodos();
        
        foreach ($all_todos as $todo) {
            if ($todo->assignee_id === $user_id) {
                $todos[] = $todo;
            } elseif ($include_subordinates) {
                $user = User::load($user_id);
                if ($user) {
                    $subordinates = $user->getSubordinateIds(true);
                    if (in_array($todo->assignee_id, $subordinates)) {
                        $todos[] = $todo;
                    }
                }
            }
        }
        
        return $todos;
    }
    
    public static function getPendingTodosByUser($user_id) {
        $todos = self::getTodosByUser($user_id);
        return array_filter($todos, function($todo) {
            return $todo->status !== 'completed';
        });
    }
    
    public function delete() {
        $filename = TODOS_DIR . $this->id . '.json';
        if (file_exists($filename)) {
            return unlink($filename);
        }
        return false;
    }
    
    public static function createTodo($data) {
        $todo = new Todo($data);
        return $todo->save() ? $todo : false;
    }
}

// 通知模型
class Notification {
    public $id;
    public $title;
    public $content;
    public $type = 'info';
    public $user_id;
    public $sender_id;
    public $is_read = false;
    public $created_at;
    
    const TYPES = [
        'info' => '信息',
        'warning' => '警告',
        'error' => '错误',
        'success' => '成功'
    ];
    
    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        
        if (empty($this->id)) {
            $this->id = 'notification_' . uniqid();
        }
        
        if (empty($this->created_at)) {
            $this->created_at = date('Y-m-d H:i:s');
        }
    }
    
    public function save() {
        $filename = NOTIFICATIONS_DIR . $this->id . '.json';
        $data = get_object_vars($this);
        return file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    public static function load($notification_id) {
        $filename = NOTIFICATIONS_DIR . $notification_id . '.json';
        if (!file_exists($filename)) return null;
        
        $data = json_decode(file_get_contents($filename), true);
        if ($data === null) return null;
        
        return new Notification($data);
    }
    
    public static function getNotificationsByUser($user_id, $only_unread = false) {
        $notifications = [];
        $files = glob(NOTIFICATIONS_DIR . '*.json');
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && $data['user_id'] === $user_id) {
                if (!$only_unread || !$data['is_read']) {
                    $notifications[] = new Notification($data);
                }
            }
        }
        
        // 按时间倒序排序
        usort($notifications, function($a, $b) {
            return strtotime($b->created_at) - strtotime($a->created_at);
        });
        
        return $notifications;
    }
    
    public function markAsRead() {
        $this->is_read = true;
        return $this->save();
    }
    
    public function delete() {
        $filename = NOTIFICATIONS_DIR . $this->id . '.json';
        if (file_exists($filename)) {
            return unlink($filename);
        }
        return false;
    }
    
    public static function createNotification($data) {
        $notification = new Notification($data);
        return $notification->save() ? $notification : false;
    }
}

// 申请模型
class Application {
    public $id;
    public $title;
    public $content;
    public $status = 'pending';
    public $applicant_id;
    public $approver_id;
    public $created_at;
    public $updated_at;
    
    const STATUSES = [
        'pending' => '待审批',
        'approved' => '已批准',
        'rejected' => '已拒绝'
    ];
    
    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        
        if (empty($this->id)) {
            $this->id = 'application_' . uniqid();
        }
        
        if (empty($this->created_at)) {
            $this->created_at = date('Y-m-d H:i:s');
        }
        
        $this->updated_at = date('Y-m-d H:i:s');
    }
    
    public function save() {
        $filename = APPLICATIONS_DIR . $this->id . '.json';
        $data = get_object_vars($this);
        return file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    public static function load($application_id) {
        $filename = APPLICATIONS_DIR . $application_id . '.json';
        if (!file_exists($filename)) return null;
        
        $data = json_decode(file_get_contents($filename), true);
        if ($data === null) return null;
        
        return new Application($data);
    }
    
    public static function getAllApplications() {
        $applications = [];
        $files = glob(APPLICATIONS_DIR . '*.json');
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $applications[] = new Application($data);
            }
        }
        
        return $applications;
    }
    
    public static function getPendingApplications() {
        $applications = self::getAllApplications();
        return array_filter($applications, function($app) {
            return $app->status === 'pending';
        });
    }
    
    public static function getApplicationsByUser($user_id) {
        $applications = [];
        $all_applications = self::getAllApplications();
        
        foreach ($all_applications as $app) {
            if ($app->applicant_id === $user_id || $app->approver_id === $user_id) {
                $applications[] = $app;
            }
        }
        
        return $applications;
    }
    
    public function approve($approver_id) {
        $this->status = 'approved';
        $this->approver_id = $approver_id;
        $this->updated_at = date('Y-m-d H:i:s');
        return $this->save();
    }
    
    public function reject($approver_id) {
        $this->status = 'rejected';
        $this->approver_id = $approver_id;
        $this->updated_at = date('Y-m-d H:i:s');
        return $this->save();
    }
    
    public function delete() {
        $filename = APPLICATIONS_DIR . $this->id . '.json';
        if (file_exists($filename)) {
            return unlink($filename);
        }
        return false;
    }
    
    public static function createApplication($data) {
        $application = new Application($data);
        return $application->save() ? $application : false;
    }
}

// 通知管理器
class NotificationManager {
    public static function sendToMultipleUsers($user_ids, $type, $title, $content, $related_id = null) {
        $success_count = 0;
        foreach ($user_ids as $user_id) {
            $notification = Notification::createNotification([
                'user_id' => $user_id,
                'type' => $type,
                'title' => $title,
                'content' => $content,
                'related_id' => $related_id
            ]);
            if ($notification) $success_count++;
        }
        return $success_count;
    }
    
    public static function getUnreadCount($user_id) {
        $notifications = Notification::getNotificationsByUser($user_id, true);
        return count($notifications);
    }
}