<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permission_check.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();

$error = '';
$success = '';

// 处理创建群组
if (isset($_POST['create_group'])) {
    PermissionCheck::require('chat_group');
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        $error = '无效的请求令牌';
    } else {
        $group_name = $_POST['group_name'] ?? '';
        $participants = $_POST['participants'] ?? [];
        
        if (empty($group_name)) {
            $error = '请输入群组名称';
        } elseif (empty($participants)) {
            $error = '请至少选择一个群组成员';
        } else {
            // 添加创建者到参与者
            $participants[] = $user->id;
            $participants = array_unique($participants);
            
            $chat = ChatManager::createGroupChat($group_name, $participants);
            if ($chat) {
                header('Location: chat.php?chat=' . $chat->id);
                exit();
            } else {
                $error = '创建群组失败';
            }
        }
    }
}

// 处理发送消息
if (isset($_POST['send_message'])) {
    PermissionCheck::require('chat_send');
    
    if (!validate_csrf_token($_POST['csrf_token'])) {
        $error = '无效的请求令牌';
    } else {
        $chat_id = $_POST['chat_id'] ?? '';
        $content = $_POST['content'] ?? '';
        
        if (empty($chat_id) || (empty($content) && empty($_FILES['attachments']['name'][0]))) {
            $error = '请输入消息内容或上传附件';
        } else {
            $chat = ChatManager::loadChat($chat_id);
            if (!$chat) {
                $error = '聊天不存在';
            } else {
                if (!in_array($user->id, $chat->participants)) {
                    $error = '您不是此聊天的参与者';
                } else {
                    // 处理附件上传
                    $attachments = [];
                    if (!empty($_FILES['attachments']['name'][0])) {
                        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                            if (!empty($tmp_name)) {
                                $file_name = $_FILES['attachments']['name'][$key];
                                $file_size = $_FILES['attachments']['size'][$key];
                                $file_type = $_FILES['attachments']['type'][$key];
                                
                                // 生成唯一文件名
                                $unique_name = uniqid() . '_' . $file_name;
                                $upload_path = DATA_DIR . 'attachments' . DIRECTORY_SEPARATOR;
                                
                                // 确保附件目录存在
                                if (!is_dir($upload_path)) {
                                    mkdir($upload_path, 0755, true);
                                }
                                
                                if (move_uploaded_file($tmp_name, $upload_path . $unique_name)) {
                                    $attachments[] = [
                                        'name' => $file_name,
                                        'path' => 'attachments/' . $unique_name,
                                        'size' => $file_size,
                                        'type' => $file_type
                                    ];
                                }
                            }
                        }
                    }
                    
                    $message_data = [
                        'chat_id' => $chat_id,
                        'sender_id' => $user->id,
                        'content' => $content,
                        'attachments' => $attachments
                    ];
                    
                    $message = Message::createMessage($message_data);
                    
                    if ($message) {
                        // 重定向回聊天页面
                        header('Location: chat.php?chat=' . $chat_id);
                        exit();
                    } else {
                        $error = '消息发送失败';
                    }
                }
            }
        }
    }
}

// 处理删除消息
if (isset($_GET['delete_message'])) {
    $message_id = $_GET['delete_message'] ?? '';
    $chat_id = $_GET['chat_id'] ?? '';
    
    if (empty($message_id) || empty($chat_id)) {
        $error = '参数错误';
    } else {
        $message = Message::load($message_id, $chat_id);
        if ($message && ($message->sender_id === $user->id || $user->hasPermission('chat_delete'))) {
            if ($message->delete()) {
                header('Location: chat.php?chat=' . $chat_id);
                exit();
            } else {
                $error = '删除失败';
            }
        } else {
            $error = '权限不足或消息不存在';
        }
    }
}

// 处理创建私聊
if (isset($_GET['chat_with'])) {
    $target_user_id = $_GET['chat_with'];
    $target_user = User::load($target_user_id);
    
    if ($target_user && $user->canOperateUser($target_user)) {
        $chat = ChatManager::getOrCreatePrivateChat($user->id, $target_user_id);
        if ($chat) {
            header('Location: chat.php?chat=' . $chat->id);
            exit();
        } else {
            $error = '创建聊天失败';
        }
    } else {
        $error = '用户不存在或权限不足';
    }
}

// 获取当前聊天
$current_chat = null;
if (isset($_GET['chat'])) {
    $current_chat = ChatManager::loadChat($_GET['chat']);
    if (!$current_chat || !in_array($user->id, $current_chat->participants)) {
        $current_chat = null;
    }
}

// 获取聊天列表
$chats = ChatManager::getChatsByUser($user->id);

// 模拟聊天分组和置顶功能
// 实际项目中应该存储在数据库或文件中
$pinned_chats = [];
$grouped_chats = [
    'groups' => [],
    'private' => []
];

// 获取每个聊天的最近消息
$chat_last_messages = [];
foreach ($chats as $chat) {
    $messages = Message::getMessagesByChat($chat->id, 1);
    if (!empty($messages)) {
        $chat_last_messages[$chat->id] = end($messages);
    }
}

foreach ($chats as $chat) {
    if ($chat->type === 'group') {
        $grouped_chats['groups'][] = $chat;
    } else {
        $grouped_chats['private'][] = $chat;
    }
}

// 获取消息列表
$messages = [];
if ($current_chat) {
    $messages = Message::getMessagesByChat($current_chat->id);
}

// 获取可聊天的用户列表
$all_users = User::getAllUsers();
$chatable_users = [];
foreach ($all_users as $u) {
    if ($u->id !== $user->id && $user->canOperateUser($u)) {
        $chatable_users[] = $u;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 聊天</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="chat-styles.css">
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
            <li><a href="chat.php" class="nav-link active">💬 聊天</a></li>
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
        <h1 class="page-title">聊天</h1>
        
        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <!-- 群组创建表单 -->
        <?php if ($user->hasPermission('chat_group')): ?>
        <div class="group-create-form">
            <h2 class="section-title">创建群组</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="form-group">
                    <label class="form-label">群组名称 <span class="required">*</span></label>
                    <input type="text" name="group_name" class="form-control" placeholder="输入群组名称" required>
                </div>
                <div class="form-group">
                    <label class="form-label">选择成员 <span class="required">*</span></label>
                    <div class="user-checkbox-list">
                        <?php foreach ($chatable_users as $u): ?>
                        <div class="user-checkbox-item">
                            <input type="checkbox" name="participants[]" value="<?php echo $u->id; ?>" id="user_<?php echo $u->id; ?>">
                            <label for="user_<?php echo $u->id; ?>"><?php echo htmlspecialchars($u->name); ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" name="create_group" class="btn btn-primary">创建群组</button>
            </form>
        </div>
        <?php endif; ?>
        
        <div class="chat-container">
            <!-- 聊天列表 -->
            <div class="chat-list">
                <div class="chat-list-header">
                    <span>聊天列表</span>
                    <div class="chat-list-actions">
                        <button class="btn-icon" title="刷新">↻</button>
                        <button class="btn-icon" title="设置">⚙️</button>
                    </div>
                </div>
                
                <!-- 置顶聊天 -->
                <?php if (!empty($pinned_chats)): ?>
                <div class="chat-section">
                    <div class="chat-section-header">
                        <span>📌 置顶聊天</span>
                        <span>▼</span>
                    </div>
                    <div class="chat-section-content">
                        <?php foreach ($pinned_chats as $chat): ?>
                        <div class="chat-item chat-item-pinned <?php echo $current_chat && $current_chat->id === $chat->id ? 'active' : ''; ?>"
                             data-chat-id="<?php echo $chat->id; ?>">
                            <div class="chat-item-info">
                                <div>
                                    <div class="chat-item-name">
                                        <?php if ($chat->type === 'group'): ?>
                                        <?php echo htmlspecialchars($chat->name); ?>
                                        <?php else: ?>
                                        <?php 
                                        foreach ($chat->participants as $pid) {
                                            if ($pid !== $user->id) {
                                                $other_user = User::load($pid);
                                                if ($other_user) {
                                                    echo htmlspecialchars($other_user->name);
                                                }
                                                break;
                                            }
                                        }
                                        ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="chat-item-preview">
                                <?php if (isset($chat_last_messages[$chat->id])): ?>
                                <?php $last_msg = $chat_last_messages[$chat->id]; ?>
                                <?php $sender = $last_msg->getSender(); ?>
                                <?php if ($last_msg->sender_id === $user->id): ?>
                                我: <?php echo htmlspecialchars(mb_substr($last_msg->content ?? '', 0, 30)); ?>...
                                <?php else: ?>
                                <?php echo htmlspecialchars($sender ? $sender->name : '未知'); ?>: <?php echo htmlspecialchars(mb_substr($last_msg->content ?? '', 0, 30)); ?>...
                                <?php endif; ?>
                                <?php else: ?>
                                暂无消息
                                <?php endif; ?>
                            </div>
                                </div>
                                <div class="chat-item-time">
                                    <?php if (isset($chat_last_messages[$chat->id])): ?>
                                    <?php echo date('H:i', strtotime($chat_last_messages[$chat->id]->created_at)); ?>
                                    <?php else: ?>
                                    <?php echo date('H:i', strtotime($chat->created_at)); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="chat-item-actions">
                                <button class="btn-icon" title="取消置顶">📌</button>
                                <button class="btn-icon" title="删除">🗑️</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- 群组聊天 -->
                <?php if (!empty($grouped_chats['groups'])): ?>
                <div class="chat-section">
                    <div class="chat-section-header">
                        <span>🏢 群组聊天</span>
                        <span>▼</span>
                    </div>
                    <div class="chat-section-content">
                        <?php foreach ($grouped_chats['groups'] as $chat): ?>
                        <div class="chat-item <?php echo $current_chat && $current_chat->id === $chat->id ? 'active' : ''; ?>"
                             data-chat-id="<?php echo $chat->id; ?>">
                            <div class="chat-item-info">
                                <div>
                                    <div class="chat-item-name">
                                        <?php echo htmlspecialchars($chat->name); ?>
                                    </div>
                                    <div class="chat-item-preview">
                                        <?php if (isset($chat_last_messages[$chat->id])): ?>
                                        <?php $last_msg = $chat_last_messages[$chat->id]; ?>
                                        <?php $sender = $last_msg->getSender(); ?>
                                        <?php if ($last_msg->sender_id === $user->id): ?>
                                        我: <?php echo htmlspecialchars(mb_substr($last_msg->content ?? '', 0, 30)); ?>...
                                        <?php else: ?>
                                        <?php echo htmlspecialchars($sender ? $sender->name : '未知'); ?>: <?php echo htmlspecialchars(mb_substr($last_msg->content ?? '', 0, 30)); ?>...
                                        <?php endif; ?>
                                        <?php else: ?>
                                        暂无消息
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="chat-item-time">
                                    <?php if (isset($chat_last_messages[$chat->id])): ?>
                                    <?php echo date('H:i', strtotime($chat_last_messages[$chat->id]->created_at)); ?>
                                    <?php else: ?>
                                    <?php echo date('H:i', strtotime($chat->created_at)); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="chat-item-actions">
                                <button class="btn-icon" title="置顶">📌</button>
                                <button class="btn-icon" title="删除">🗑️</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- 私聊 -->
                <?php if (!empty($grouped_chats['private'])): ?>
                <div class="chat-section">
                    <div class="chat-section-header">
                        <span>👤 私聊</span>
                        <span>▼</span>
                    </div>
                    <div class="chat-section-content">
                        <?php foreach ($grouped_chats['private'] as $chat): ?>
                        <div class="chat-item <?php echo $current_chat && $current_chat->id === $chat->id ? 'active' : ''; ?>"
                             data-chat-id="<?php echo $chat->id; ?>">
                            <div class="chat-item-info">
                                <div>
                                    <div class="chat-item-name">
                                        <?php 
                                        foreach ($chat->participants as $pid) {
                                            if ($pid !== $user->id) {
                                                $other_user = User::load($pid);
                                                if ($other_user) {
                                                    echo htmlspecialchars($other_user->name);
                                                }
                                                break;
                                            }
                                        }
                                        ?>
                                    </div>
                                    <div class="chat-item-preview">
                                        <?php if (isset($chat_last_messages[$chat->id])): ?>
                                        <?php $last_msg = $chat_last_messages[$chat->id]; ?>
                                        <?php $sender = $last_msg->getSender(); ?>
                                        <?php if ($last_msg->sender_id === $user->id): ?>
                                        我: <?php echo htmlspecialchars(mb_substr($last_msg->content ?? '', 0, 30)); ?>...
                                        <?php else: ?>
                                        <?php echo htmlspecialchars($sender ? $sender->name : '未知'); ?>: <?php echo htmlspecialchars(mb_substr($last_msg->content ?? '', 0, 30)); ?>...
                                        <?php endif; ?>
                                        <?php else: ?>
                                        暂无消息
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="chat-item-time">
                                    <?php if (isset($chat_last_messages[$chat->id])): ?>
                                    <?php echo date('H:i', strtotime($chat_last_messages[$chat->id]->created_at)); ?>
                                    <?php else: ?>
                                    <?php echo date('H:i', strtotime($chat->created_at)); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="chat-item-actions">
                                <button class="btn-icon" title="置顶">📌</button>
                                <button class="btn-icon" title="删除">🗑️</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- 空状态 -->
                <?php if (empty($chats)): ?>
                <div class="empty-chat-list">
                    <p>没有聊天</p>
                    <p class="empty-chat-list-hint">开始与其他用户聊天吧！</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 聊天内容 -->
            <div class="chat-content">
                <?php if ($current_chat): ?>
                <div class="chat-content-header">
                    <div>
                        <?php if ($current_chat->type === 'group'): ?>
                        <span><?php echo htmlspecialchars($current_chat->name); ?></span>
                        <span class="group-count">
                            (<?php echo count($current_chat->participants); ?>人)
                        </span>
                        <?php else: ?>
                        <?php 
                        foreach ($current_chat->participants as $pid) {
                            if ($pid !== $user->id) {
                                $other_user = User::load($pid);
                                if ($other_user) {
                                    echo htmlspecialchars($other_user->name);
                                }
                                break;
                            }
                        }
                        ?>
                        <?php endif; ?>
                    </div>
                    <div class="chat-content-actions">
                        <button class="btn-icon" title="成员">👥</button>
                        <button class="btn-icon" title="设置">⚙️</button>
                    </div>
                </div>
                
                <div class="chat-messages">
                    <?php if (empty($messages)): ?>
                    <div class="empty-messages">
                        没有消息
                        <p>开始对话吧！</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($messages as $message): 
                        $sender = $message->getSender();
                    ?>
                    <div class="message <?php echo $message->sender_id === $user->id ? 'own' : 'other'; ?>">
                        <div class="message-content">
                            <?php echo htmlspecialchars($message->content ?? ''); ?>
                            
                            <!-- 显示附件 -->
                            <?php if (!empty($message->attachments)): ?>
                            <div class="message-attachments">
                                <?php foreach ($message->attachments as $attachment): ?>
                                <div class="attachment-item">
                                    <span class="attachment-icon">📎</span>
                                    <a href="<?php echo DATA_DIR . $attachment['path']; ?>" 
                                       target="_blank" 
                                       class="attachment-link">
                                        <?php echo htmlspecialchars($attachment['name']); ?>
                                    </a>
                                    <span class="attachment-size">
                                        (<?php echo round($attachment['size'] / 1024, 1); ?>KB)
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="message-meta">
                            <?php if ($message->sender_id !== $user->id): ?>
                            <?php echo $sender ? htmlspecialchars($sender->name) : '未知'; ?> · 
                            <?php endif; ?>
                            <?php echo date('H:i', strtotime($message->created_at)); ?>
                            <?php if ($message->sender_id === $user->id || $user->hasPermission('chat_delete')): ?>
                            <a href="?delete_message=<?php echo $message->id; ?>&chat_id=<?php echo $current_chat->id; ?>" 
                                       class="delete-link">删除</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="message-form">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="chat_id" value="<?php echo $current_chat->id; ?>">
                        <textarea name="content" placeholder="输入消息..." required></textarea>
                        <div class="message-form-actions">
                            <div class="message-form-attachments">
                                <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt">
                                <small>支持上传多个文件</small>
                            </div>
                            <button type="submit" name="send_message" class="btn btn-primary">发送</button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="no-chat">
                    <div class="no-chat-icon">💬</div>
                    <h3>选择一个聊天</h3>
                    <p>从左侧列表选择一个聊天开始对话</p>
                    <?php if (!empty($chatable_users)): ?>
                    <p class="no-chat-link">
                        或 <a href="#new-chat">开始新聊天</a>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 可聊天用户列表 -->
        <?php if (count($chatable_users) > 0): ?>
        <div class="new-chat-section" id="new-chat">
            <h2 class="section-title">开始新聊天</h2>
            <div class="chat-list">
                <div class="chat-list-header">可聊天用户</div>
                <?php foreach ($chatable_users as $u): ?>
                <div class="user-item">
                    <div class="user-item-name">
                        <?php echo htmlspecialchars($u->name); ?>
                        <span class="user-item-position">
                            (<?php echo htmlspecialchars($u->position); ?>)
                        </span>
                    </div>
                    <a href="?chat_with=<?php echo $u->id; ?>" class="btn btn-primary btn-sm">
                        开始聊天
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="chat-scripts.js"></script>
</body>
</html>