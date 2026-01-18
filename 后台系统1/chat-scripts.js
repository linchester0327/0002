// 折叠聊天列表功能
function toggleSection(element) {
    const content = element.nextElementSibling;
    const icon = element.querySelector('span:last-child');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.textContent = '▼';
    } else {
        content.style.display = 'none';
        icon.textContent = '▶';
    }
}

// 自动滚动到最新消息
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.querySelector('.chat-messages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // 为聊天部分头部添加点击事件监听器
    const sectionHeaders = document.querySelectorAll('.chat-section-header');
    sectionHeaders.forEach(header => {
        header.addEventListener('click', function() {
            toggleSection(this);
        });
    });
    
    // 为聊天项添加点击事件监听器
    const chatItems = document.querySelectorAll('.chat-item');
    chatItems.forEach(item => {
        item.addEventListener('click', function() {
            const chatId = this.getAttribute('data-chat-id');
            if (chatId) {
                window.location.href = 'chat.php?chat=' + chatId;
            }
        });
    });
    
    // 为删除消息链接添加确认事件
    const deleteLinks = document.querySelectorAll('a[href*="delete_message"]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('确定删除？')) {
                e.preventDefault();
            }
        });
    });
});