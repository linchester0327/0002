function toggleTree(element) {
    const children = element.parentElement.nextElementSibling;
    if (children && children.classList.contains('tree-children')) {
        if (children.style.display === 'none') {
            children.style.display = 'block';
            element.textContent = '▼';
        } else {
            children.style.display = 'none';
            element.textContent = '▶';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // 处理创建部门按钮点击
    const createDeptBtn = document.getElementById('createDeptBtn');
    if (createDeptBtn) {
        createDeptBtn.addEventListener('click', function() {
            document.getElementById('deptForm').style.display = 'block';
        });
    }

    // 处理取消按钮点击
    const cancelDeptBtn = document.getElementById('cancelDeptBtn');
    if (cancelDeptBtn) {
        cancelDeptBtn.addEventListener('click', function() {
            document.getElementById('deptForm').style.display = 'none';
        });
    }

    // 为所有树节点切换按钮添加点击事件
    const treeToggles = document.querySelectorAll('.tree-toggle');
    treeToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            toggleTree(this);
        });
    });

    // 为所有删除按钮添加点击事件
    const deleteBtns = document.querySelectorAll('.delete-btn');
    deleteBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            if (!confirm('确定删除？')) {
                e.preventDefault();
            }
        });
    });
});