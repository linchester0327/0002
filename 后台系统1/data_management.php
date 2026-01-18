<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permission_check.php';

Auth::requireLogin();
PermissionCheck::require('system_config');
$user = Auth::getCurrentUser();

$error = '';
$success = '';

// 处理备份数据
if (isset($_GET['action']) && $_GET['action'] === 'backup') {
    $backup_dir = DATA_DIR . 'backups' . DIRECTORY_SEPARATOR;
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    $backup_filename = 'backup_' . date('Ymd_His') . '.zip';
    $backup_path = $backup_dir . $backup_filename;
    
    // 创建备份
    if (create_backup($backup_path)) {
        $success = '备份成功';
        log_info('数据备份', ['user_id' => $user->id, 'filename' => $backup_filename]);
    } else {
        $error = '备份失败';
        log_error('备份失败', ['user_id' => $user->id]);
    }
}

// 处理恢复数据
if (isset($_POST['restore_backup'])) {
    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        $error = '请选择备份文件';
    } else {
        $backup_file = $_FILES['backup_file'];
        $errors = validate_file_upload($backup_file, ['zip'], 50 * 1024 * 1024);
        
        if (empty($errors)) {
            if (restore_backup($backup_file['tmp_name'])) {
                $success = '恢复成功';
                log_info('数据恢复', ['user_id' => $user->id]);
            } else {
                $error = '恢复失败';
                log_error('恢复失败', ['user_id' => $user->id]);
            }
        } else {
            $error = implode(', ', $errors);
        }
    }
}

// 处理清理日志
if (isset($_GET['action']) && $_GET['action'] === 'clean_logs') {
    if (clean_old_logs(7)) {
        $success = '日志清理成功';
        log_info('清理日志', ['user_id' => $user->id]);
    } else {
        $error = '日志清理失败';
        log_error('清理日志失败', ['user_id' => $user->id]);
    }
}

// 获取系统状态
$system_status = get_system_status();
// 获取备份列表
$backups = get_backup_list();
// 获取最新日志
$latest_logs = get_latest_logs(50);

// 辅助函数
function create_backup($backup_path) {
    $zip = new ZipArchive();
    if ($zip->open($backup_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        return false;
    }
    
    // 添加数据文件
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(DATA_DIR),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen(DATA_DIR));
            $zip->addFile($filePath, $relativePath);
        }
    }
    
    $zip->close();
    return file_exists($backup_path);
}

function restore_backup($backup_file) {
    $zip = new ZipArchive();
    if ($zip->open($backup_file) !== TRUE) {
        return false;
    }
    
    // 清空数据目录
    $data_dir = DATA_DIR;
    $files = glob($data_dir . '*');
    foreach ($files as $file) {
        if (is_dir($file) && basename($file) !== 'backups') {
            delete_directory($file);
        } elseif (is_file($file)) {
            unlink($file);
        }
    }
    
    // 解压备份
    $result = $zip->extractTo($data_dir);
    $zip->close();
    return $result;
}

function delete_directory($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            delete_directory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

function clean_old_logs($days = 7) {
    $log_dir = DATA_DIR . 'logs' . DIRECTORY_SEPARATOR;
    $files = glob($log_dir . '*.log');
    $cutoff = time() - ($days * 24 * 3600);
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
        }
    }
    return true;
}

function get_system_status() {
    $status = [];
    
    // 磁盘使用情况
    $status['disk'] = [
        'total' => disk_total_space(DATA_DIR),
        'free' => disk_free_space(DATA_DIR),
        'used' => disk_total_space(DATA_DIR) - disk_free_space(DATA_DIR)
    ];
    
    // 系统信息
    $status['system'] = [
        'php_version' => PHP_VERSION,
        'server_os' => PHP_OS,
        'server_name' => $_SERVER['SERVER_NAME'] ?? 'localhost',
        'current_time' => date('Y-m-d H:i:s')
    ];
    
    // 数据统计
    $status['data'] = [
        'users' => count(glob(DATA_DIR . 'users' . DIRECTORY_SEPARATOR . '*.json')),
        'todos' => count(glob(DATA_DIR . 'todos' . DIRECTORY_SEPARATOR . '*.json')),
        'chats' => count(glob(DATA_DIR . 'chats' . DIRECTORY_SEPARATOR . '*.json')),
        'notifications' => count(glob(DATA_DIR . 'notifications' . DIRECTORY_SEPARATOR . '*.json')),
        'applications' => count(glob(DATA_DIR . 'applications' . DIRECTORY_SEPARATOR . '*.json')),
        'logs' => count(glob(DATA_DIR . 'logs' . DIRECTORY_SEPARATOR . '*.log')),
        'backups' => count(glob(DATA_DIR . 'backups' . DIRECTORY_SEPARATOR . '*.zip'))
    ];
    
    return $status;
}

function get_backup_list() {
    $backups = [];
    $backup_dir = DATA_DIR . 'backups' . DIRECTORY_SEPARATOR;
    if (!is_dir($backup_dir)) {
        return $backups;
    }
    
    $files = glob($backup_dir . '*.zip');
    foreach ($files as $file) {
        $backups[] = [
            'filename' => basename($file),
            'size' => filesize($file),
            'created_at' => date('Y-m-d H:i:s', filemtime($file)),
            'path' => $file
        ];
    }
    
    usort($backups, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return $backups;
}

function get_latest_logs($limit = 50) {
    $logs = [];
    $log_dir = DATA_DIR . 'logs' . DIRECTORY_SEPARATOR;
    $files = glob($log_dir . '*.log');
    
    // 按日期排序，最新的在前
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    foreach ($files as $file) {
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        foreach (array_reverse($lines) as $line) {
            $log_data = json_decode($line, true);
            if ($log_data) {
                $logs[] = $log_data;
                if (count($logs) >= $limit) {
                    break 2;
                }
            }
        }
    }
    
    return $logs;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 数据管理</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="header">
        <div class="logo"><?php echo SITE_NAME; ?></div>
        <div class="user-info">
            <div>
                <strong><?php echo htmlspecialchars($user->name); ?></strong>
                <div style="font-size: 12px; color: #718096;"><?php echo htmlspecialchars($user->position); ?></div>
            </div>
            <div class="user-avatar"><?php echo substr($user->name, 0, 1); ?></div>
            <a href="logout.php" style="background:#fed7d7;color:#c53030;padding:8px 15px;border-radius:6px;text-decoration:none;">退出</a>
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
            <li><a href="data_management.php" class="nav-link active">💾 数据管理</a></li>
            <?php if ($user->hasPermission('permission_assign')): ?>
            <li><a href="permissions.php" class="nav-link">🔐 权限管理</a></li>
            <?php endif; ?>
            <?php if ($user->isAdmin()): ?>
            <li><a href="password.php" class="nav-link">🔑 修改密码</a></li>
            <?php endif; ?>
        </ul>
    </div>
    
    <div class="main-content">
        <h1 class="page-title">数据管理</h1>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <!-- 系统状态 -->
        <div class="card">
            <h2 class="card-title">📊 系统状态</h2>
            
            <div class="grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $system_status['data']['users']; ?></div>
                    <div class="stat-label">用户数量</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $system_status['data']['todos']; ?></div>
                    <div class="stat-label">待办事项</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $system_status['data']['chats']; ?></div>
                    <div class="stat-label">聊天数量</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $system_status['data']['notifications']; ?></div>
                    <div class="stat-label">通知数量</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $system_status['data']['applications']; ?></div>
                    <div class="stat-label">申请数量</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $system_status['data']['backups']; ?></div>
                    <div class="stat-label">备份数量</div>
                </div>
            </div>
            
            <!-- 磁盘使用情况 -->
            <div style="margin-top: 30px;">
                <h3 style="margin-bottom: 15px;">💾 磁盘使用情况</h3>
                <?php
                $disk_used_percent = ($system_status['disk']['used'] / $system_status['disk']['total']) * 100;
                ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span>已使用: <?php echo round($system_status['disk']['used'] / (1024 * 1024), 2); ?> MB</span>
                    <span>总空间: <?php echo round($system_status['disk']['total'] / (1024 * 1024), 2); ?> MB</span>
                    <span><?php echo round($disk_used_percent, 1); ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo min($disk_used_percent, 100); ?>%;"></div>
                </div>
            </div>
            
            <!-- 系统信息 -->
            <div style="margin-top: 30px;">
                <h3 style="margin-bottom: 15px;">⚙️ 系统信息</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div><strong>PHP版本:</strong> <?php echo $system_status['system']['php_version']; ?></div>
                    <div><strong>服务器系统:</strong> <?php echo $system_status['system']['server_os']; ?></div>
                    <div><strong>服务器名称:</strong> <?php echo $system_status['system']['server_name']; ?></div>
                    <div><strong>当前时间:</strong> <?php echo $system_status['system']['current_time']; ?></div>
                </div>
            </div>
        </div>
        
        <!-- 数据备份 -->
        <div class="card">
            <h2 class="card-title">🗄️ 数据备份</h2>
            
            <div style="display: flex; gap: 15px; margin-bottom: 30px;">
                <a href="data_management.php?action=backup" class="btn btn-primary" onclick="return confirm('确定要备份数据吗？')">
                    📥 创建备份
                </a>
                <form method="POST" enctype="multipart/form-data" style="display: inline;">
                    <input type="file" name="backup_file" accept=".zip" style="display: none;" id="backupFile">
                    <label for="backupFile" class="btn btn-secondary" style="cursor: pointer;">
                        📤 选择备份文件
                    </label>
                    <button type="submit" name="restore_backup" class="btn btn-success" onclick="return confirm('确定要恢复数据吗？这将覆盖现有数据！')">
                        恢复数据
                    </button>
                </form>
                <a href="data_management.php?action=clean_logs" class="btn btn-danger" onclick="return confirm('确定要清理7天前的日志吗？')">
                    🧹 清理日志
                </a>
            </div>
            
            <!-- 备份列表 -->
            <h3 style="margin-bottom: 15px;">备份历史</h3>
            <?php if (empty($backups)): ?>
            <div style="text-align: center; color: #718096; padding: 40px 0;">
                暂无备份
            </div>
            <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>文件名</th>
                            <th>大小</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($backup['filename']); ?></td>
                            <td><?php echo round($backup['size'] / (1024 * 1024), 2); ?> MB</td>
                            <td><?php echo $backup['created_at']; ?></td>
                            <td>
                                <a href="<?php echo str_replace(DATA_DIR, 'data/', $backup['path']); ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                                    下载
                                </a>
                                <a href="#" onclick="if(confirm('确定删除？')) window.location.href='?delete_backup=<?php echo urlencode($backup['filename']); ?>'" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">
                                    删除
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- 系统日志 -->
        <div class="card">
            <h2 class="card-title">📋 系统日志</h2>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>级别</th>
                            <th>消息</th>
                            <th>用户</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($latest_logs)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #718096; padding: 40px 0;">
                                暂无日志
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($latest_logs as $log): ?>
                        <tr>
                            <td><?php echo $log['timestamp']; ?></td>
                            <td>
                                <span class="log-level log-level-<?php echo $log['level']; ?>">
                                    <?php echo $log['level']; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($log['message']); ?></td>
                            <td><?php echo htmlspecialchars($log['user_id']); ?></td>
                            <td><?php echo htmlspecialchars($log['ip']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>