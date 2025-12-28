<?php
// 设置响应头为JSON格式
header('Content-Type: application/json');

// 从password.ini读取密码
function getPasswordFromIni() {
    $ini_file = 'password.ini';
    $password = '';
    
    if (file_exists($ini_file)) {
        $lines = file($ini_file);
        foreach ($lines as $line) {
            $line = trim($line);
            // 跳过注释行和空行
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // 解析password=value格式
            if (strpos($line, 'password=') === 0) {
                $password = trim(substr($line, 9)); // 9是'password='的长度
                break;
            }
        }
    }
    
    return $password;
}

// 检查请求方法是否为POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 获取POST请求的原始数据
    $input = file_get_contents('php://input');
    
    // 检查是否有数据
    if (empty($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No data provided']);
        exit;
    }
    
    // 验证JSON格式
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON format']);
        exit;
    }
    
    // 验证数据结构（确保包含projects字段）
    if (!isset($data['projects'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data structure']);
        exit;
    }
    
    // 从password.ini获取密码
    $password = getPasswordFromIni();
    
    // 构建要保存的数据结构
    $save_data = [
        'password' => $password,
        'projects' => $data['projects']
    ];
    
    // 写入到main.json文件
    $file_path = 'main.json';
    $result = file_put_contents($file_path, json_encode($save_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    if ($result === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to write to file']);
        exit;
    }
    
    // 返回成功响应
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Data saved successfully']);
    exit;
} 
// 处理获取密码的请求
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'getpassword') {
    // 从password.ini获取密码
    $password = getPasswordFromIni();
    
    // 返回密码
    http_response_code(200);
    echo json_encode(['success' => true, 'password' => $password]);
    exit;
}

// 其他请求方法
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
exit;
?>