<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permission_check.php';

Auth::requireLogin();
PermissionCheck::require('permission_assign');
$user = Auth::getCurrentUser();

$error = '';
$success = '';

// 处理权限分配
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        $error = '无效的请求令牌';
    } else if (!isset($_POST['user_id']) || !isset($_POST['permissions'])) {
        $error = '参数错误';
    } else {
        $target_user = User::load($_POST['user_id']);
        if (!$target_user) {
            $error = '用户不存在';
        } else {
            $permissions = $_POST['permissions'] ?? [];
            
            // 验证权限分配
            foreach ($permissions as $perm) {
                if (!$user->hasPermission($perm)) {
                    $error = '您没有分配此权限的权限';
                    break;
                }
            }
            
            if (!$error) {
                if ($target_user->updatePermissions($permissions, $user)) {
                    $success = '权限分配成功';
                } else {
                    $error = '权限分配失败';
                }
            }
        }
    }
}

// 获取所有用户
$all_users = User::getAllUsers();
// 过滤出当前用户可以操作的用户
$manageable_users = [];
foreach ($all_users as $u) {
    if ($user->canOperateUser($u) && $u->id !== $user->id && !$u->isAdmin()) {
        $manageable_users[] = $u;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 权限管理</title>
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
            <?php if ($user->hasPermission('system_config')): ?>
            <li><a href="data_management.php" class="nav-link">💾 数据管理</a></li>
            <?php endif; ?>
            <li><a href="permissions.php" class="nav-link active">🔐 权限管理</a></li>
            <?php if ($user->isAdmin()): ?>
            <li><a href="password.php" class="nav-link">🔑 修改密码</a></li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="main-content">
        <h1 class="page-title">权限管理</h1>
        
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

                    <label class="form-label">选择用户 <span class="required">*</span></label>
                    <div id="userSelection">
                        <?php if (empty($manageable_users)): ?>
                        <p class="no-manageable-users">没有可管理的用户</p>
                        <?php else: ?>
                        <?php foreach ($manageable_users as $u): ?>
                        <div class="user-card" onclick="selectUser('<?php echo $u->id; ?>', this)" data-permissions='<?php echo json_encode($u->permissions); ?>'>
                            <input type="radio" name="user_id" value="<?php echo $u->id; ?>" 
                                   id="user_<?php echo $u->id; ?>" class="user-radio">
                            <div class="user-info">
                                <h4><?php echo htmlspecialchars($u->name); ?></h4>
                                <p>用户名: <?php echo htmlspecialchars($u->username); ?> | 职务: <?php echo htmlspecialchars($u->position); ?></p>
                                <p>当前权限: <?php echo count($u->permissions); ?> 项</p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">分配权限</label>
                    <div class="permissions-grid" id="permissionsGrid">
                        <?php foreach (User::PERMISSIONS as $key => $name): 
                            if ($user->hasPermission($key)): ?>
                            <div class="permission-item">
                                <input type="checkbox" name="permissions[]" value="<?php echo $key; ?>" 
                                       id="perm_<?php echo $key; ?>">
                                <label for="perm_<?php echo $key; ?>" class="permission-label" 
                                       title="权限说明：<?php echo htmlspecialchars(User::PERMISSIONS[$key]); ?>">
                                    <?php echo $name; ?>
                                </label>
                                <span class="permission-description" 
                                      id="desc_<?php echo $key; ?>"></span>
                            </div>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">保存权限</button>
                    <a href="dashboard.php" class="btn btn-secondary">返回</a>
                </div>
            </form>
        </div>
    </div>
    
    <script src="js/permissions.js"></script>
</body>
</html>