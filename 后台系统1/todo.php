<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permission_check.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();

$error = '';
$success = '';

// 处理创建TODO
if (isset($_POST['create_todo'])) {
    PermissionCheck::require('todo_create');
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        $error = '无效的请求令牌';
    } else {
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $assignee_id = $_POST['assignee_id'] ?? $user->id;
        $priority = $_POST['priority'] ?? 'medium';
        $due_date = $_POST['due_date'] ?? '';
        
        if (empty($title)) {
            $error = '请填写TODO标题';
        } else {
            $todo = Todo::createTodo([
                'title' => $title,
                'description' => $description,
                'assignee_id' => $assignee_id,
                'priority' => $priority,
                'due_date' => $due_date
            ], $user->id);
            
            if ($todo) {
                $success = 'TODO创建成功';
            } else {
                $error = 'TODO创建失败';
            }
        }
    }
}

// 处理更新TODO状态
if (isset($_GET['status'])) {
    PermissionCheck::require('todo_check');
    
    $todo = Todo::load($_GET['status']);
    if ($todo) {
        if ($todo->assignee_id === $user->id || $todo->creator_id === $user->id) {
            $new_status = $_GET['new_status'] ?? 'completed';
            if ($todo->updateStatus($new_status)) {
                header('Location: todo.php?msg=状态更新成功');
                exit();
            } else {
                $error = '状态更新失败';
            }
        } else {
            $error = '权限不足';
        }
    } else {
        $error = 'TODO不存在';
    }
}

// 处理删除TODO
if (isset($_GET['delete'])) {
    $todo = Todo::load($_GET['delete']);
    if ($todo && $todo->creator_id === $user->id) {
        if ($todo->delete()) {
            header('Location: todo.php?msg=删除成功');
            exit();
        } else {
            $error = '删除失败';
        }
    } else {
        $error = '权限不足或TODO不存在';
    }
}

// 获取TODO列表
$todos = Todo::getTodosByUser($user->id, true);

// 获取可分配的用户列表
$all_users = User::getAllUsers();
$assignable_users = [];
foreach ($all_users as $u) {
    if ($u->id === $user->id || $user->canOperateUser($u)) {
        $assignable_users[] = $u;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 待办事项</title>
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
            <li><a href="todo.php" class="nav-link active">✅ 待办事项</a></li>
            <li><a href="chat.php" class="nav-link">💬 聊天</a></li>
            <li><a href="notifications.php" class="nav-link">🔔 通知</a></li>
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
        <h1 class="page-title">待办事项</h1>
        
        <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_GET['msg']); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <!-- 创建TODO表单 -->
        <?php if ($user->hasPermission('todo_manage')): ?>
        <div class="form-container">
            <h2 class="form-title">创建新待办</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="form-group">
                    <label class="form-label">标题 <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">描述</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">分配给</label>
                    <select name="assignee_id" class="form-control">
                        <?php foreach ($assignable_users as $u): ?>
                        <option value="<?php echo $u->id; ?>"><?php echo htmlspecialchars($u->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">优先级</label>
                    <select name="priority" class="form-control">
                        <option value="low">低</option>
                        <option value="medium" selected>中</option>
                        <option value="high">高</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">截止日期</label>
                    <input type="date" name="due_date" class="form-control">
                </div>
                
                <button type="submit" name="create_todo" class="btn btn-primary">创建</button>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- TODO列表 -->
        <div class="todo-list">
            <h2 class="section-title">我的待办</h2>
            
            <?php if (empty($todos)): ?>
            <p class="empty-message">没有待办事项</p>
            <?php else: ?>
            <?php foreach ($todos as $todo): 
                $creator = $todo->getCreator();
                $assignee = $todo->getAssignee();
            ?>
            <div class="todo-item">
                <div class="todo-header">
                    <div>
                        <h3 class="todo-title"><?php echo htmlspecialchars($todo->title); ?></h3>
                        <div class="todo-meta">
                            <span class="status-badge status-<?php echo $todo->status; ?>">
                                <?php echo Todo::STATUSES[$todo->status]; ?>
                            </span>
                            <span class="priority-badge priority-<?php echo $todo->priority; ?>">
                                优先级: <?php echo Todo::PRIORITIES[$todo->priority]; ?>
                            </span>
                            <?php if ($todo->due_date): ?>
                            <span>截止: <?php echo $todo->due_date; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($todo->creator_id === $user->id): ?>
                    <a href="?delete=<?php echo $todo->id; ?>" onclick="return confirm('确定删除待办吗？')" class="btn btn-danger btn-sm">删除</a>
                    <?php endif; ?>
                </div>
                
                <?php if ($todo->description): ?>
                <div class="todo-description">
                    <?php echo htmlspecialchars($todo->description); ?>
                </div>
                <?php endif; ?>
                
                <div class="todo-meta todo-meta-bottom">
                    <span>创建者: <?php echo $creator ? htmlspecialchars($creator->name) : '未知'; ?></span>
                    <span>分配给: <?php echo $assignee ? htmlspecialchars($assignee->name) : '未知'; ?></span>
                    <span>创建时间: <?php echo $todo->created_at; ?></span>
                </div>
                
                <div class="todo-actions">
                    <?php if ($todo->status === 'pending'): ?>
                    <a href="?status=<?php echo $todo->id; ?>&new_status=in_progress" class="btn btn-warning">开始处理</a>
                    <?php elseif ($todo->status === 'in_progress'): ?>
                    <a href="?status=<?php echo $todo->id; ?>&new_status=completed" class="btn btn-success">标记完成</a>
                    <?php endif; ?>
                    <?php if ($todo->status === 'completed'): ?>
                    <a href="?status=<?php echo $todo->id; ?>&new_status=pending" class="btn btn-primary">重新打开</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>