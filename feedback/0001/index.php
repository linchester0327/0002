<?php
// 安全配置
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
ini_set('error_log', './error.log');

// 定义常量
define('JSON_FILE', './feedback.json');

// 安全函数
function sanitize_input($input) {
    // 去除首尾空白
    $input = trim($input);
    // 转义HTML特殊字符
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    // 过滤危险字符
    $input = preg_replace('/[<>"\'&]/', '', $input);
    return $input;
}

function validate_email($email) {
    if (empty($email)) return true;
    // 严格的邮箱验证
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function generate_id() {
    // 生成唯一ID，使用时间戳和随机数
    return 'feedback-' . date('Y-m-d\TH:i:sP') . '-' . bin2hex(random_bytes(4));
}

function read_json() {
    // 安全读取JSON文件
    if (!file_exists(JSON_FILE)) {
        return array();
    }
    
    // 检查文件权限
    if (!is_readable(JSON_FILE)) {
        error_log('JSON file is not readable');
        return array();
    }
    
    $content = file_get_contents(JSON_FILE);
    if ($content === false) {
        error_log('Failed to read JSON file');
        return array();
    }
    
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Invalid JSON format: ' . json_last_error_msg());
        return array();
    }
    
    return $data;
}

function write_json($data) {
    // 安全写入JSON文件
    if (!is_writable(dirname(JSON_FILE))) {
        error_log('Directory is not writable');
        return false;
    }
    
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log('Failed to encode JSON: ' . json_last_error_msg());
        return false;
    }
    
    // 写入临时文件，然后原子替换
    $temp_file = JSON_FILE . '.tmp';
    if (file_put_contents($temp_file, $json) === false) {
        error_log('Failed to write to temp file');
        return false;
    }
    
    if (!rename($temp_file, JSON_FILE)) {
        error_log('Failed to rename temp file');
        unlink($temp_file);
        return false;
    }
    
    return true;
}

function find_message_by_id($data, $id, &$parent = null) {
    // 递归查找消息
    foreach ($data as $message_id => $message) {
        if ($message_id === $id) {
            return $message;
        }
        if (isset($message['re']) && !empty($message['re'])) {
            if ($found = find_message_by_id($message['re'], $id, $message)) {
                return $found;
            }
        }
    }
    return null;
}

// 处理反馈提交
$errors = array();
$success = '';
if (isset($_POST['action']) && $_POST['action'] == 'submit_feedback') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $content = sanitize_input($_POST['content']);
    
    // 验证输入
    if (empty($content)) {
        $errors[] = '反馈内容不能为空';
    }
    if (strlen($content) > 1000) {
        $errors[] = '反馈内容不能超过1000个字符';
    }
    if (!validate_email($email)) {
        $errors[] = '邮箱格式不正确';
    }
    if (strlen($name) > 50) {
        $errors[] = '姓名不能超过50个字符';
    }
    if (strlen($email) > 100) {
        $errors[] = '邮箱不能超过100个字符';
    }
    
    if (empty($errors)) {
        // 读取现有数据
        $data = read_json();
        
        // 创建新反馈
        $new_feedback = array(
            'time' => date('Y-m-d\TH:i:sP'),
            'name' => $name,
            'email' => $email,
            'content' => $content,
            're' => array()
        );
        
        // 生成唯一ID
        $feedback_id = generate_id();
        
        // 添加到数据
        $data[$feedback_id] = $new_feedback;
        
        // 写入文件
        if (write_json($data)) {
            $success = '反馈提交成功！';
        } else {
            $errors[] = '反馈提交失败，请稍后重试';
        }
    }
}

// 处理回复提交
if (isset($_POST['action']) && $_POST['action'] == 'submit_reply') {
    $parent_id = sanitize_input($_POST['parent_id']);
    $reply_content = sanitize_input($_POST['reply_content']);
    
    // 验证输入
    if (empty($parent_id) || empty($reply_content)) {
        $errors[] = '回复内容不能为空';
    }
    if (strlen($reply_content) > 1000) {
        $errors[] = '回复内容不能超过1000个字符';
    }
    
    if (empty($errors)) {
        // 读取现有数据
        $data = read_json();
        
        // 查找父消息
        $parent_message = find_message_by_id($data, $parent_id, $parent_ref);
        
        if ($parent_message) {
            // 创建新回复
            $new_reply = array(
                'time' => date('Y-m-d\TH:i:sP'),
                'content' => $reply_content,
                're' => array()
            );
            
            // 生成唯一ID
            $reply_id = generate_id();
            
            // 添加回复
            if (isset($parent_ref)) {
                // 回复的是回复
                if (isset($parent_ref['re'])) {
                    $parent_ref['re'][$reply_id] = $new_reply;
                }
            } else {
                // 回复的是主反馈
                if (isset($data[$parent_id]['re'])) {
                    $data[$parent_id]['re'][$reply_id] = $new_reply;
                }
            }
            
            // 写入文件
            if (write_json($data)) {
                $success = '回复提交成功！';
            } else {
                $errors[] = '回复提交失败，请稍后重试';
            }
        } else {
            $errors[] = '找不到要回复的消息';
        }
    }
}

// 显示所有反馈和回复
function display_messages($messages, $level = 0) {
    foreach ($messages as $id => $message) {
        $indent = str_repeat('&nbsp;', $level * 20);
        echo '<div class="message">';
        echo '<div class="message-header">';
        echo '<span class="message-id">' . substr($id, 0, 30) . '...</span>';
        echo '<span class="message-time">' . $message['time'] . '</span>';
        echo '</div>';
        
        if (!empty($message['name'])) {
            echo '<div class="message-name">' . $message['name'] . '</div>';
        }
        if (!empty($message['email'])) {
            echo '<div class="message-email">' . $message['email'] . '</div>';
        }
        
        echo '<div class="message-content">' . $message['content'] . '</div>';
        
        // 回复表单
        echo '<div class="reply-form">';
        echo '<form action="index.php" method="POST">';
        echo '<input type="hidden" name="action" value="submit_reply">';
        echo '<input type="hidden" name="parent_id" value="' . $id . '">';
        echo '<div class="form-group">';
        echo '<label for="reply_content_' . $id . '">回复：</label>';
        echo '<textarea id="reply_content_' . $id . '" name="reply_content" required maxlength="1000"></textarea>';
        echo '</div>';
        echo '<button type="submit">提交回复</button>';
        echo '</form>';
        echo '</div>';
        
        // 显示回复
        if (isset($message['re']) && !empty($message['re'])) {
            echo '<div class="replies">';
            display_messages($message['re'], $level + 1);
            echo '</div>';
        }
        
        echo '</div>';
    }
}

// 读取数据
$data = read_json();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>反馈系统</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .feedback-form {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            height: 100px;
            resize: vertical;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #45a049;
        }
        .messages {
            margin-top: 30px;
        }
        .message {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        .message-id {
            font-weight: bold;
            color: #333;
        }
        .message-time {
            font-size: 12px;
            color: #666;
        }
        .message-content {
            margin-bottom: 10px;
            line-height: 1.5;
        }
        .reply-form {
            margin-top: 15px;
            padding: 15px;
            background-color: #f0f0f0;
            border-radius: 5px;
        }
        .replies {
            margin-left: 20px;
            margin-top: 15px;
        }
        .reply {
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 5px;
            background-color: white;
        }
        .error {
            color: red;
            margin-bottom: 10px;
        }
        .success {
            color: green;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>反馈系统</h1>
        
        <!-- 反馈表单 -->
        <div class="feedback-form">
            <h2>提交反馈</h2>
            
            <!-- 错误提示 -->
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- 成功提示 -->
            <?php if (!empty($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form action="index.php" method="POST">
                <input type="hidden" name="action" value="submit_feedback">
                <div class="form-group">
                    <label for="name">姓名（可选）：</label>
                    <input type="text" id="name" name="name" maxlength="50">
                </div>
                <div class="form-group">
                    <label for="email">邮箱（可选）：</label>
                    <input type="text" id="email" name="email" maxlength="100">
                </div>
                <div class="form-group">
                    <label for="content">反馈内容：</label>
                    <textarea id="content" name="content" required maxlength="1000"></textarea>
                </div>
                <button type="submit">提交反馈</button>
            </form>
        </div>
        
        <!-- 反馈列表 -->
        <div class="messages">
            <h2>反馈列表</h2>
            <?php if (empty($data)): ?>
                <p>暂无反馈</p>
            <?php else: ?>
                <?php 
                    // 按时间倒序显示
                    krsort($data);
                    display_messages($data);
                ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>