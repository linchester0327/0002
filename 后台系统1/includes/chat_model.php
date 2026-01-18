<?php
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

class Message {
    public $id;
    public $chat_id;
    public $sender_id;
    public $content;
    public $attachments = []; // 附件数组
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