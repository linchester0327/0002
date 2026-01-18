<?php
// dashboard.php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permission_check.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();

// 获取统计数据
$total_users = count(User::getAllUsers());
$subordinates_count = count($user->getSubordinates(false));
$pending_todos = count(Todo::getPendingTodosByUser($user->id));
$unread_notifications = NotificationManager::getUnreadCount($user->id);
$pending_applications = count(Application::getPendingApplications());

// 获取最近的活动
$recent_todos = array_slice(Todo::getTodosByUser($user->id, true), 0, 5);
$recent_notifications = array_slice(Notification::getNotificationsByUser($user->id), 0, 5);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 仪表板</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="header">
        <div class="header-left">
            <button id="menu-toggle" class="menu-toggle-btn">☰</button>
            <div class="logo"><?php echo SITE_NAME; ?></div>
        </div>
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
            <li><a href="dashboard.php" class="nav-link active">📊 <span>仪表板</span></a></li>
            <li><a href="users.php" class="nav-link">👥 <span>用户管理</span></a></li>
            <li><a href="todo.php" class="nav-link">✅ <span>待办事项</span></a></li>
            <li><a href="chat.php" class="nav-link">💬 <span>聊天</span></a></li>
            <li><a href="notifications.php" class="nav-link">
                🔔 <span>通知</span>
                <?php if ($unread_notifications > 0): ?>
                <span class="badge"><?php echo $unread_notifications; ?></span>
                <?php endif; ?>
            </a></li>
            <li><a href="applications.php" class="nav-link">
                📝 <span>申请管理</span>
                <?php if ($pending_applications > 0 && $user->hasPermission('application_approve')): ?>
                <span class="badge"><?php echo $pending_applications; ?></span>
                <?php endif; ?>
            </a></li>
            <?php if ($user->hasPermission('permission_assign')): ?>
            <li><a href="permissions.php" class="nav-link">🔐 <span>权限管理</span></a></li>
            <?php endif; ?>
            <?php if ($user->isAdmin()): ?>
            <li><a href="password.php" class="nav-link">🔑 <span>修改密码</span></a></li>
            <?php endif; ?>
            <?php if ($user->hasPermission('system_config')): ?>
            <li><a href="data_management.php" class="nav-link">💾 <span>数据管理</span></a></li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="main-content">
        <h1 class="page-title">仪表板</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_users; ?></div>
                <div class="stat-label">总用户数</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $subordinates_count; ?></div>
                <div class="stat-label">直接下属</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $pending_todos; ?></div>
                <div class="stat-label">待办事项</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $unread_notifications; ?></div>
                <div class="stat-label">未读通知</div>
            </div>
            <?php if ($user->hasPermission('application_approve')): ?>
            <div class="stat-card">
                <div class="stat-value"><?php echo $pending_applications; ?></div>
                <div class="stat-label">待处理申请</div>
            </div>
            <?php endif; ?>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($user->permissions); ?></div>
                <div class="stat-label">权限数量</div>
            </div>
        </div>
        
        <div class="grid">
            <!-- 最近待办 -->
            <div class="card">
                <h2 class="card-title">最近待办事项</h2>
                <?php if (empty($recent_todos)): ?>
                <div class="empty-message">
                    暂无待办事项
                </div>
                <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($recent_todos as $todo): ?>
                    <li class="activity-item">
                        <div class="activity-content">
                            <div class="activity-title"><?php echo htmlspecialchars($todo->title); ?></div>
                            <div class="activity-meta">
                                优先级: <?php echo Todo::PRIORITIES[$todo->priority]; ?> · 
                                状态: <?php echo Todo::STATUSES[$todo->status]; ?> · 
                                <?php echo $todo->created_at; ?>
                            </div>
                        </div>
                        <div class="activity-icon todo">
                            <?php echo $todo->status === 'completed' ? '✅' : '⏳'; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <div class="card-footer">
                    <a href="todo.php" class="btn btn-primary">查看全部</a>
                </div>
            </div>
            
            <!-- 最近通知 -->
            <div class="card">
                <h2 class="card-title">最近通知</h2>
                <?php if (empty($recent_notifications)): ?>
                <div class="empty-message">
                    暂无通知
                </div>
                <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($recent_notifications as $notification): ?>
                    <li class="activity-item">
                        <div class="activity-content">
                            <div class="activity-title"><?php echo htmlspecialchars($notification->title); ?></div>
                            <div class="activity-meta">
                                <?php echo Notification::TYPES[$notification->type]; ?> · 
                                <?php echo date('Y-m-d H:i', strtotime($notification->created_at)); ?>
                                <?php if (!$notification->is_read): ?>
                                <span class="unread-badge">(未读)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="activity-icon notification">
                            <?php echo $notification->is_read ? '📌' : '🔔'; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <div class="card-footer">
                    <a href="notifications.php" class="btn btn-primary">查看全部</a>
                </div>
            </div>
        </div>    </div>

    <script src="js/menu-toggle.js"></script>
</body>
</html>