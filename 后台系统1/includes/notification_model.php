<?php
class Notification {
    public $id;
    public $user_id;
    public $type; // system, todo, chat, application
    public $title;
    public $content;
    public $related_id; // 关联的ID（如todo_id, chat_id等）
    public $is_read = false;
    public $created_at;
    
    const TYPES = [
        'system' => '系统通知',
        'todo' => '待办通知',
        'chat' => '聊天通知',
        'application' => '申请通知'
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
        $filename = DATA_DIR . 'notifications' . DIRECTORY_SEPARATOR . $this->id . '.json';
        $data = get_object_vars($this);
        return file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    public static function load($notification_id) {
        $filename = DATA_DIR . 'notifications' . DIRECTORY_SEPARATOR . $notification_id . '.json';
        if (!file_exists($filename)) return null;
        
        $data = json_decode(file_get_contents($filename), true);
        if ($data === null) return null;
        
        return new Notification($data);
    }
    
    public static function getNotificationsByUser($user_id, $only_unread = false) {
        $notifications = [];
        $files = glob(DATA_DIR . 'notifications' . DIRECTORY_SEPARATOR . '*.json');
        
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
        $filename = DATA_DIR . 'notifications' . DIRECTORY_SEPARATOR . $this->id . '.json';
        if (file_exists($filename)) {
            return unlink($filename);
        }
        return false;
    }
    
    public static function createNotification($data) {
        $notification = new Notification($data);
        return $notification->save() ? $notification : false;
    }
    
    public static function createSystemNotification($user_id, $title, $content, $related_id = null) {
        return self::createNotification([
            'user_id' => $user_id,
            'type' => 'system',
            'title' => $title,
            'content' => $content,
            'related_id' => $related_id
        ]);
    }
    
    public static function createTodoNotification($user_id, $todo_id, $title, $content) {
        return self::createNotification([
            'user_id' => $user_id,
            'type' => 'todo',
            'title' => $title,
            'content' => $content,
            'related_id' => $todo_id
        ]);
    }
    
    public static function createChatNotification($user_id, $chat_id, $title, $content) {
        return self::createNotification([
            'user_id' => $user_id,
            'type' => 'chat',
            'title' => $title,
            'content' => $content,
            'related_id' => $chat_id
        ]);
    }
    
    public static function createApplicationNotification($user_id, $application_id, $title, $content) {
        return self::createNotification([
            'user_id' => $user_id,
            'type' => 'application',
            'title' => $title,
            'content' => $content,
            'related_id' => $application_id
        ]);
    }
}

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