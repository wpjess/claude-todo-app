<?php
session_start();

$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

if ($_POST && isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    if ($username === 'admin' && $password === 'password123') {
        $_SESSION['logged_in'] = true;
        header('Location: index.php');
        exit;
    } else {
        $loginError = 'Invalid credentials';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (!file_exists('todos.json')) {
    $defaultData = [
        'tabs' => [
            'Home' => ['id' => 'Home', 'name' => 'Home', 'todos' => []],
            'Work' => ['id' => 'Work', 'name' => 'Work', 'todos' => []]
        ]
    ];
    file_put_contents('todos.json', json_encode($defaultData, JSON_PRETTY_PRINT));
}

$todos = json_decode(file_get_contents('todos.json'), true);

function renderTodo($todo, $tabId, $isSubtask = false) {
    $subtaskClass = $isSubtask ? ' subtask' : '';
    $subtaskHtml = '';
    
    if (isset($todo['subtasks']) && !empty($todo['subtasks'])) {
        foreach ($todo['subtasks'] as $subtask) {
            $subtaskHtml .= renderTodo($subtask, $tabId, true);
        }
    }
    
    return '
        <div class="todo-item' . ($todo['completed'] ? ' completed' : '') . $subtaskClass . '" data-id="' . $todo['id'] . '" data-parent="' . (isset($todo['parent_id']) ? $todo['parent_id'] : '') . '">
            <div class="todo-content-wrapper">
                <div class="todo-toolbar" id="toolbar-' . $todo['id'] . '">
                    <button class="toolbar-btn" onclick="formatText(\'bold\', ' . $todo['id'] . ')"><strong>B</strong></button>
                    <button class="toolbar-btn" onclick="formatText(\'italic\', ' . $todo['id'] . ')"><em>I</em></button>
                    <button class="toolbar-btn" onclick="insertLink(' . $todo['id'] . ')">🔗</button>
                    <button class="toolbar-btn" onclick="formatText(\'insertUnorderedList\', ' . $todo['id'] . ')">• List</button>
                </div>
                <div class="todo-content' . ($todo['completed'] ? ' completed' : '') . '" 
                     contenteditable="true" 
                     onfocus="showToolbar(' . $todo['id'] . ')"
                     onblur="hideToolbar(' . $todo['id'] . '); saveTodoContent(\'' . htmlspecialchars($tabId) . '\', ' . $todo['id'] . ', this.innerHTML)">' . $todo['content'] . '</div>
                ' . $subtaskHtml . '
            </div>
            <div class="todo-actions">
                <button class="todo-btn todo-btn-complete' . ($todo['completed'] ? ' completed' : '') . '" 
                        onclick="toggleTodo(\'' . htmlspecialchars($tabId) . '\', ' . $todo['id'] . ')" 
                        title="' . ($todo['completed'] ? 'Mark as incomplete' : 'Mark as complete') . '">
                    ' . ($todo['completed'] ? '↶' : '✓') . '
                </button>
                ' . (!$isSubtask ? '<button class="todo-btn todo-btn-subtask" onclick="addSubtask(\'' . htmlspecialchars($tabId) . '\', ' . $todo['id'] . ')" title="Add subtask">+</button>' : '') . '
                <button class="todo-btn todo-btn-delete" onclick="deleteTodo(\'' . htmlspecialchars($tabId) . '\', ' . $todo['id'] . ')" title="Delete todo">×</button>
            </div>
        </div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claude To-Do App</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            color: #334155;
        }
        
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .login-form {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            width: 320px;
        }
        
        .login-form h2 {
            margin-bottom: 1.5rem;
            text-align: center;
            color: #1e293b;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #475569;
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn {
            background: #3b82f6;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.2s;
        }
        
        .btn:hover {
            background: #2563eb;
        }
        
        .btn-full {
            width: 100%;
        }
        
        .error {
            color: #dc2626;
            margin-top: 0.5rem;
            text-align: center;
            font-size: 0.875rem;
        }
        
        .app-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 1.5rem;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
        }
        
        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
        }
        
        .tabs-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        
        .tabs-header {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            align-items: center;
            padding: 0 0.75rem;
            background: #f8fafc;
        }
        
        .tab {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            position: relative;
            font-size: 0.875rem;
            font-weight: 500;
            color: #64748b;
        }
        
        .tab:hover {
            background: #f1f5f9;
            color: #475569;
        }
        
        .tab.active {
            border-bottom-color: #3b82f6;
            background: white;
            color: #3b82f6;
        }
        
        .tab-close {
            margin-left: 0.5rem;
            color: #94a3b8;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
        }
        
        .tab-close:hover {
            color: #ef4444;
        }
        
        .add-tab-btn {
            margin-left: auto;
            padding: 0.5rem 0.75rem;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 500;
            transition: background 0.2s;
        }
        
        .add-tab-btn:hover {
            background: #059669;
        }
        
        .tab-content {
            padding: 1.5rem;
            min-height: 400px;
        }
        
        .add-todo-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.2s;
        }
        
        .add-todo-btn:hover {
            background: #2563eb;
        }
        
        .todo-item {
            background: white;
            margin-bottom: 0.75rem;
            padding: 1rem;
            border-radius: 8px;
            border-left: 3px solid #e2e8f0;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            transition: all 0.2s;
            border: 1px solid #f1f5f9;
        }
        
        .todo-item:hover {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            border-color: #e2e8f0;
        }
        
        .todo-content-wrapper {
            flex: 1;
        }
        
        .todo-item.completed {
            opacity: 0.6;
            border-left-color: #10b981;
            background: #f0fdf4;
        }
        
        .todo-content {
            min-height: 36px;
            padding: 0.5rem;
            border: 1px solid transparent;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            word-wrap: break-word;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        
        .todo-content.completed {
            text-decoration: line-through;
            color: #6b7280;
        }
        
        .todo-content:focus {
            outline: none;
            border-color: #3b82f6;
            background: #fefefe;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .todo-toolbar {
            display: none;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .todo-toolbar.show {
            display: flex;
            gap: 0.25rem;
            align-items: center;
        }
        
        .toolbar-btn {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.2s;
        }
        
        .toolbar-btn:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }
        
        .toolbar-btn.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .todo-content a {
            color: #3b82f6;
            text-decoration: underline;
        }
        
        .todo-content strong {
            font-weight: 600;
        }
        
        .todo-content em {
            font-style: italic;
        }
        
        .todo-content ul, .todo-content ol {
            margin-left: 1.25rem;
        }
        
        .subtask {
            margin-left: 1.5rem;
            margin-top: 0.5rem;
            border-left: 2px solid #e5e7eb;
            padding-left: 0.75rem;
        }
        
        .subtask .todo-item {
            border-left: 2px solid #9ca3af;
            background: #f9fafb;
        }
        
        .todo-actions {
            display: flex;
            flex-direction: row;
            gap: 0.25rem;
            flex-shrink: 0;
            align-items: flex-start;
        }
        
        .todo-btn {
            padding: 0.25rem;
            font-size: 0.75rem;
            min-width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
            background: #f8fafc;
            color: #64748b;
        }
        
        .todo-btn:hover {
            background: #f1f5f9;
            border-color: #e2e8f0;
        }
        
        .todo-btn-complete {
            color: #10b981;
        }
        
        .todo-btn-complete:hover {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }
        
        .todo-btn-complete.completed {
            background: #fbbf24;
            color: white;
        }
        
        .todo-btn-complete.completed:hover {
            background: #f59e0b;
        }
        
        .todo-btn-subtask {
            color: #6b7280;
        }
        
        .todo-btn-subtask:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }
        
        .todo-btn-delete {
            color: #ef4444;
        }
        
        .todo-btn-delete:hover {
            background: #fef2f2;
            border-color: #fecaca;
        }
        
        .hidden {
            display: none !important;
        }
        
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            width: 320px;
            position: relative;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .modal-content h3 {
            margin-bottom: 1rem;
            color: #1e293b;
            font-weight: 600;
        }
        
        .modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 20px;
            cursor: pointer;
            color: #9ca3af;
            line-height: 1;
        }
        
        .modal-close:hover {
            color: #374151;
        }
        
        .loading {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Empty state styling */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b7280;
        }
        
        .empty-state h3 {
            font-size: 1.125rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #374151;
        }
        
        .empty-state p {
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <?php if (!$isLoggedIn): ?>
        <div class="login-container">
            <form class="login-form" method="POST">
                <h2>Login</h2>
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-full">Login</button>
                <?php if (isset($loginError)): ?>
                    <div class="error"><?php echo htmlspecialchars($loginError); ?></div>
                <?php endif; ?>
                <div style="margin-top: 1rem; font-size: 0.875rem; color: #6b7280; text-align: center;">
                    Demo credentials: admin / password123
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="app-container">
            <div class="header">
                <h1>Todo App</h1>
                <a href="?logout=1" class="btn">Logout</a>
            </div>
            
            <div class="tabs-container">
                <div class="tabs-header">
                    <?php foreach ($todos['tabs'] as $tabId => $tab): ?>
                        <div class="tab<?php echo array_key_first($todos['tabs']) === $tabId ? ' active' : ''; ?>" data-tab="<?php echo htmlspecialchars($tabId); ?>">
                            <?php echo htmlspecialchars($tab['name']); ?>
                            <?php if (count($todos['tabs']) > 1): ?>
                                <span class="tab-close" onclick="deleteTab('<?php echo htmlspecialchars($tabId); ?>')">&times;</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <button class="add-tab-btn" onclick="showAddTabModal()">+ Add Tab</button>
                </div>
                
                <?php foreach ($todos['tabs'] as $tabId => $tab): ?>
                    <div class="tab-content<?php echo array_key_first($todos['tabs']) !== $tabId ? ' hidden' : ''; ?>" id="tab-<?php echo htmlspecialchars($tabId); ?>">
                        <button class="add-todo-btn" onclick="addTodo('<?php echo htmlspecialchars($tabId); ?>')">+ Add Todo</button>
                        <div id="todos-<?php echo htmlspecialchars($tabId); ?>">
                            <?php if (empty($tab['todos'])): ?>
                                <div class="empty-state">
                                    <h3>No todos yet</h3>
                                    <p>Click "Add Todo" to get started</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($tab['todos'] as $todo): ?>
                                    <?php echo renderTodo($todo, $tabId); ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div id="addTabModal" class="modal hidden">
            <div class="modal-content">
                <span class="modal-close" onclick="hideAddTabModal()">&times;</span>
                <h3>Add New Tab</h3>
                <div class="form-group">
                    <label for="tabName">Tab Name:</label>
                    <input type="text" id="tabName" placeholder="Enter tab name">
                </div>
                <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <button class="btn" onclick="hideAddTabModal()" style="background: #6b7280;">Cancel</button>
                    <button class="btn" onclick="createTab()">Create</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        let currentTab = '<?php echo array_key_first($todos['tabs']); ?>';
        let todoCounter = <?php echo !empty($todos['tabs']) ? max(array_map(function($tab) { return !empty($tab['todos']) ? max(array_column($tab['todos'], 'id')) : 0; }, $todos['tabs'])) + 1 : 1; ?>;
        let activeToolbar = null;

        function switchTab(tabId) {
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));
            
            document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
            document.getElementById(`tab-${tabId}`).classList.remove('hidden');
            
            currentTab = tabId;
        }

        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function(e) {
                if (!e.target.classList.contains('tab-close')) {
                    switchTab(this.dataset.tab);
                }
            });
        });

        function showAddTabModal() {
            document.getElementById('addTabModal').classList.remove('hidden');
            document.getElementById('tabName').focus();
        }

        function hideAddTabModal() {
            document.getElementById('addTabModal').classList.add('hidden');
            document.getElementById('tabName').value = '';
        }

        function createTab() {
            const tabName = document.getElementById('tabName').value.trim();
            if (!tabName) {
                alert('Please enter a tab name');
                return;
            }

            fetch('save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'createTab',
                    tabName: tabName
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error creating tab: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating tab');
            });
        }

        function deleteTab(tabId) {
            if (!confirm('Are you sure you want to delete this tab? All todos will be lost.')) {
                return;
            }

            fetch('save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'deleteTab',
                    tabId: tabId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error deleting tab: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting tab');
            });
        }

        function addTodo(tabId, parentId = null) {
            // Hide empty state if it exists
            const emptyState = document.querySelector(`#tab-${tabId} .empty-state`);
            if (emptyState) {
                emptyState.remove();
            }
            
            const todosContainer = document.getElementById(`todos-${tabId}`);
            const todoId = todoCounter++;
            
            const todoHtml = `
                <div class="todo-item${parentId ? ' subtask' : ''}" data-id="${todoId}" data-parent="${parentId || ''}">
                    <div class="todo-content-wrapper">
                        <div class="todo-toolbar" id="toolbar-${todoId}">
                            <button class="toolbar-btn" onclick="formatText('bold', ${todoId})"><strong>B</strong></button>
                            <button class="toolbar-btn" onclick="formatText('italic', ${todoId})"><em>I</em></button>
                            <button class="toolbar-btn" onclick="insertLink(${todoId})">🔗</button>
                            <button class="toolbar-btn" onclick="formatText('insertUnorderedList', ${todoId})">• List</button>
                        </div>
                        <div class="todo-content" contenteditable="true" 
                             onfocus="showToolbar(${todoId})"
                             onblur="hideToolbar(${todoId}); saveTodoContent('${tabId}', ${todoId}, this.innerHTML)" 
                             placeholder="Enter your todo..."></div>
                    </div>
                    <div class="todo-actions">
                        <button class="todo-btn todo-btn-complete" onclick="toggleTodo('${tabId}', ${todoId})" title="Mark as complete">✓</button>
                        ${!parentId ? `<button class="todo-btn todo-btn-subtask" onclick="addSubtask('${tabId}', ${todoId})" title="Add subtask">+</button>` : ''}
                        <button class="todo-btn todo-btn-delete" onclick="deleteTodo('${tabId}', ${todoId})" title="Delete todo">×</button>
                    </div>
                </div>
            `;
            
            if (parentId) {
                const parentTodo = document.querySelector(`[data-id="${parentId}"]`);
                parentTodo.insertAdjacentHTML('beforeend', todoHtml);
            } else {
                todosContainer.insertAdjacentHTML('beforeend', todoHtml);
            }
            
            const newTodo = document.querySelector(`[data-id="${todoId}"]`);
            const contentDiv = newTodo.querySelector('.todo-content');
            contentDiv.focus();

            fetch('save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'addTodo',
                    tabId: tabId,
                    todoId: todoId,
                    content: '',
                    completed: false,
                    parentId: parentId
                })
            })
            .then(response => response.json())
            .catch(error => console.error('Error:', error));
        }
        
        function addSubtask(tabId, parentId) {
            addTodo(tabId, parentId);
        }
        
        function showToolbar(todoId) {
            if (activeToolbar) {
                document.getElementById(`toolbar-${activeToolbar}`).classList.remove('show');
            }
            activeToolbar = todoId;
            document.getElementById(`toolbar-${todoId}`).classList.add('show');
        }
        
        function hideToolbar(todoId) {
            setTimeout(() => {
                const toolbar = document.getElementById(`toolbar-${todoId}`);
                if (toolbar && !toolbar.matches(':hover')) {
                    toolbar.classList.remove('show');
                    if (activeToolbar === todoId) {
                        activeToolbar = null;
                    }
                }
            }, 100);
        }
        
        function formatText(command, todoId) {
            const contentDiv = document.querySelector(`[data-id="${todoId}"] .todo-content`);
            contentDiv.focus();
            document.execCommand(command, false, null);
            saveTodoContent(currentTab, todoId, contentDiv.innerHTML);
        }
        
        function insertLink(todoId) {
            const url = prompt('Enter URL:');
            if (url) {
                const contentDiv = document.querySelector(`[data-id="${todoId}"] .todo-content`);
                contentDiv.focus();
                const selection = window.getSelection();
                const range = selection.getRangeAt(0);
                const selectedText = range.toString() || url;
                
                const link = document.createElement('a');
                link.href = url.startsWith('http') ? url : 'https://' + url;
                link.textContent = selectedText;
                link.target = '_blank';
                
                range.deleteContents();
                range.insertNode(link);
                
                saveTodoContent(currentTab, todoId, contentDiv.innerHTML);
            }
        }

        function saveTodoContent(tabId, todoId, content) {
            fetch('save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'updateTodo',
                    tabId: tabId,
                    todoId: todoId,
                    content: content
                })
            })
            .then(response => response.json())
            .catch(error => console.error('Error:', error));
        }

        function toggleTodo(tabId, todoId) {
            const todoItem = document.querySelector(`[data-id="${todoId}"]`);
            const isCompleted = todoItem.classList.contains('completed');
            
            fetch('save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'toggleTodo',
                    tabId: tabId,
                    todoId: todoId,
                    completed: !isCompleted
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const content = todoItem.querySelector('.todo-content');
                    const button = todoItem.querySelector('.todo-btn-complete');
                    
                    if (!isCompleted) {
                        todoItem.classList.add('completed');
                        content.classList.add('completed');
                        button.classList.add('completed');
                        button.textContent = '↶';
                    } else {
                        todoItem.classList.remove('completed');
                        content.classList.remove('completed');
                        button.classList.remove('completed');
                        button.textContent = '✓';
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function deleteTodo(tabId, todoId) {
            if (!confirm('Are you sure you want to delete this todo?')) {
                return;
            }

            fetch('save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'deleteTodo',
                    tabId: tabId,
                    todoId: todoId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelector(`[data-id="${todoId}"]`).remove();
                    
                    // Show empty state if no todos remain
                    const todosContainer = document.getElementById(`todos-${tabId}`);
                    if (todosContainer.children.length === 0) {
                        todosContainer.innerHTML = `
                            <div class="empty-state">
                                <h3>No todos yet</h3>
                                <p>Click "Add Todo" to get started</p>
                            </div>
                        `;
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('tabName').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    createTab();
                }
            });

            document.getElementById('addTabModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    hideAddTabModal();
                }
            });
        });
    </script>
</body>
</html>