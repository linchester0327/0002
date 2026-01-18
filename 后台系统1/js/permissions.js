// 权限说明
const permissionDescriptions = {
    'user_create': '创建新用户的权限，影响用户管理功能',
    'user_edit': '编辑现有用户信息的权限，影响用户管理功能',
    'user_delete': '删除用户的权限，影响用户管理功能',
    'user_view': '查看用户列表和详情的权限，影响用户管理功能',
    'todo_manage': '管理所有待办事项的权限，影响待办事项功能',
    'todo_check': '标记待办事项完成状态的权限，影响待办事项功能',
    'chat_send': '发送消息的权限，影响聊天功能',
    'chat_delete': '删除消息的权限，影响聊天功能',
    'chat_group': '创建和管理群组聊天的权限，影响聊天功能',
    'application_create': '创建申请的权限，影响申请管理功能',
    'application_delete': '删除申请的权限，影响申请管理功能',
    'application_approve': '批准申请的权限，影响申请管理功能',
    'application_manage': '管理所有申请的权限，影响申请管理功能',
    'permission_assign': '分配权限给其他用户的权限，影响权限管理功能',
    'system_config': '系统配置的权限，影响数据管理等系统功能',
    'notification_view': '查看通知的权限，影响通知功能',
    'notification_delete': '删除通知的权限，影响通知功能'
};

function selectUser(userId, element) {
    // 移除其他选中状态
    document.querySelectorAll('.user-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // 添加选中状态
    element.classList.add('selected');
    element.querySelector('input[type="radio"]').checked = true;
    
    // 加载用户原有权限
    loadUserPermissions(element);
}

function loadUserPermissions(element) {
    const permissionsJson = element.dataset.permissions;
    const permissions = permissionsJson ? JSON.parse(permissionsJson) : [];
    
    // 重置所有权限复选框
    document.querySelectorAll('input[name="permissions[]"]').forEach(checkbox => {
        checkbox.checked = permissions.includes(checkbox.value);
    });
}

// 显示权限说明
function showPermissionDescription() {
    document.querySelectorAll('input[name="permissions[]"]').forEach(checkbox => {
        const permissionKey = checkbox.value;
        const descriptionElement = document.getElementById('desc_' + permissionKey);
        
        if (descriptionElement) {
            descriptionElement.textContent = permissionDescriptions[permissionKey] || '';
            descriptionElement.style.fontSize = '0.8em';
            descriptionElement.style.color = '#666';
            descriptionElement.style.marginLeft = '10px';
        }
    });
}

// 保存时的确认机制
function confirmSave() {
    const selectedUser = document.querySelector('input[name="user_id"]:checked');
    if (!selectedUser) {
        alert('请先选择用户');
        return false;
    }
    
    return confirm('确定要保存权限设置吗？此操作将覆盖用户当前的权限设置。');
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    showPermissionDescription();
    
    // 为保存按钮添加确认事件
    const saveButton = document.querySelector('button[type="submit"]');
    if (saveButton) {
        saveButton.addEventListener('click', function(e) {
            if (!confirmSave()) {
                e.preventDefault();
            }
        });
    }
});