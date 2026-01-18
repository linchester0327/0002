<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permission_check.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();

// 检查是否是管理员（只有管理员可以修改密码）
if (!$user->isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// 处理密码修改
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        $error = '无效的请求令牌';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = '请填写所有必填字段';
        } elseif ($new_password !== $confirm_password) {
            $error = '两次输入的新密码不一致';
        } else {
            // 验证当前密码
            if (!password_verify($current_password, $user->password_hash)) {
                $error = '当前密码错误';
            } else {
                // 验证新密码强度
                $password_validation = validate_password_strength($new_password);
                if (!$password_validation['valid']) {
                    $error = implode('<br>', $password_validation['errors']);
                } else {
                    // 更新密码
                    $user->password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $user->updated_at = date('Y-m-d H:i:s');
                    
                    if ($user->save()) {
                        $success = '密码修改成功';
                        log_info('管理员密码修改成功', ['user_id' => $user->id]);
                    } else {
                        $error = '密码修改失败';
                        log_error('管理员密码修改失败', ['user_id' => $user->id]);
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 修改密码</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="header">
        <div class="logo"><?php echo SITE_NAME; ?></div>
        <div class="user-info">
            <div>
                <strong><?php echo htmlspecialchars($user->name); ?></strong>
                <div class="user-position"><?php echo htmlspecialchars($user->position); ?></div>
            </div>
            <div class="user-avatar"><?php echo substr($user->name, 0, 1); ?></div>
            <a href="logout.php" class="logout-btn">退出</a>
        </div>
    </div>
    
    <div class="nav-sidebar">
        <ul class="nav-menu">
            <li><a href="dashboard.php" class="nav-link">📊 仪表板</a></li>
            <li><a href="users.php" class="nav-link">👥 用户管理</a></li>
            <li><a href="todo.php" class="nav-link">✅ 待办事项</a></li>
            <li><a href="chat.php" class="nav-link">💬 聊天</a></li>
            <li><a href="notifications.php" class="nav-link">🔔 通知</a></li>
            <li><a href="applications.php" class="nav-link">📝 申请管理</a></li>
            <?php if ($user->hasPermission('permission_assign')): ?>
            <li><a href="permissions.php" class="nav-link">🔐 权限管理</a></li>
            <?php endif; ?>
            <?php if ($user->isAdmin()): ?>
            <li><a href="password.php" class="nav-link active">🔑 修改密码</a></li>
            <?php endif; ?>
            <?php if ($user->hasPermission('system_config')): ?>
            <li><a href="data_management.php" class="nav-link">💾 数据管理</a></li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="main-content">
        <h1 class="page-title">修改密码</h1>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="form-group">
                    <label class="form-label">当前密码</label>
                    <input type="password" name="current_password" class="form-control" placeholder="输入当前密码" required>
                </div>
                <div class="form-group">
                    <label class="form-label">新密码</label>
                    <input type="password" name="new_password" class="form-control" placeholder="输入新密码" minlength="8" required>
                </div>
                <div class="form-group">
                    <label class="form-label">确认新密码</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="再次输入新密码" minlength="8" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">修改密码</button>
                    <a href="dashboard.php" class="btn btn-secondary">取消</a>
                </div>
            </form>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2 class="card-title">密码强度要求</h2>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>至少8位字符</li>
                <li>包含至少一个大写字母</li>
                <li>包含至少一个小写字母</li>
                <li>包含至少一个数字</li>
                <li>包含至少一个特殊字符</li>
            </ul>
        </div>
    </div>
</body>
</html>