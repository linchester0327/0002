<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permission_check.php';

Auth::requireLogin();
PermissionCheck::require('user_view');
$user = Auth::getCurrentUser();

// 处理删除用户
if (isset($_GET['delete'])) {
    PermissionCheck::require('user_delete', $_GET['delete']);
    $target_user = User::load($_GET['delete']);
    if ($target_user && $user->canOperateUser($target_user)) {
        $target_user->delete();
        header('Location: users.php?msg=删除成功');
        exit();
    }
}

// 处理创建部门
if (isset($_POST['create_department'])) {
    PermissionCheck::require('user_create');
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        $error = '无效的请求令牌';
    } else {
        $dept_name = $_POST['dept_name'] ?? '';
        $parent_id = $_POST['parent_id'] ?? null;
        
        if (empty($dept_name)) {
            $error = '请输入部门名称';
        } else {
            // 创建部门（使用特殊的用户类型）
            $dept_data = [
                'name' => $dept_name,
                'code' => 'DEPT_' . strtoupper(substr($dept_name, 0, 3)) . '_' . time(),
                'username' => 'dept_' . uniqid(),
                'password_hash' => password_hash('department', PASSWORD_DEFAULT),
                'position' => '部门',
                'parent_id' => $parent_id,
                'permissions' => [],
                'is_department' => true
            ];
            
            $department = User::createUser($dept_data, $user);
            if ($department) {
                header('Location: users.php?msg=部门创建成功');
                exit();
            } else {
                $error = '部门创建失败';
            }
        }
    }
}

$all_users = User::getAllUsers();

// 构建树形结构
function buildTree($users, $parent_id = null) {
    $tree = [];
    foreach ($users as $u) {
        if ($u->parent_id === $parent_id) {
            $children = buildTree($users, $u->id);
            if (!empty($children)) {
                // 确保使用已声明的属性
                $u->children = $children;
            }
            $tree[] = $u;
        }
    }
    return $tree;
}

$user_tree = buildTree($all_users);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 用户管理</title>
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
            <li><a href="users.php" class="nav-link active">👥 用户管理</a></li>
            <li><a href="todo.php" class="nav-link">✅ 待办事项</a></li>
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
        <div class="action-header">
            <h1 class="page-title">用户管理</h1>
            <div class="action-buttons">
                <?php if ($user->hasPermission('user_create')): ?>
                <a href="create_user.php" class="btn btn-primary">+ 创建用户</a>
                <button class="btn btn-success" id="createDeptBtn">+ 创建部门</button>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (isset($_GET['msg'])): ?>
        <div class="message-success">
            <?php echo htmlspecialchars($_GET['msg']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <!-- 部门创建表单 -->
        <div id="deptForm" class="dept-form-container">
            <h2 class="card-title">创建部门</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="form-group">
                    <label class="form-label">部门名称</label>
                    <input type="text" name="dept_name" class="form-control" placeholder="输入部门名称" required>
                </div>
                <div class="form-group">
                    <label class="form-label">上级部门</label>
                    <select name="parent_id" class="form-control">
                        <option value="">无（顶级部门）</option>
                        <?php foreach ($all_users as $u): 
                            if (isset($u->is_department) && $u->is_department): ?>
                        <option value="<?php echo $u->id; ?>">
                            <?php echo htmlspecialchars($u->name); ?>
                        </option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" name="create_department" class="btn btn-primary">创建部门</button>
                    <button type="button" class="btn btn-secondary" id="cancelDeptBtn">取消</button>
                </div>
            </form>
        </div>
        
        <!-- 树形结构用户列表 -->
        <div class="card">
            <h2 class="card-title">用户树形结构</h2>
            <div class="tree-container">
                <?php function renderTree($users, $user, $level = 0) { ?>
                    <?php foreach ($users as $u): 
                        $is_dept = isset($u->is_department) && $u->is_department;
                        $can_operate = $user->canOperateUser($u) && $u->id !== $user->id && !$u->isAdmin();
                    ?>
                    <div class="tree-node tree-node-level-<?php echo $level; ?>">
                        <div class="tree-node-content">
                            <?php if (isset($u->children)): ?>
                            <span class="tree-toggle">
                                ▶
                            </span>
                            <?php else: ?>
                            <span class="tree-toggle-placeholder"></span>
                            <?php endif; ?>
                            <span class="<?php echo $is_dept ? 'tree-node-dept' : 'tree-node-user'; ?>">
                                <?php echo $is_dept ? '🏢 ' : '👤 '; ?>
                                <?php echo htmlspecialchars($u->name); ?>
                                <?php echo $is_dept ? '(部门)' : ''; ?>
                            </span>
                            <span class="tree-node-code">
                                <?php echo htmlspecialchars($u->code); ?>
                            </span>
                            <div class="tree-node-actions">
                                <a href="edit_user.php?id=<?php echo $u->id; ?>" class="btn btn-sm btn-primary">查看</a>
                                <?php if ($can_operate && $user->hasPermission('user_edit')): ?>
                                <a href="edit_user.php?id=<?php echo $u->id; ?>&action=edit" class="btn btn-sm btn-success">编辑</a>
                                <?php endif; ?>
                                <?php if ($can_operate && $user->hasPermission('user_delete')): ?>
                                <a href="?delete=<?php echo $u->id; ?>" class="btn btn-sm btn-danger delete-btn">删除</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (isset($u->children)): ?>
                        <div class="tree-children">
                            <?php renderTree($u->children, $user, $level + 1); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php } ?>
                
                <?php renderTree($user_tree, $user); ?>
            </div>
        </div>
        
        <!-- 传统表格视图（可选） -->
        <div class="card">
            <h2 class="card-title">表格视图</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>姓名</th>
                            <th>用户名</th>
                            <th>代码</th>
                            <th>职务</th>
                            <th>类型</th>
                            <th>上级</th>
                            <th>权限数</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_users as $u): 
                            $parent = $u->parent_id ? User::load($u->parent_id) : null;
                            $can_operate = $user->canOperateUser($u) && $u->id !== $user->id && !$u->isAdmin();
                            $is_dept = isset($u->is_department) && $u->is_department;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u->name); ?></td>
                            <td><?php echo htmlspecialchars($u->username); ?></td>
                            <td><?php echo htmlspecialchars($u->code); ?></td>
                            <td><?php echo htmlspecialchars($u->position); ?></td>
                            <td><?php echo $is_dept ? '部门' : '用户'; ?></td>
                            <td><?php echo $parent ? htmlspecialchars($parent->name) : '-'; ?></td>
                            <td><?php echo count($u->permissions); ?></td>
                            <td>
                                <div class="btn-group">
                                    <a href="edit_user.php?id=<?php echo $u->id; ?>" class="btn btn-primary">查看</a>
                                    <?php if ($can_operate && $user->hasPermission('user_edit')): ?>
                                    <a href="edit_user.php?id=<?php echo $u->id; ?>&action=edit" class="btn btn-success">编辑</a>
                                    <?php endif; ?>
                                    <?php if ($can_operate && $user->hasPermission('user_delete')): ?>
                                    <a href="?delete=<?php echo $u->id; ?>" class="btn btn-danger delete-btn">删除</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="js/tree-toggle.js"></script>
</body>
</html>
