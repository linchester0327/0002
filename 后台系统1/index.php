<?php
// index.php - 完整正确的版本
// 会话安全配置
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
ini_set('session.gc_maxlifetime', 3600); // 1小时过期
ini_set('session.cookie_lifetime', 3600);
session_start();

// 包含配置文件
include_once __DIR__ . '/includes/config.php';
include_once __DIR__ . '/includes/models.php';

// 定义基础路径
$current_dir = __DIR__;
$base_dir = '/';
define('BASE_DIR', $base_dir);

// 读取系统状态
$system_data = json_decode(file_get_contents(SYSTEM_FILE), true);
$is_initialized = $system_data['initialized'] ?? false;

// 处理错误和消息
$error = '';
$success = '';

// 处理系统初始化
if (!$is_initialized && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['init_system'])) {
    // 验证CSRF令牌
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error = '无效的请求';
        log_security('CSRF验证失败', ['action' => 'init_system']);
    } else {
        $admin_name = $_POST['admin_name'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($admin_name) || empty($password) || empty($confirm_password)) {
            $error = '请填写所有必填字段';
            log_warning('系统初始化表单验证失败', ['missing_fields' => true]);
        } elseif ($password !== $confirm_password) {
            $error = '两次输入的密码不一致';
            log_warning('系统初始化密码确认失败');
        } else {
            // 验证密码复杂度
            $password_validation = validate_password_strength($password);
            if (!$password_validation['valid']) {
                $error = implode('<br>', $password_validation['errors']);
                log_warning('系统初始化密码复杂度不足', ['errors' => $password_validation['errors']]);
            } else {
                // 创建管理员用户
                $admin_data = [
                    'id' => 'admin_' . time(),
                    'name' => $admin_name,
                    'code' => 'ADMIN001',
                    'username' => 'admin',
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'position' => '系统管理员',
                    'permissions' => array_keys(User::PERMISSIONS),
                    'parent_id' => null,
                    'subordinates' => [],
                    'created_at' => date('Y-m-d H:i:s'),
                    'is_active' => true
                ];
                
                if (file_put_contents(USERS_DIR . 'admin.json', json_encode($admin_data, JSON_PRETTY_PRINT))) {
                    $system_data['initialized'] = true;
                    $system_data['admin_created'] = true;
                    $system_data['init_time'] = date('Y-m-d H:i:s');
                    file_put_contents(SYSTEM_FILE, json_encode($system_data, JSON_PRETTY_PRINT));
                    $success = '系统初始化成功！请使用管理员账户登录';
                    $is_initialized = true;
                    log_info('系统初始化成功', ['admin_name' => $admin_name]);
                } else {
                    $error = '初始化失败，请检查目录权限';
                    log_error('系统初始化失败', ['error' => '文件写入失败']);
                }
            }
        }
    }
}

// 处理用户登录
if ($is_initialized && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // 验证CSRF令牌
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error = '无效的请求';
        log_security('CSRF验证失败', ['action' => 'login']);
    } else {
        // 检查登录尝试限制
        $login_limit_error = check_login_limit();
        if ($login_limit_error) {
            $error = $login_limit_error;
            log_security('登录尝试次数过多', ['action' => 'login']);
        } else {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $error = '请填写用户名和密码';
                record_login_attempt($username);
                log_warning('登录表单验证失败', ['missing_fields' => true]);
            } else {
                // 查找用户
                $user_files = glob(USERS_DIR . '*.json');
                $user_found = false;
                
                foreach ($user_files as $user_file) {
                    $user_data = json_decode(file_get_contents($user_file), true);
                    if ($user_data && $user_data['username'] === $username) {
                        $user_found = true;
                        if (password_verify($password, $user_data['password_hash'])) {
                            // 登录成功，重置尝试次数
                            reset_login_attempts();
                            
                            // 登录成功
                            $_SESSION['user_id'] = $user_data['id'];
                            $_SESSION['username'] = $user_data['username'];
                            $_SESSION['user_name'] = $user_data['name'];
                            $_SESSION['is_admin'] = ($username === 'admin');
                            
                            // 重新生成CSRF令牌
                            unset($_SESSION['csrf_token']);
                            
                            // 记录登录成功
                            log_info('用户登录成功', ['username' => $username, 'user_id' => $user_data['id']]);
                            
                            // 跳转到仪表板
                            header('Location: dashboard.php');
                            exit();
                        } else {
                            $error = '密码错误';
                            record_login_attempt($username);
                            log_warning('登录密码错误', ['username' => $username]);
                        }
                        break;
                    }
                }
                
                if (!$user_found) {
                    $error = '用户不存在';
                    record_login_attempt($username);
                    log_warning('登录用户不存在', ['username' => $username]);
                }
            }
        }
    }
}

// 如果用户已登录，跳转到仪表板
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 登录</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <h1><?php echo SITE_NAME; ?></h1>
            <p>后台权限管理系统</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (!$is_initialized): ?>
                <div class="init-notice">
                    <h3>💡 系统初始化</h3>
                    <p>首次使用，请创建管理员账户。管理员拥有系统所有权限。</p>
                </div>
                
                <form method="POST" id="initForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="form-group">
                        <label class="form-label">管理员姓名</label>
                        <input type="text" name="admin_name" class="form-control" 
                               placeholder="请输入管理员真实姓名" required
                               value="<?php echo isset($_POST['admin_name']) ? htmlspecialchars($_POST['admin_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">管理员密码</label>
                        <input type="password" name="password" class="form-control" 
                               placeholder="请输入密码（至少6位）" minlength="6" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">确认密码</label>
                        <input type="password" name="confirm_password" class="form-control" 
                               placeholder="请再次输入密码" minlength="6" required>
                    </div>
                    
                    <button type="submit" name="init_system" class="btn">初始化系统</button>
                </form>
                
            <?php else: ?>
                <form method="POST" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="form-group">
                        <label class="form-label">用户名</label>
                        <input type="text" name="username" class="form-control" 
                               placeholder="请输入用户名" required
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : 'admin'; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">密码</label>
                        <input type="password" name="password" class="form-control" 
                               placeholder="请输入密码" required>
                    </div>
                    
                    <button type="submit" name="login" class="btn">登录系统</button>
                </form>
                
                <div class="login-info">
                    <p>默认管理员账户：admin</p>
                    <p>初始化时设置的密码</p>
                </div>
            <?php endif; ?>
            
            <!-- 调试信息 -->
            <div class="debug-info">
                <p><strong>调试信息：</strong></p>
                <p>当前路径: <?php echo __DIR__; ?></p>
                <p>系统初始化状态: <?php echo $is_initialized ? '已初始化' : '未初始化'; ?></p>
                <p>数据目录: <?php echo DATA_DIR; ?></p>
            </div>
        </div>
    </div>

    <script>
        // 密码确认验证
        document.getElementById('initForm')?.addEventListener('submit', function(e) {
            const password = document.querySelector('input[name="password"]');
            const confirm = document.querySelector('input[name="confirm_password"]');
            
            if (password && confirm && password.value !== confirm.value) {
                e.preventDefault();
                alert('两次输入的密码不一致，请重新输入');
                confirm.focus();
            }
        });
    </script>
</body>
</html>
