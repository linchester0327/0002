<?php
class Todo {
    public $id;
    public $title;
    public $description;
    public $creator_id;
    public $assignee_id;
    public $status = 'pending'; // pending, in_progress, completed
    public $priority = 'medium'; // low, medium, high
    public $due_date;
    public $created_at;
    public $updated_at;
    public $completed_at;
    
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
        $all = self::getAllTodos();
        
        foreach ($all as $todo) {
            if ($todo->assignee_id === $user_id || $todo->creator_id === $user_id) {
                $todos[] = $todo;
            } elseif ($include_subordinates) {
                // 检查是否是下属的TODO
                $creator = User::load($todo->creator_id);
                if ($creator && $creator->parent_id === $user_id) {
                    $todos[] = $todo;
                }
            }
        }
        
        return $todos;
    }
    
    public static function getPendingTodosByUser($user_id) {
        $todos = [];
        $all = self::getTodosByUser($user_id, true);
        
        foreach ($all as $todo) {
            if ($todo->status === 'pending') {
                $todos[] = $todo;
            }
        }
        
        return $todos;
    }
    
    public function delete() {
        $filename = TODOS_DIR . $this->id . '.json';
        if (file_exists($filename)) {
            return unlink($filename);
        }
        return false;
    }
    
    public function updateStatus($status) {
        if (array_key_exists($status, self::STATUSES)) {
            $this->status = $status;
            $this->updated_at = date('Y-m-d H:i:s');
            if ($status === 'completed') {
                $this->completed_at = date('Y-m-d H:i:s');
            }
            return $this->save();
        }
        return false;
    }
    
    public function updateAssignee($assignee_id) {
        $this->assignee_id = $assignee_id;
        $this->updated_at = date('Y-m-d H:i:s');
        return $this->save();
    }
    
    public function getCreator() {
        return User::load($this->creator_id);
    }
    
    public function getAssignee() {
        return User::load($this->assignee_id);
    }
    
    public static function createTodo($data, $creator_id) {
        $todo = new Todo($data);
        $todo->creator_id = $creator_id;
        
        if (empty($todo->assignee_id)) {
            $todo->assignee_id = $creator_id;
        }
        
        return $todo->save() ? $todo : false;
    }
}