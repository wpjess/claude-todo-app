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
                <button class="btn btn-sm btn-' . ($todo['completed'] ? 'warning' : 'success') . '" 
                        onclick="toggleTodo(\'' . htmlspecialchars($tabId) . '\', ' . $todo['id'] . ')" 
                        title="' . ($todo['completed'] ? 'Mark as incomplete' : 'Mark as complete') . '">
                    ' . ($todo['completed'] ? '↶' : '✓') . '
                </button>
                ' . (!$isSubtask ? '<button class="add-subtask-btn" onclick="addSubtask(\'' . htmlspecialchars($tabId) . '\', ' . $todo['id'] . ')" title="Add subtask">+</button>' : '') . '
                <button class="btn btn-sm btn-danger" onclick="deleteTodo(\'' . htmlspecialchars($tabId) . '\', ' . $todo['id'] . ')" title="Delete todo">×</button>
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
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
        }
        
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .login-form {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 300px;
        }
        
        .login-form h2 {
            margin-bottom: 1rem;
            text-align: center;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .btn {
            background: #007bff;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .btn-full {
            width: 100%;
        }
        
        .error {
            color: #dc3545;
            margin-top: 0.5rem;
            text-align: center;
        }
        
        .app-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .tabs-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .tabs-header {
            display: flex;
            border-bottom: 1px solid #eee;
            align-items: center;
            padding: 0 1rem;
        }
        
        .tab {
            padding: 1rem 1.5rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            position: relative;
        }
        
        .tab:hover {
            background: #f8f9fa;
        }
        
        .tab.active {
            border-bottom-color: #007bff;
            background: #f8f9fa;
        }
        
        .tab-close {
            margin-left: 0.5rem;
            color: #999;
            cursor: pointer;
            font-weight: bold;
        }
        
        .tab-close:hover {
            color: #dc3545;
        }
        
        .add-tab-btn {
            margin-left: auto;
            padding: 0.5rem 1rem;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .add-tab-btn:hover {
            background: #1e7e34;
        }
        
        .tab-content {
            padding: 2rem;
            min-height: 400px;
        }
        
        .add-todo-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        
        .add-todo-btn:hover {
            background: #0056b3;
        }
        
        .todo-item {
            background: #f8f9fa;
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: 6px;
            border-left: 4px solid #007bff;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .todo-content-wrapper {
            flex: 1;
        }
        
        .todo-item.completed {
            opacity: 0.6;
            border-left-color: #28a745;
        }
        
        .todo-content {
            min-height: 40px;
            padding: 0.5rem;
            border: 1px solid transparent;
            border-radius: 4px;
            margin-bottom: 0.5rem;
            word-wrap: break-word;
        }
        
        .todo-content.completed {
            text-decoration: line-through;
        }
        
        .todo-content:focus {
            outline: none;
            border-color: #007bff;
            background: white;
        }
        
        .todo-toolbar {
            display: none;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .todo-toolbar.show {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .toolbar-btn {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.875rem;
            transition: background 0.2s;
        }
        
        .toolbar-btn:hover {
            background: #e9ecef;
        }
        
        .toolbar-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .todo-content {
            min-height: 60px;
        }
        
        .todo-content a {
            color: #007bff;
            text-decoration: underline;
        }
        
        .todo-content strong {
            font-weight: bold;
        }
        
        .todo-content em {
            font-style: italic;
        }
        
        .todo-content ul, .todo-content ol {
            margin-left: 1.5rem;
        }
        
        .subtask {
            margin-left: 2rem;
            margin-top: 0.5rem;
            border-left: 2px solid #e9ecef;
            padding-left: 1rem;
        }
        
        .subtask .todo-item {
            border-left: 2px solid #6c757d;
        }
        
        .add-subtask-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.875rem;
            min-width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .add-subtask-btn:hover {
            background: #495057;
        }
        
        .todo-actions {
            display: flex;
            flex-direction: row;
            gap: 0.25rem;
            flex-shrink: 0;
            align-items: flex-start;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            min-width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #1e7e34;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
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
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            width: 300px;
            position: relative;
        }
        
        .modal-content h3 {
            margin-bottom: 1rem;
        }
        
        .modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            line-height: 1;
        }
        
        .modal-close:hover {
            color: #333;
        }
        
        .loading {
            opacity: 0.5;
            pointer-events: none;
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
                <div style="margin-top: 1rem; font-size: 0.9rem; color: #666; text-align: center;">
                    Demo credentials: admin / password123
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="app-container">
            <div class="header">
                <h1>Claude To-Do App</h1>
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
                            <?php foreach ($tab['todos'] as $todo): ?>
                                <?php echo renderTodo($todo, $tabId); ?>
                            <?php endforeach; ?>
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
                    <button class="btn" onclick="hideAddTabModal()">Cancel</button>
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
                        <button class="btn btn-sm btn-success" onclick="toggleTodo('${tabId}', ${todoId})" title="Mark as complete">✓</button>
                        ${!parentId ? `<button class="add-subtask-btn" onclick="addSubtask('${tabId}', ${todoId})" title="Add subtask">+</button>` : ''}
                        <button class="btn btn-sm btn-danger" onclick="deleteTodo('${tabId}', ${todoId})" title="Delete todo">×</button>
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
                    const button = todoItem.querySelector('.btn-success, .btn-warning');
                    
                    if (!isCompleted) {
                        todoItem.classList.add('completed');
                        content.classList.add('completed');
                        button.classList.remove('btn-success');
                        button.classList.add('btn-warning');
                        button.textContent = 'Undo';
                    } else {
                        todoItem.classList.remove('completed');
                        content.classList.remove('completed');
                        button.classList.remove('btn-warning');
                        button.classList.add('btn-success');
                        button.textContent = 'Complete';
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