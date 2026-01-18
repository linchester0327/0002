<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permission_check.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();

// 检查用户ID参数
if (!isset($_GET['id'])) {
    header('Location: users.php?msg=参数错误');
    exit();
}

$target_user = User::load($_GET['id']);
if (!$target_user) {
    header('Location: users.php?msg=用户不存在');
    exit();
}

// 检查操作权限
if (isset($_GET['action']) && $_GET['action'] === 'edit') {
    PermissionCheck::require('user_edit', $target_user->id);
    if (!$user->canOperateUser($target_user)) {
        header('Location: users.php?msg=权限不足');
        exit();
    }
}

$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    PermissionCheck::require('user_edit', $target_user->id);
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        $error = '无效的请求令牌';
    } else {
        $name = $_POST['name'] ?? '';
        $code = $_POST['code'] ?? '';
        $position = $_POST['position'] ?? '';
        $permissions = $_POST['permissions'] ?? [];
        
        // 验证必填字段
        if (empty($name) || empty($code) || empty($position)) {
            $error = '请填写所有必填字段';
        }
        
        // 验证权限分配
        if (!$error) {
            foreach ($permissions as $perm) {
                if (!$user->hasPermission($perm)) {
                    $error = '您没有分配此权限的权限';
                    break;
                }
            }
        }
        
        // 处理密码修改
        if (!$error && !empty($_POST['password'])) {
            if ($_POST['password'] !== $_POST['confirm_password']) {
                $error = '两次密码不一致';
            } else {
                $password_validation = validate_password_strength($_POST['password']);
                if (!$password_validation['valid']) {
                    $error = implode('<br>', $password_validation['errors']);
                } else {
                    $target_user->password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }
            }
        }
        
        if (!$error) {
            // 更新用户信息
            $target_user->name = $name;
            $target_user->code = $code;
            $target_user->position = $position;
            // 管理员默认拥有所有权限，不更新权限
            if (!$target_user->isAdmin()) {
                $target_user->permissions = $permissions;
            }
            $target_user->updated_at = date('Y-m-d H:i:s');
            
            if ($target_user->save()) {
                $success = '用户信息更新成功';
            } else {
                $error = '更新失败';
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
    <title><?php echo SITE_NAME; ?> - <?php echo isset($_GET['action']) ? '编辑用户' : '查看用户'; ?></title>
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
            <a href="logout.php" class="btn-logout">退出</a>
        </div>
    </div>
    
    <div class="nav-sidebar">
        <ul class="nav-menu">
            <li><a href="dashboard.php" class="nav-link">📊 仪表板</a></li>
            <li><a href="users.php" class="nav-link active">👥 用户管理</a></li>
            <li><a href="todo.php" class="nav-link">✅ 待办事项</a></li>
            <li><a href="chat.php" class="nav-link">💬 聊天</a></li>
            <li><a href="notifications.php" class="nav-link">🔔 通知</a></li>
            <li><a href="applications.php" class="nav-link">📝 申请管理</a></li>
            <?php if ($user->hasPermission('permission_assign')): ?>
            <li><a href="permissions.php" class="nav-link">🔐 权限管理</a></li>
            <?php endif; ?>
            <?php if ($user->hasPermission('system_config')): ?>
            <li><a href="data_management.php" class="nav-link">💾 数据管理</a></li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="main-content">
        <h1 class="page-title"><?php echo isset($_GET['action']) ? '编辑用户' : '查看用户'; ?></h1>
        
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
                    <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($target_user->name); ?>" <?php echo !isset($_GET['action']) ? 'readonly' : ''; ?>>
                </div>
                
                <div class="form-group">
                    <label class="form-label">用户名</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($target_user->username); ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">用户代码 <span class="required">*</span></label>
                    <input type="text" name="code" class="form-control" required value="<?php echo htmlspecialchars($target_user->code); ?>" <?php echo !isset($_GET['action']) ? 'readonly' : ''; ?>>
                </div>
                
                <div class="form-group">
                    <label class="form-label">职务 <span class="required">*</span></label>
                    <input type="text" name="position" class="form-control" required value="<?php echo htmlspecialchars($target_user->position); ?>" <?php echo !isset($_GET['action']) ? 'readonly' : ''; ?>>
                </div>
                
                <?php if (isset($_GET['action'])): ?>
                <div class="form-group">
                    <label class="form-label">密码（留空不修改）</label>
                    <input type="password" name="password" class="form-control" placeholder="留空不修改密码">
                </div>
                
                <div class="form-group">
                    <label class="form-label">确认密码</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="留空不修改密码">
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label class="form-label">分配权限</label>
                    <?php if ($target_user->isAdmin()): ?>
                    <div class="admin-permission-note">
                        <p class="note-text">管理员默认拥有所有权限，无需分配。</p>
                    </div>
                    <?php else: ?>
                    <div class="permissions-grid">
                        <?php foreach (User::PERMISSIONS as $key => $name): 
                            if ($user->hasPermission($key)): ?>
                            <div class="permission-item">
                                <input type="checkbox" name="permissions[]" value="<?php echo $key; ?>" 
                                       id="perm_<?php echo $key; ?>" 
                                       <?php echo in_array($key, $target_user->permissions) ? 'checked' : ''; ?>
                                       <?php echo !isset($_GET['action']) ? 'disabled' : ''; ?>>
                                <label for="perm_<?php echo $key; ?>"><?php echo $name; ?></label>
                            </div>
                        <?php endif; endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-actions">
                    <?php if (isset($_GET['action'])): ?>
                    <button type="submit" class="btn btn-primary">保存修改</button>
                    <?php endif; ?>
                    <a href="users.php" class="btn btn-secondary">返回</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>