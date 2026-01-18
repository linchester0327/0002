<?php
class Application {
    public $id;
    public $user_id;
    public $type; // account, permission, resource, other
    public $title;
    public $content;
    public $status; // pending, approved, rejected
    public $approver_id;
    public $approved_at;
    public $rejection_reason;
    public $created_at;
    
    const TYPES = [
        'account' => '账号相关',
        'permission' => '权限申请',
        'resource' => '资源申请',
        'other' => '其他申请'
    ];
    
    const STATUS = [
        'pending' => '待处理',
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
            $this->id = 'app_' . uniqid();
        }
        
        if (empty($this->created_at)) {
            $this->created_at = date('Y-m-d H:i:s');
        }
        
        if (empty($this->status)) {
            $this->status = 'pending';
        }
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
        
        // 按时间倒序排序
        usort($applications, function($a, $b) {
            return strtotime($b->created_at) - strtotime($a->created_at);
        });
        
        return $applications;
    }
    
    public static function getApplicationsByUser($user_id) {
        $applications = [];
        $files = glob(APPLICATIONS_DIR . '*.json');
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && $data['user_id'] === $user_id) {
                $applications[] = new Application($data);
            }
        }
        
        // 按时间倒序排序
        usort($applications, function($a, $b) {
            return strtotime($b->created_at) - strtotime($a->created_at);
        });
        
        return $applications;
    }
    
    public static function getPendingApplications() {
        $applications = [];
        $files = glob(APPLICATIONS_DIR . '*.json');
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && $data['status'] === 'pending') {
                $applications[] = new Application($data);
            }
        }
        
        // 按时间倒序排序
        usort($applications, function($a, $b) {
            return strtotime($b->created_at) - strtotime($a->created_at);
        });
        
        return $applications;
    }
    
    public function approve($approver_id) {
        $this->status = 'approved';
        $this->approver_id = $approver_id;
        $this->approved_at = date('Y-m-d H:i:s');
        return $this->save();
    }
    
    public function reject($approver_id, $reason) {
        $this->status = 'rejected';
        $this->approver_id = $approver_id;
        $this->approved_at = date('Y-m-d H:i:s');
        $this->rejection_reason = $reason;
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