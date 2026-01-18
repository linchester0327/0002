<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permission_check.php';

Auth::requireLogin();
PermissionCheck::require('user_create');
$user = Auth::getCurrentUser();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        $error = '无效的请求令牌';
    } else {
        $required = ['name', 'username', 'password', 'code', 'position'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                $error = '请填写所有必填字段';
                break;
            }
        }
        
        if (!$error && $_POST['password'] !== $_POST['confirm_password']) {
            $error = '两次密码不一致';
        }
        
        if (!$error && User::loadByUsername($_POST['username'])) {
            $error = '用户名已存在';
        }
        
        if (!$error) {
            $permissions = $_POST['permissions'] ?? [];
            foreach ($permissions as $perm) {
                if (!$user->hasPermission($perm)) {
                    $error = '您没有分配此权限的权限';
                    break;
                }
            }
        }
        
        if (!$error) {
            $new_user = User::createUser([
                'name' => $_POST['name'],
                'username' => $_POST['username'],
                'password_hash' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                'code' => $_POST['code'],
                'position' => $_POST['position'],
                'permissions' => $permissions
            ], $user);
            
            if ($new_user) {
                $success = '用户创建成功';
            } else {
                $error = '用户创建失败';
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
    <title><?php echo SITE_NAME; ?> - 创建用户</title>
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
            <?php if ($user->hasPermission('permission_assign')): ?>
            <li><a href="permissions.php" class="nav-link">🔐 权限管理</a></li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="main-content">
        <h1 class="page-title">创建新用户</h1>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="form-group">

                    <label class="form-label">姓名 <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?php echo $_POST['name'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">用户名 <span class="required">*</span></label>
                    <input type="text" name="username" class="form-control" required value="<?php echo $_POST['username'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">密码 <span class="required">*</span></label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">确认密码 <span class="required">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">用户代码 <span class="required">*</span></label>
                    <input type="text" name="code" class="form-control" required value="<?php echo $_POST['code'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">职务 <span class="required">*</span></label>
                    <input type="text" name="position" class="form-control" required value="<?php echo $_POST['position'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">分配权限</label>
                    <div class="permissions-grid">
                        <?php foreach (User::PERMISSIONS as $key => $name): 
                            if ($user->hasPermission($key)): ?>
                            <div class="permission-item">
                                <input type="checkbox" name="permissions[]" value="<?php echo $key; ?>" 
                                       id="perm_<?php echo $key; ?>">
                                <label for="perm_<?php echo $key; ?>" class="permission-label"><?php echo $name; ?></label>
                            </div>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">创建用户</button>
                    <a href="users.php" class="btn btn-secondary">取消</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
