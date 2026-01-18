// 菜单折叠功能
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.getElementById('menu-toggle');
    const navSidebar = document.querySelector('.nav-sidebar');
    const mainContent = document.querySelector('.main-content');
    
    menuToggle.addEventListener('click', function() {
        navSidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
    });
});