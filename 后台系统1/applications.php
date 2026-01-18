<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permission_check.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();

$error = '';
$success = '';

// 处理创建申请
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_application'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        $error = '无效的请求令牌';
    } else {
        $type = $_POST['type'];
        $title = $_POST['title'];
        $content = $_POST['content'];
        
        if (empty($title) || empty($content)) {
            $error = '标题和内容不能为空';
        } else {
            $application = Application::createApplication([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'content' => $content
            ]);
            
            if ($application) {
                // 发送通知给管理员
                $admins = User::getAdmins();
                foreach ($admins as $admin) {
                    Notification::createApplicationNotification(
                        $admin->id,
                        $application->id,
                        '新的申请需要处理',
                        "用户 {$user->name} 提交了新的申请：{$title}"
                    );
                }
                
                $success = '申请创建成功';
            } else {
                $error = '申请创建失败';
            }
        }
    }
}

// 处理批准申请
if (isset($_GET['approve'])) {
    $application_id = $_GET['approve'];
    $application = Application::load($application_id);
    if ($application && $user->hasPermission('application_approve')) {
        if ($application->approve($user->id)) {
            // 发送通知给申请人
            Notification::createApplicationNotification(
                $application->user_id,
                $application->id,
                '申请已批准',
                "您的申请 \"{$application->title}\" 已被批准"
            );
            
            header('Location: applications.php?msg=批准成功');
            exit();
        } else {
            $error = '批准失败';
        }
    } else {
        $error = '权限不足或申请不存在';
    }
}

// 处理拒绝申请
if (isset($_GET['reject'])) {
    $application_id = $_GET['reject'];
    $application = Application::load($application_id);
    if ($application && $user->hasPermission('application_approve')) {
        $reason = $_GET['reason'] ?? '未提供原因';
        if ($application->reject($user->id, $reason)) {
            // 发送通知给申请人
            Notification::createApplicationNotification(
                $application->user_id,
                $application->id,
                '申请已拒绝',
                "您的申请 \"{$application->title}\" 已被拒绝。原因：{$reason}"
            );
            
            header('Location: applications.php?msg=拒绝成功');
            exit();
        } else {
            $error = '拒绝失败';
        }
    } else {
        $error = '权限不足或申请不存在';
    }
}

// 处理删除申请
if (isset($_GET['delete'])) {
    $application_id = $_GET['delete'];
    $application = Application::load($application_id);
    if ($application && ($application->user_id === $user->id || $user->hasPermission('application_manage'))) {
        if ($application->delete()) {
            header('Location: applications.php?msg=删除成功');
            exit();
        } else {
            $error = '删除失败';
        }
    } else {
        $error = '权限不足或申请不存在';
    }
}

// 获取申请列表
if ($user->hasPermission('application_manage')) {
    $applications = Application::getAllApplications();
} else {
    $applications = Application::getApplicationsByUser($user->id);
}

// 获取待处理申请数量
$pending_count = count(Application::getPendingApplications());
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 申请管理</title>
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
            <li><a href="applications.php" class="nav-link active">📝 申请管理</a></li>
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
        <h1 class="page-title">申请管理</h1>
        
        <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_GET['msg']); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <!-- 创建申请表单 -->
        <div class="form-container">
            <h2 class="card-title">创建新申请</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="form-group">
                    <label class="form-label">申请类型</label>
                    <select name="type" class="form-control" required>
                        <option value="account">账号相关</option>
                        <option value="permission">权限申请</option>
                        <option value="resource">资源申请</option>
                        <option value="other">其他申请</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">申请标题</label>
                    <input type="text" name="title" class="form-control" required placeholder="请输入申请标题">
                </div>
                <div class="form-group">
                    <label class="form-label">申请内容</label>
                    <textarea name="content" class="form-control" required placeholder="请详细描述您的申请内容"></textarea>
                </div>
                <button type="submit" name="create_application" class="btn btn-primary">提交申请</button>
            </form>
        </div>
        
        <!-- 申请列表 -->
        <div class="application-list">
            <div class="action-bar">
                <div>
                    申请列表
                    <?php if ($pending_count > 0): ?>
                    <span class="badge">待处理 <?php echo $pending_count; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (empty($applications)): ?>
            <div class="empty-message">
                暂无申请
            </div>
            <?php else: ?>
            <?php foreach ($applications as $application): ?>
            <div class="application-item">
                <div class="application-header">
                    <div>
                        <span class="type-badge type-<?php echo $application->type; ?>">
                            <?php echo Application::TYPES[$application->type]; ?>
                        </span>
                        <span class="status-badge status-<?php echo $application->status; ?>">
                            <?php echo Application::STATUS[$application->status]; ?>
                        </span>
                    </div>
                    <div class="application-meta">
                        <?php echo date('Y-m-d H:i', strtotime($application->created_at)); ?>
                    </div>
                </div>
                
                <h3 class="application-title">
                    <?php echo htmlspecialchars($application->title); ?>
                </h3>
                
                <div class="application-content">
                    <?php echo htmlspecialchars($application->content); ?>
                </div>
                
                <?php if ($application->status === 'rejected' && !empty($application->rejection_reason)): ?>
                <div class="rejection-reason">
                    <strong>拒绝原因：</strong><?php echo htmlspecialchars($application->rejection_reason); ?>
                </div>
                <?php endif; ?>
                
                <div class="application-meta">
                    <strong>申请人：</strong><?php echo htmlspecialchars(User::load($application->user_id)->name); ?>
                    <?php if (!empty($application->approver_id)): ?>
                    <strong>审批人：</strong><?php echo htmlspecialchars(User::load($application->approver_id)->name); ?>
                    <?php endif; ?>
                </div>
                
                <div class="application-actions">
                    <?php if ($application->status === 'pending' && $user->hasPermission('application_approve')): ?>
                    <a href="applications.php?approve=<?php echo $application->id; ?>" class="btn btn-success">批准</a>
                    <a href="applications.php?reject=<?php echo $application->id; ?>&reason=不符合要求" class="btn btn-danger">拒绝</a>
                    <?php endif; ?>
                    <?php if ($application->user_id === $user->id || $user->hasPermission('application_manage')): ?>
                    <a href="applications.php?delete=<?php echo $application->id; ?>" onclick="return confirm('确定删除？')" class="btn btn-secondary">删除</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>