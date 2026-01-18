<?php
// includes/config.php

// 检查 session 是否已启动
if (session_status() === PHP_SESSION_NONE) {
    // 会话安全配置
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_only_cookies', 1);
    ini_set('session.gc_maxlifetime', 3600); // 1小时过期
    ini_set('session.cookie_lifetime', 3600);
    session_start();
}

// 系统基本配置
define('SITE_NAME', '后台权限管理系统');
define('DEFAULT_TIMEZONE', 'Asia/Shanghai');

// 安全配置
define('MAX_LOGIN_ATTEMPTS', 5); // 最大登录尝试次数
define('LOGIN_LOCKOUT_TIME', 300); // 锁定时间（秒）
define('PASSWORD_MIN_LENGTH', 8); // 密码最小长度
define('PASSWORD_REQUIRE_UPPERCASE', true); // 要求大写字母
define('PASSWORD_REQUIRE_LOWERCASE', true); // 要求小写字母
define('PASSWORD_REQUIRE_NUMBERS', true); // 要求数字
define('PASSWORD_REQUIRE_SPECIAL', true); // 要求特殊字符


// 动态获取当前URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$path_info = dirname(dirname($script_name)); // 向上一级目录
$site_url = $protocol . '://' . $host . $path_info . '/';
define('SITE_URL', $site_url);

// HTTPS强制（生产环境）
if (isset($_SERVER['HTTP_HOST']) && !isset($_SERVER['HTTPS']) && $_SERVER['HTTP_HOST'] !== 'localhost') {
    $secure_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?? '';
    header('Location: ' . $secure_url, true, 301);
    exit();
}

// 安全HTTP头
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\'; img-src \'self\' data:; font-src \'self\'; connect-src \'self\';');


// 文件路径配置
$current_dir = dirname(__DIR__);
define('BASE_PATH', $current_dir . DIRECTORY_SEPARATOR);
define('DATA_DIR', BASE_PATH . 'data' . DIRECTORY_SEPARATOR);
define('USERS_DIR', DATA_DIR . 'users' . DIRECTORY_SEPARATOR);
define('CHATS_DIR', DATA_DIR . 'chats' . DIRECTORY_SEPARATOR);
define('TODOS_DIR', DATA_DIR . 'todos' . DIRECTORY_SEPARATOR);
define('NOTIFICATIONS_DIR', DATA_DIR . 'notifications' . DIRECTORY_SEPARATOR);
define('APPLICATIONS_DIR', DATA_DIR . 'applications' . DIRECTORY_SEPARATOR);
define('LOGS_DIR', DATA_DIR . 'logs' . DIRECTORY_SEPARATOR);
define('SYSTEM_FILE', DATA_DIR . 'system.json');

// 确保目录存在
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!is_dir(USERS_DIR)) mkdir(USERS_DIR, 0755, true);
if (!is_dir(CHATS_DIR)) mkdir(CHATS_DIR, 0755, true);
if (!is_dir(TODOS_DIR)) mkdir(TODOS_DIR, 0755, true);
if (!is_dir(NOTIFICATIONS_DIR)) mkdir(NOTIFICATIONS_DIR, 0755, true);
if (!is_dir(APPLICATIONS_DIR)) mkdir(APPLICATIONS_DIR, 0755, true);
if (!is_dir(LOGS_DIR)) mkdir(LOGS_DIR, 0755, true);
if (!is_dir(DATA_DIR . 'backups')) mkdir(DATA_DIR . 'backups', 0755, true);

// 初始化系统文件
if (!file_exists(SYSTEM_FILE)) {
    file_put_contents(SYSTEM_FILE, json_encode([
        'initialized' => false,
        'admin_created' => false
    ], JSON_PRETTY_PRINT));
}

// 设置时区
date_default_timezone_set(DEFAULT_TIMEZONE);

// CSRF保护功能
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 登录尝试限制功能
function get_client_ip() {
    $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim($_SERVER[$key]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function record_login_attempt($username) {
    $ip = get_client_ip();
    $attempts_key = 'login_attempts_' . $ip;
    
    if (!isset($_SESSION[$attempts_key])) {
        $_SESSION[$attempts_key] = [
            'count' => 0,
            'last_attempt' => time(),
            'username' => $username
        ];
    }
    
    $_SESSION[$attempts_key]['count']++;
    $_SESSION[$attempts_key]['last_attempt'] = time();
    $_SESSION[$attempts_key]['username'] = $username;
}

function check_login_limit() {
    $ip = get_client_ip();
    $attempts_key = 'login_attempts_' . $ip;
    
    if (isset($_SESSION[$attempts_key])) {
        $attempts = $_SESSION[$attempts_key];
        $elapsed = time() - $attempts['last_attempt'];
        
        if ($attempts['count'] >= MAX_LOGIN_ATTEMPTS && $elapsed < LOGIN_LOCKOUT_TIME) {
            $remaining = LOGIN_LOCKOUT_TIME - $elapsed;
            return "登录尝试次数过多，请在 {$remaining} 秒后重试";
        } elseif ($elapsed >= LOGIN_LOCKOUT_TIME) {
            // 锁定时间已过，重置尝试次数
            unset($_SESSION[$attempts_key]);
        }
    }
    
    return false;
}

function reset_login_attempts() {
    $ip = get_client_ip();
    $attempts_key = 'login_attempts_' . $ip;
    if (isset($_SESSION[$attempts_key])) {
        unset($_SESSION[$attempts_key]);
    }
}

// 日志记录功能
function log_event($level, $message, $data = []) {
    $log_file = LOGS_DIR . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = get_client_ip();
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'anonymous';
    
    $log_entry = [
        'timestamp' => $timestamp,
        'level' => $level,
        'message' => $message,
        'ip' => $ip,
        'user_id' => $user_id,
        'data' => $data
    ];
    
    $log_line = json_encode($log_entry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
}

function log_error($message, $data = []) {
    log_event('ERROR', $message, $data);
}

function log_warning($message, $data = []) {
    log_event('WARNING', $message, $data);
}

function log_info($message, $data = []) {
    log_event('INFO', $message, $data);
}

function log_security($message, $data = []) {
    log_event('SECURITY', $message, $data);
}

// 密码复杂度验证
function validate_password_strength($password) {
    $errors = [];
    
    // 检查长度
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "密码长度至少为 " . PASSWORD_MIN_LENGTH . " 位";
    }
    
    // 检查大写字母
    if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
        $errors[] = "密码必须包含至少一个大写字母";
    }
    
    // 检查小写字母
    if (PASSWORD_REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
        $errors[] = "密码必须包含至少一个小写字母";
    }
    
    // 检查数字
    if (PASSWORD_REQUIRE_NUMBERS && !preg_match('/[0-9]/', $password)) {
        $errors[] = "密码必须包含至少一个数字";
    }
    
    // 检查特殊字符
    if (PASSWORD_REQUIRE_SPECIAL && !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "密码必须包含至少一个特殊字符";
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

// 路径安全处理
function sanitize_path($path) {
    // 移除路径中的../和./
    $path = preg_replace('#\.\./#', '', $path);
    $path = preg_replace('#\./#', '', $path);
    // 移除空字节
    $path = str_replace("\0", '', $path);
    // 规范化路径
    $path = realpath($path);
    return $path;
}

function validate_path($path, $base_dir) {
    // 确保路径在基础目录内
    $real_path = realpath($path);
    $real_base = realpath($base_dir);
    
    if (!$real_path || !$real_base) {
        return false;
    }
    
    // 检查路径是否在基础目录内
    return strpos($real_path, $real_base) === 0;
}

// 文件上传安全检查
function validate_file_upload($file, $allowed_extensions = [], $max_size = 5 * 1024 * 1024) {
    $errors = [];
    
    // 检查文件是否上传成功
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = '文件上传失败';
        return $errors;
    }
    
    // 检查文件大小
    if ($file['size'] > $max_size) {
        $errors[] = '文件大小超过限制';
    }
    
    // 检查文件扩展名
    if (!empty($allowed_extensions)) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed_extensions)) {
            $errors[] = '不允许的文件类型';
        }
    }
    
    // 检查文件类型（MIME类型）
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // 检查MIME类型
    $allowed_mimes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    
    if (!in_array($mime_type, $allowed_mimes)) {
        $errors[] = '不允许的文件类型';
    }
    
    // 检查文件是否为真实文件（防止恶意文件）
    if (!is_uploaded_file($file['tmp_name'])) {
        $errors[] = '无效的文件上传';
    }
    
    return $errors;
}

// 生成安全的文件名
function generate_safe_filename($original_name) {
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time();
    if (!empty($extension)) {
        $filename .= '.' . $extension;
    }
    return $filename;
}

// 开发环境显示错误
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
