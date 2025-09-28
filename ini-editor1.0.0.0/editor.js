// INI配置编辑器核心功能

// 全局变量
let currentINI = {}; // 当前INI数据
let currentSection = null; // 当前选中的配置节
let isModified = false; // 文件是否被修改
let currentFileName = "未命名.ini"; // 当前文件名

// DOM元素引用
const sectionList = document.getElementById('section-list');
const currentSectionTitle = document.getElementById('current-section-title');
const noSelectionMessage = document.getElementById('no-selection-message');
const keyValueEditor = document.getElementById('key-value-editor');
const keyValueList = document.getElementById('key-value-list');
const newKeyInput = document.getElementById('new-key');
const newValueInput = document.getElementById('new-value');
const statusInfo = document.getElementById('status-info');
const fileInfo = document.getElementById('file-info');
const toast = document.getElementById('toast');

// 模态框引用
const codeViewModal = document.getElementById('code-view-modal');
const codeEditor = document.getElementById('code-editor');
const codeViewCloseBtn = document.getElementById('code-view-close-btn');
const codeViewApplyBtn = document.getElementById('code-view-apply-btn');
const codeErrorMsg = document.getElementById('code-error-message');

const addSectionModal = document.getElementById('add-section-modal');
const newSectionNameInput = document.getElementById('new-section-name');
const addSectionCancelBtn = document.getElementById('add-section-cancel-btn');
const addSectionConfirmBtn = document.getElementById('add-section-confirm-btn');
const sectionErrorMsg = document.getElementById('section-error-message');

const renameSectionModal = document.getElementById('rename-section-modal');
const renameSectionNameInput = document.getElementById('rename-section-name');
const renameSectionCancelBtn = document.getElementById('rename-section-cancel-btn');
const renameSectionConfirmBtn = document.getElementById('rename-section-confirm-btn');
const renameErrorMsg = document.getElementById('rename-error-message');

const helpModal = document.getElementById('help-modal');
const helpCloseBtn = document.getElementById('help-close-btn');

// 按钮引用
const newFileBtn = document.getElementById('new-file-btn');
const openFileBtn = document.getElementById('open-file-btn');
const fileInput = document.getElementById('file-input');
const saveFileBtn = document.getElementById('save-file-btn');
const saveAsFileBtn = document.getElementById('save-as-file-btn');
const codeViewBtn = document.getElementById('code-view-btn');
const validateBtn = document.getElementById('validate-btn');
const exportBtn = document.getElementById('export-btn');
const importBtn = document.getElementById('import-btn');
const helpBtn = document.getElementById('help-btn');
const addSectionBtn = document.getElementById('add-section-btn');
const renameSectionBtn = document.getElementById('rename-section-btn');
const deleteSectionBtn = document.getElementById('delete-section-btn');
const duplicateSectionBtn = document.getElementById('duplicate-section-btn');
const addKeyValueBtn = document.getElementById('add-key-value-btn');

// 初始化函数
function init() {
    // 绑定事件监听器
    bindEventListeners();
    
    // 创建默认配置
    createDefaultINI();
    
    // 更新UI
    updateSectionList();
    updateStatusInfo('就绪');
}

// 绑定事件监听器
function bindEventListeners() {
    // 文件操作按钮
    newFileBtn.addEventListener('click', handleNewFile);
    openFileBtn.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', handleFileOpen);
    saveFileBtn.addEventListener('click', handleSaveFile);
    saveAsFileBtn.addEventListener('click', handleSaveAsFile);
    
    // 编辑器功能按钮
    codeViewBtn.addEventListener('click', showCodeViewModal);
    validateBtn.addEventListener('click', validateINI);
    exportBtn.addEventListener('click', exportINI);
    importBtn.addEventListener('click', importINI);
    helpBtn.addEventListener('click', showHelpModal);
    
    // 配置节操作按钮
    addSectionBtn.addEventListener('click', showAddSectionModal);
    renameSectionBtn.addEventListener('click', showRenameSectionModal);
    deleteSectionBtn.addEventListener('click', handleDeleteSection);
    duplicateSectionBtn.addEventListener('click', handleDuplicateSection);
    
    // 键值对操作
    addKeyValueBtn.addEventListener('click', handleAddKeyValue);
    newKeyInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') newValueInput.focus(); });
    newValueInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') handleAddKeyValue(); });
    
    // 模态框按钮
    codeViewCloseBtn.addEventListener('click', hideCodeViewModal);
    codeViewApplyBtn.addEventListener('click', applyCodeChanges);
    
    addSectionCancelBtn.addEventListener('click', hideAddSectionModal);
    addSectionConfirmBtn.addEventListener('click', handleAddSection);
    
    renameSectionCancelBtn.addEventListener('click', hideRenameSectionModal);
    renameSectionConfirmBtn.addEventListener('click', handleRenameSection);
    
    helpCloseBtn.addEventListener('click', hideHelpModal);
    
    // 点击模态框外部关闭模态框
    codeViewModal.addEventListener('click', (e) => { if (e.target === codeViewModal) hideCodeViewModal(); });
    addSectionModal.addEventListener('click', (e) => { if (e.target === addSectionModal) hideAddSectionModal(); });
    renameSectionModal.addEventListener('click', (e) => { if (e.target === renameSectionModal) hideRenameSectionModal(); });
    helpModal.addEventListener('click', (e) => { if (e.target === helpModal) hideHelpModal(); });
    
    // 禁止模态框内部点击事件冒泡
    document.querySelectorAll('.modal-content').forEach(modal => {
        modal.addEventListener('click', (e) => e.stopPropagation());
    });
}

// 创建默认INI配置
function createDefaultINI() {
    currentINI = {
        'general': {
            'name': '测试配置',
            'description': '这是一个INI配置文件',
            'version': '1.0.0'
        },
        'settings': {
            'debug': 'true',
            'log_level': 'info',
            'timeout': '30'
        }
    };
    
    currentFileName = "未命名.ini";
    isModified = false;
    updateFileInfo();
}

// 更新配置节列表
function updateSectionList() {
    sectionList.innerHTML = '';
    
    // 添加全局配置节（如果有）
    if (currentINI['']) {
        addSectionItem('', '全局配置');
    }
    
    // 添加所有其他配置节
    Object.keys(currentINI).forEach(section => {
        if (section !== '') {
            addSectionItem(section);
        }
    });
    
    // 高亮当前选中的配置节
    if (currentSection) {
        const sectionItems = sectionList.querySelectorAll('.section-item');
        sectionItems.forEach(item => {
            if (item.dataset.section === currentSection) {
                item.classList.add('active');
            }
        });
    }
}

// 添加配置节项到列表
function addSectionItem(section, displayName = null) {
    const li = document.createElement('li');
    li.className = 'section-item';
    li.dataset.section = section;
    li.textContent = displayName || section;
    li.addEventListener('click', () => selectSection(section));
    sectionList.appendChild(li);
}

// 选择配置节
function selectSection(section) {
    currentSection = section;
    currentSectionTitle.textContent = section || '全局配置';
    
    // 显示/隐藏编辑界面
    if (section !== null) {
        noSelectionMessage.style.display = 'none';
        keyValueEditor.style.display = 'block';
        renameSectionBtn.disabled = false;
        deleteSectionBtn.disabled = false;
        duplicateSectionBtn.disabled = false;
        
        // 更新键值对列表
        updateKeyValueList();
    } else {
        noSelectionMessage.style.display = 'block';
        keyValueEditor.style.display = 'none';
        renameSectionBtn.disabled = true;
        deleteSectionBtn.disabled = true;
        duplicateSectionBtn.disabled = true;
    }
    
    // 更新配置节列表高亮
    updateSectionList();
    
    // 更新状态栏
    updateStatusInfo(`编辑配置节: ${section || '全局配置'}`);
}

// 更新键值对列表
function updateKeyValueList() {
    keyValueList.innerHTML = '';
    
    if (!currentSection || !currentINI[currentSection]) {
        return;
    }
    
    const keyValues = currentINI[currentSection];
    
    Object.keys(keyValues).forEach((key, index) => {
        const value = keyValues[key];
        addKeyValueItem(key, value, index);
    });
}

// 添加键值对项到列表
function addKeyValueItem(key, value, index) {
    const div = document.createElement('div');
    div.className = 'key-value-item';
    div.dataset.key = key;
    
    const keyInput = document.createElement('input');
    keyInput.type = 'text';
    keyInput.className = 'key-input';
    keyInput.value = key;
    keyInput.addEventListener('change', function() {
        handleKeyChange(key, this.value);
    });
    
    const valueInput = document.createElement('input');
    valueInput.type = 'text';
    valueInput.className = 'value-input';
    valueInput.value = value;
    valueInput.addEventListener('change', function() {
        handleValueChange(key, this.value);
    });
    
    const moveUpBtn = document.createElement('button');
    moveUpBtn.className = 'move-up-btn';
    moveUpBtn.innerHTML = '↑';
    moveUpBtn.disabled = index === 0;
    moveUpBtn.addEventListener('click', function() {
        handleMoveKeyValue(key, -1);
    });
    
    const moveDownBtn = document.createElement('button');
    moveDownBtn.className = 'move-down-btn';
    moveDownBtn.innerHTML = '↓';
    const totalItems = Object.keys(currentINI[currentSection] || {}).length;
    moveDownBtn.disabled = index === totalItems - 1;
    moveDownBtn.addEventListener('click', function() {
        handleMoveKeyValue(key, 1);
    });
    
    const deleteBtn = document.createElement('button');
    deleteBtn.className = 'delete-btn';
    deleteBtn.textContent = '删除';
    deleteBtn.addEventListener('click', function() {
        handleDeleteKeyValue(key);
    });
    
    div.appendChild(keyInput);
    div.appendChild(valueInput);
    div.appendChild(moveUpBtn);
    div.appendChild(moveDownBtn);
    div.appendChild(deleteBtn);
    
    keyValueList.appendChild(div);
}

// 更新状态栏信息
function updateStatusInfo(message) {
    statusInfo.textContent = message;
}

// 更新文件信息
function updateFileInfo() {
    fileInfo.textContent = `${currentFileName}${isModified ? ' *' : ''}`;
}

// 显示提示框
function showToast(message, type = 'info') {
    toast.textContent = message;
    toast.className = `toast ${type}`;
    toast.style.display = 'block';
    
    setTimeout(() => {
        toast.style.display = 'none';
    }, 3000);
}

// 标记文件已修改
function markAsModified() {
    if (!isModified) {
        isModified = true;
        updateFileInfo();
    }
}

// 解析INI字符串
function parseINI(iniString) {
    const result = {};
    let currentSection = '';
    
    // 分割行并处理每一行
    const lines = iniString.split('\n');
    for (let i = 0; i < lines.length; i++) {
        let line = lines[i].trim();
        
        // 跳过空行和注释行
        if (line === '' || line.startsWith(';') || line.startsWith('#')) {
            continue;
        }
        
        // 检查是否为配置节
        const sectionMatch = line.match(/^\[(.*)\]$/);
        if (sectionMatch) {
            currentSection = sectionMatch[1].trim();
            if (!result[currentSection]) {
                result[currentSection] = {};
            }
            continue;
        }
        
        // 解析键值对
        const keyValueMatch = line.match(/^(.*?)=(.*)$/);
        if (keyValueMatch) {
            const key = keyValueMatch[1].trim();
            const value = keyValueMatch[2].trim();
            
            if (!result[currentSection]) {
                result[currentSection] = {};
            }
            result[currentSection][key] = value;
        }
    }
    
    return result;
}

// 生成INI字符串
function generateINI(iniObject) {
    let result = '';
    
    // 处理全局配置节
    if (iniObject['']) {
        Object.keys(iniObject['']).forEach(key => {
            result += `${key}=${iniObject[''][key]}\n`;
        });
        if (Object.keys(iniObject['']).length > 0 && Object.keys(iniObject).length > 1) {
            result += '\n';
        }
    }
    
    // 处理其他配置节
    Object.keys(iniObject).forEach(section => {
        if (section !== '') {
            result += `[${section}]\n`;
            
            Object.keys(iniObject[section]).forEach(key => {
                result += `${key}=${iniObject[section][key]}\n`;
            });
            
            // 添加空行分隔配置节（除了最后一个）
            if (section !== Object.keys(iniObject).pop()) {
                result += '\n';
            }
        }
    });
    
    return result.trim();
}

// 验证INI数据
function validateINI() {
    try {
        // 尝试生成INI字符串，看是否有错误
        const iniString = generateINI(currentINI);
        showToast('INI文件格式验证通过！', 'success');
        updateStatusInfo('验证通过：INI文件格式正确');
        return true;
    } catch (error) {
        showToast(`验证失败：${error.message}`, 'error');
        updateStatusInfo(`验证失败：${error.message}`);
        return false;
    }
}

// 事件处理函数

// 新建文件
function handleNewFile() {
    if (isModified) {
        if (!confirm('文件已修改，是否保存当前更改？')) {
            return;
        }
        handleSaveFile();
    }
    
    createDefaultINI();
    updateSectionList();
    selectSection(null);
    showToast('已创建新文件', 'success');
    updateStatusInfo('已创建新文件');
}

// 打开文件
function handleFileOpen(event) {
    const file = event.target.files[0];
    if (!file) {
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const content = e.target.result;
            const parsedINI = parseINI(content);
            
            currentINI = parsedINI;
            currentFileName = file.name;
            isModified = false;
            
            updateSectionList();
            selectSection(null);
            updateFileInfo();
            
            showToast(`已成功打开文件: ${file.name}`, 'success');
            updateStatusInfo(`已打开文件: ${file.name}`);
        } catch (error) {
            showToast(`打开文件失败: ${error.message}`, 'error');
            updateStatusInfo(`打开文件失败: ${error.message}`);
        }
    };
    reader.readAsText(file);
    
    // 重置文件输入，以便可以再次选择同一文件
    fileInput.value = '';
}

// 保存文件
function handleSaveFile() {
    if (validateINI()) {
        const iniString = generateINI(currentINI);
        const blob = new Blob([iniString], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        
        const a = document.createElement('a');
        a.href = url;
        a.download = currentFileName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        isModified = false;
        updateFileInfo();
        
        showToast(`已成功保存文件: ${currentFileName}`, 'success');
        updateStatusInfo(`已保存文件: ${currentFileName}`);
    }
}

// 另存为文件
function handleSaveAsFile() {
    const newFileName = prompt('请输入文件名:', currentFileName);
    if (newFileName) {
        const oldFileName = currentFileName;
        currentFileName = newFileName.endsWith('.ini') ? newFileName : newFileName + '.ini';
        handleSaveFile();
        currentFileName = oldFileName;
    }
}

// 添加配置节
function handleAddSection() {
    const sectionName = newSectionNameInput.value.trim();
    
    // 验证配置节名称
    if (!sectionName) {
        sectionErrorMsg.textContent = '配置节名称不能为空';
        sectionErrorMsg.style.display = 'block';
        return;
    }
    
    if (currentINI[sectionName]) {
        sectionErrorMsg.textContent = '配置节名称已存在';
        sectionErrorMsg.style.display = 'block';
        return;
    }
    
    if (sectionName.includes('[') || sectionName.includes(']')) {
        sectionErrorMsg.textContent = '配置节名称不能包含方括号';
        sectionErrorMsg.style.display = 'block';
        return;
    }
    
    // 添加新配置节
    currentINI[sectionName] = {};
    markAsModified();
    
    // 更新UI
    updateSectionList();
    selectSection(sectionName);
    hideAddSectionModal();
    
    showToast(`已添加配置节: ${sectionName}`, 'success');
    updateStatusInfo(`已添加配置节: ${sectionName}`);
}

// 重命名配置节
function handleRenameSection() {
    const newSectionName = renameSectionNameInput.value.trim();
    
    // 验证新名称
    if (!newSectionName) {
        renameErrorMsg.textContent = '配置节名称不能为空';
        renameErrorMsg.style.display = 'block';
        return;
    }
    
    if (newSectionName === currentSection) {
        hideRenameSectionModal();
        return;
    }
    
    if (currentINI[newSectionName]) {
        renameErrorMsg.textContent = '配置节名称已存在';
        renameErrorMsg.style.display = 'block';
        return;
    }
    
    if (newSectionName.includes('[') || newSectionName.includes(']')) {
        renameErrorMsg.textContent = '配置节名称不能包含方括号';
        renameErrorMsg.style.display = 'block';
        return;
    }
    
    // 重命名配置节
    currentINI[newSectionName] = currentINI[currentSection];
    delete currentINI[currentSection];
    
    // 更新当前选中的配置节
    currentSection = newSectionName;
    markAsModified();
    
    // 更新UI
    updateSectionList();
    currentSectionTitle.textContent = newSectionName;
    hideRenameSectionModal();
    
    showToast(`已重命名配置节为: ${newSectionName}`, 'success');
    updateStatusInfo(`已重命名配置节为: ${newSectionName}`);
}

// 删除配置节
function handleDeleteSection() {
    if (!currentSection) {
        return;
    }
    
    if (confirm(`确定要删除配置节 "${currentSection}" 吗？所有键值对都将被删除。`)) {
        const sectionToDelete = currentSection;
        delete currentINI[sectionToDelete];
        markAsModified();
        
        // 更新UI
        selectSection(null);
        updateSectionList();
        
        showToast(`已删除配置节: ${sectionToDelete}`, 'success');
        updateStatusInfo(`已删除配置节: ${sectionToDelete}`);
    }
}

// 复制配置节
function handleDuplicateSection() {
    if (!currentSection) {
        return;
    }
    
    let newSectionName = `${currentSection}_copy`;
    let counter = 1;
    
    // 确保新名称不重复
    while (currentINI[newSectionName]) {
        newSectionName = `${currentSection}_copy${counter}`;
        counter++;
    }
    
    // 复制配置节
    currentINI[newSectionName] = { ...currentINI[currentSection] };
    markAsModified();
    
    // 更新UI
    updateSectionList();
    selectSection(newSectionName);
    
    showToast(`已复制配置节为: ${newSectionName}`, 'success');
    updateStatusInfo(`已复制配置节为: ${newSectionName}`);
}

// 添加键值对
function handleAddKeyValue() {
    const key = newKeyInput.value.trim();
    const value = newValueInput.value.trim();
    
    // 验证键名
    if (!key) {
        showToast('键名不能为空', 'error');
        return;
    }
    
    if (!currentSection || !currentINI[currentSection]) {
        showToast('请先选择一个配置节', 'error');
        return;
    }
    
    if (currentINI[currentSection][key]) {
        showToast(`键 "${key}" 已存在`, 'error');
        return;
    }
    
    // 添加键值对
    currentINI[currentSection][key] = value;
    markAsModified();
    
    // 更新UI
    updateKeyValueList();
    
    // 清空输入框
    newKeyInput.value = '';
    newValueInput.value = '';
    newKeyInput.focus();
    
    showToast(`已添加键值对: ${key}=${value}`, 'success');
    updateStatusInfo(`已添加键值对: ${key}=${value}`);
}

// 修改键名
function handleKeyChange(oldKey, newKey) {
    newKey = newKey.trim();
    
    if (!newKey) {
        showToast('键名不能为空', 'error');
        updateKeyValueList(); // 恢复原始值
        return;
    }
    
    if (newKey === oldKey) {
        return;
    }
    
    if (currentINI[currentSection][newKey]) {
        showToast(`键 "${newKey}" 已存在`, 'error');
        updateKeyValueList(); // 恢复原始值
        return;
    }
    
    // 修改键名
    currentINI[currentSection][newKey] = currentINI[currentSection][oldKey];
    delete currentINI[currentSection][oldKey];
    markAsModified();
    
    // 更新UI
    updateKeyValueList();
    
    showToast(`已修改键名: ${oldKey} -> ${newKey}`, 'success');
    updateStatusInfo(`已修改键名: ${oldKey} -> ${newKey}`);
}

// 修改值
function handleValueChange(key, newValue) {
    newValue = newValue.trim();
    
    if (currentINI[currentSection][key] === newValue) {
        return;
    }
    
    // 修改值
    currentINI[currentSection][key] = newValue;
    markAsModified();
    
    showToast(`已修改键 "${key}" 的值`, 'success');
    updateStatusInfo(`已修改键 "${key}" 的值`);
}

// 移动键值对
function handleMoveKeyValue(key, direction) {
    const keys = Object.keys(currentINI[currentSection]);
    const index = keys.indexOf(key);
    const newIndex = index + direction;
    
    // 检查边界
    if (newIndex < 0 || newIndex >= keys.length) {
        return;
    }
    
    // 创建新的键值对顺序
    const newKeys = [...keys];
    [newKeys[index], newKeys[newIndex]] = [newKeys[newIndex], newKeys[index]]; // 交换位置
    
    // 创建新的配置节对象
    const newSection = {};
    newKeys.forEach(k => {
        newSection[k] = currentINI[currentSection][k];
    });
    
    // 更新配置节
    currentINI[currentSection] = newSection;
    markAsModified();
    
    // 更新UI
    updateKeyValueList();
    
    showToast(`已移动键值对: ${key}`, 'success');
}

// 删除键值对
function handleDeleteKeyValue(key) {
    if (confirm(`确定要删除键值对 "${key}" 吗？`)) {
        delete currentINI[currentSection][key];
        markAsModified();
        
        // 更新UI
        updateKeyValueList();
        
        showToast(`已删除键值对: ${key}`, 'success');
        updateStatusInfo(`已删除键值对: ${key}`);
    }
}

// 应用代码视图中的更改
function applyCodeChanges() {
    const code = codeEditor.value;
    
    try {
        const parsedINI = parseINI(code);
        currentINI = parsedINI;
        markAsModified();
        
        // 更新UI
        updateSectionList();
        if (currentSection) {
            selectSection(currentSection);
        }
        
        hideCodeViewModal();
        showToast('已应用代码更改', 'success');
        updateStatusInfo('已应用代码更改');
    } catch (error) {
        codeErrorMsg.textContent = `解析错误: ${error.message}`;
        codeErrorMsg.style.display = 'block';
        updateStatusInfo(`解析错误: ${error.message}`);
    }
}

// 导出INI为其他格式
function exportINI() {
    const format = prompt('请选择导出格式:\n1. JSON\n2. XML\n3. 纯文本', '1');
    
    if (!format || !['1', '2', '3'].includes(format)) {
        return;
    }
    
    let content = '';
    let fileName = currentFileName.replace('.ini', '');
    let mimeType = 'text/plain';
    
    try {
        if (format === '1') {
            // JSON格式
            content = JSON.stringify(currentINI, null, 2);
            fileName += '.json';
            mimeType = 'application/json';
        } else if (format === '2') {
            // XML格式
            content = generateXML(currentINI);
            fileName += '.xml';
            mimeType = 'application/xml';
        } else {
            // 纯文本格式
            content = generateINI(currentINI);
            fileName += '_export.txt';
        }
        
        // 创建并下载文件
        const blob = new Blob([content], { type: mimeType });
        const url = URL.createObjectURL(blob);
        
        const a = document.createElement('a');
        a.href = url;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        showToast(`已导出为: ${fileName}`, 'success');
        updateStatusInfo(`已导出为: ${fileName}`);
    } catch (error) {
        showToast(`导出失败: ${error.message}`, 'error');
        updateStatusInfo(`导出失败: ${error.message}`);
    }
}

// 从其他格式导入
function importINI() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json,.xml,.txt,.ini';
    
    input.onchange = function(event) {
        const file = event.target.files[0];
        if (!file) {
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                let parsedINI = {};
                const content = e.target.result;
                
                // 根据文件扩展名选择解析方法
                const fileExtension = file.name.split('.').pop().toLowerCase();
                
                if (fileExtension === 'ini') {
                    parsedINI = parseINI(content);
                } else if (fileExtension === 'json') {
                    parsedINI = JSON.parse(content);
                } else if (fileExtension === 'xml') {
                    parsedINI = parseXML(content);
                } else {
                    // 尝试作为INI解析
                    parsedINI = parseINI(content);
                }
                
                currentINI = parsedINI;
                currentFileName = file.name;
                isModified = false;
                
                // 更新UI
                updateSectionList();
                selectSection(null);
                updateFileInfo();
                
                showToast(`已成功导入文件: ${file.name}`, 'success');
                updateStatusInfo(`已导入文件: ${file.name}`);
            } catch (error) {
                showToast(`导入文件失败: ${error.message}`, 'error');
                updateStatusInfo(`导入文件失败: ${error.message}`);
            }
        };
        
        reader.readAsText(file);
    };
    
    input.click();
}

// 生成XML字符串（从INI对象）
function generateXML(iniObject) {
    let xml = '<?xml version="1.0" encoding="UTF-8"?>' + '\n';
    xml += '<config>' + '\n';
    
    // 处理全局配置
    if (iniObject['']) {
        Object.keys(iniObject['']).forEach(key => {
            xml += `  <${key}>${escapeXML(iniObject[''][key])}</${key}>` + '\n';
        });
    }
    
    // 处理配置节
    Object.keys(iniObject).forEach(section => {
        if (section !== '') {
            xml += `  <${section}>` + '\n';
            
            Object.keys(iniObject[section]).forEach(key => {
                xml += `    <${key}>${escapeXML(iniObject[section][key])}</${key}>` + '\n';
            });
            
            xml += `  </${section}>` + '\n';
        }
    });
    
    xml += '</config>';
    return xml;
}

// 解析XML字符串（转换为INI对象）
function parseXML(xmlString) {
    const result = {};
    
    try {
        // 创建XML解析器
        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(xmlString, 'text/xml');
        
        // 检查解析错误
        const parserError = xmlDoc.querySelector('parsererror');
        if (parserError) {
            throw new Error('XML解析错误');
        }
        
        // 获取根元素的子元素
        const root = xmlDoc.documentElement;
        const children = Array.from(root.children);
        
        // 处理每个子元素
        children.forEach(child => {
            // 检查是否有子元素
            if (child.children.length > 0) {
                // 作为配置节处理
                const section = child.tagName;
                result[section] = {};
                
                Array.from(child.children).forEach(keyElement => {
                    result[section][keyElement.tagName] = keyElement.textContent;
                });
            } else {
                // 作为全局配置处理
                if (!result['']) {
                    result[''] = {};
                }
                result[''][child.tagName] = child.textContent;
            }
        });
    } catch (error) {
        throw new Error(`XML解析失败: ${error.message}`);
    }
    
    return result;
}

// 转义XML特殊字符
function escapeXML(text) {
    if (typeof text !== 'string') {
        text = String(text);
    }
    
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

// 模态框控制函数

// 显示代码视图模态框
function showCodeViewModal() {
    const iniString = generateINI(currentINI);
    codeEditor.value = iniString;
    codeErrorMsg.style.display = 'none';
    codeViewModal.style.display = 'flex';
    
    // 自动聚焦到代码编辑器
    setTimeout(() => {
        codeEditor.focus();
    }, 100);
}

// 隐藏代码视图模态框
function hideCodeViewModal() {
    codeViewModal.style.display = 'none';
    codeErrorMsg.style.display = 'none';
}

// 显示添加配置节模态框
function showAddSectionModal() {
    newSectionNameInput.value = '';
    sectionErrorMsg.style.display = 'none';
    addSectionModal.style.display = 'flex';
    
    // 自动聚焦到输入框
    setTimeout(() => {
        newSectionNameInput.focus();
    }, 100);
}

// 隐藏添加配置节模态框
function hideAddSectionModal() {
    addSectionModal.style.display = 'none';
    sectionErrorMsg.style.display = 'none';
}

// 显示重命名配置节模态框
function showRenameSectionModal() {
    if (!currentSection) {
        return;
    }
    
    renameSectionNameInput.value = currentSection;
    renameErrorMsg.style.display = 'none';
    renameSectionModal.style.display = 'flex';
    
    // 自动聚焦到输入框
    setTimeout(() => {
        renameSectionNameInput.focus();
        renameSectionNameInput.select();
    }, 100);
}

// 隐藏重命名配置节模态框
function hideRenameSectionModal() {
    renameSectionModal.style.display = 'none';
    renameErrorMsg.style.display = 'none';
}

// 显示帮助模态框
function showHelpModal() {
    helpModal.style.display = 'flex';
}

// 隐藏帮助模态框
function hideHelpModal() {
    helpModal.style.display = 'none';
}

// 初始化应用
init();