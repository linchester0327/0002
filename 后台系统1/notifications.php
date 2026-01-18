<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permission_check.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();

$error = '';
$success = '';

// 处理标记为已读
if (isset($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    $notification = Notification::load($notification_id);
    if ($notification && $notification->user_id === $user->id) {
        if ($notification->markAsRead()) {
            header('Location: notifications.php?msg=标记成功');
            exit();
        } else {
            $error = '标记失败';
        }
    } else {
        $error = '权限不足或通知不存在';
    }
}

// 处理删除通知
if (isset($_GET['delete'])) {
    $notification_id = $_GET['delete'];
    $notification = Notification::load($notification_id);
    if ($notification && $notification->user_id === $user->id) {
        if ($notification->delete()) {
            header('Location: notifications.php?msg=删除成功');
            exit();
        } else {
            $error = '删除失败';
        }
    } else {
        $error = '权限不足或通知不存在';
    }
}

// 处理标记所有为已读
if (isset($_GET['mark_all_read'])) {
    $notifications = Notification::getNotificationsByUser($user->id, true);
    foreach ($notifications as $notification) {
        $notification->markAsRead();
    }
    header('Location: notifications.php?msg=全部标记成功');
    exit();
}

// 获取通知列表
$notifications = Notification::getNotificationsByUser($user->id);

// 获取未读通知数量
$unread_count = NotificationManager::getUnreadCount($user->id);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 通知</title>
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
            <li><a href="notifications.php" class="nav-link active">🔔 通知</a></li>
            <li><a href="applications.php" class="nav-link">📝 申请管理</a></li>
            <?php if ($user->hasPermission('system_config')): ?>
            <li><a href="data_management.php" class="nav-link">💾 数据管理</a></li>
            <?php endif; ?>
            <?php if ($user->hasPermission('permission_assign')): ?>
            <li><a href="permissions.php" class="nav-link">🔐 权限管理</a></li>
            <?php endif; ?>
            <?php if ($user->isAdmin()): ?>
            <li><a href="password.php" class="nav-link">🔑 修改密码</a></li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="main-content">
        <h1 class="page-title">通知</h1>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="action-bar">
            <div>
                共 <?php echo count($notifications); ?> 条通知
                <?php if ($unread_count > 0): ?>
                <span class="badge">未读 <?php echo $unread_count; ?></span>
                <?php endif; ?>
            </div>
            <?php if ($unread_count > 0): ?>
            <a href="notifications.php?mark_all_read" class="btn btn-secondary">标记全部已读</a>
            <?php endif; ?>
        </div>
        
        <div class="notification-list">
            <?php if (empty($notifications)): ?>
            <div class="empty-message">
                没有通知
            </div>
            <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
            <div class="notification-item <?php echo !$notification->is_read ? 'unread' : ''; ?>">
                <div class="notification-header">
                    <div class="notification-title">
                        <?php echo htmlspecialchars($notification->title); ?>
                        <span class="type-badge type-<?php echo $notification->type; ?>">
                            <?php echo Notification::TYPES[$notification->type]; ?>
                        </span>
                    </div>
                    <div class="notification-meta">
                        <?php echo date('Y-m-d H:i', strtotime($notification->created_at)); ?>
                    </div>
                </div>
                
                <div class="notification-content">
                    <?php echo htmlspecialchars($notification->content); ?>
                </div>
                
                <div class="notification-actions">
                    <?php if (!$notification->is_read): ?>
                    <a href="notifications.php?mark_read=<?php echo $notification->id; ?>" class="btn btn-primary">标记已读</a>
                    <?php endif; ?>
                    <a href="notifications.php?delete=<?php echo $notification->id; ?>" onclick="return confirm('确定删除？')" class="btn btn-danger">删除</a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>