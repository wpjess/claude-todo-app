<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
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

if (!$todos) {
    echo json_encode(['success' => false, 'message' => 'Failed to load data']);
    exit;
}

function saveTodos($todos) {
    $result = file_put_contents('todos.json', json_encode($todos, JSON_PRETTY_PRINT));
    if ($result === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to save data']);
        exit;
    }
}

function findTodoById($todos, $tabId, $todoId) {
    foreach ($todos['tabs'][$tabId]['todos'] as &$todo) {
        if ($todo['id'] == $todoId) {
            return $todo;
        }
        if (isset($todo['subtasks'])) {
            foreach ($todo['subtasks'] as &$subtask) {
                if ($subtask['id'] == $todoId) {
                    return $subtask;
                }
            }
        }
    }
    return null;
}

function updateTodoById(&$todos, $tabId, $todoId, $updates) {
    foreach ($todos['tabs'][$tabId]['todos'] as &$todo) {
        if ($todo['id'] == $todoId) {
            foreach ($updates as $key => $value) {
                $todo[$key] = $value;
            }
            return true;
        }
        if (isset($todo['subtasks'])) {
            foreach ($todo['subtasks'] as &$subtask) {
                if ($subtask['id'] == $todoId) {
                    foreach ($updates as $key => $value) {
                        $subtask[$key] = $value;
                    }
                    return true;
                }
            }
        }
    }
    return false;
}

function deleteTodoById(&$todos, $tabId, $todoId) {
    foreach ($todos['tabs'][$tabId]['todos'] as $index => &$todo) {
        if ($todo['id'] == $todoId) {
            unset($todos['tabs'][$tabId]['todos'][$index]);
            $todos['tabs'][$tabId]['todos'] = array_values($todos['tabs'][$tabId]['todos']);
            return true;
        }
        if (isset($todo['subtasks'])) {
            foreach ($todo['subtasks'] as $subIndex => $subtask) {
                if ($subtask['id'] == $todoId) {
                    unset($todo['subtasks'][$subIndex]);
                    $todo['subtasks'] = array_values($todo['subtasks']);
                    return true;
                }
            }
        }
    }
    return false;
}

switch ($input['action']) {
    case 'createTab':
        if (!isset($input['tabName']) || trim($input['tabName']) === '') {
            echo json_encode(['success' => false, 'message' => 'Tab name is required']);
            exit;
        }
        
        $tabName = trim($input['tabName']);
        $tabId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $tabName);
        
        if (isset($todos['tabs'][$tabId])) {
            echo json_encode(['success' => false, 'message' => 'Tab already exists']);
            exit;
        }
        
        $todos['tabs'][$tabId] = [
            'id' => $tabId,
            'name' => $tabName,
            'todos' => []
        ];
        
        saveTodos($todos);
        echo json_encode(['success' => true, 'message' => 'Tab created successfully']);
        break;
        
    case 'deleteTab':
        if (!isset($input['tabId'])) {
            echo json_encode(['success' => false, 'message' => 'Tab ID is required']);
            exit;
        }
        
        $tabId = $input['tabId'];
        
        if (count($todos['tabs']) <= 1) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete the last tab']);
            exit;
        }
        
        if (!isset($todos['tabs'][$tabId])) {
            echo json_encode(['success' => false, 'message' => 'Tab not found']);
            exit;
        }
        
        unset($todos['tabs'][$tabId]);
        
        saveTodos($todos);
        echo json_encode(['success' => true, 'message' => 'Tab deleted successfully']);
        break;
        
    case 'addTodo':
        if (!isset($input['tabId']) || !isset($input['todoId'])) {
            echo json_encode(['success' => false, 'message' => 'Tab ID and Todo ID are required']);
            exit;
        }
        
        $tabId = $input['tabId'];
        $todoId = intval($input['todoId']);
        $content = isset($input['content']) ? $input['content'] : '';
        $completed = isset($input['completed']) ? $input['completed'] : false;
        $parentId = isset($input['parentId']) ? intval($input['parentId']) : null;
        
        if (!isset($todos['tabs'][$tabId])) {
            echo json_encode(['success' => false, 'message' => 'Tab not found']);
            exit;
        }
        
        $newTodo = [
            'id' => $todoId,
            'content' => $content,
            'completed' => $completed,
            'created_at' => date('Y-m-d H:i:s'),
            'subtasks' => []
        ];
        
        if ($parentId) {
            $newTodo['parent_id'] = $parentId;
            // Find parent todo and add subtask
            $parentFound = false;
            foreach ($todos['tabs'][$tabId]['todos'] as &$todo) {
                if ($todo['id'] == $parentId) {
                    if (!isset($todo['subtasks'])) {
                        $todo['subtasks'] = [];
                    }
                    $todo['subtasks'][] = $newTodo;
                    $parentFound = true;
                    break;
                }
            }
            if (!$parentFound) {
                echo json_encode(['success' => false, 'message' => 'Parent todo not found']);
                exit;
            }
        } else {
            $todos['tabs'][$tabId]['todos'][] = $newTodo;
        }
        
        saveTodos($todos);
        echo json_encode(['success' => true, 'message' => 'Todo added successfully']);
        break;
        
    case 'updateTodo':
        if (!isset($input['tabId']) || !isset($input['todoId']) || !isset($input['content'])) {
            echo json_encode(['success' => false, 'message' => 'Tab ID, Todo ID, and content are required']);
            exit;
        }
        
        $tabId = $input['tabId'];
        $todoId = intval($input['todoId']);
        $content = $input['content'];
        
        if (!isset($todos['tabs'][$tabId])) {
            echo json_encode(['success' => false, 'message' => 'Tab not found']);
            exit;
        }
        
        $updates = [
            'content' => $content,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if (!updateTodoById($todos, $tabId, $todoId, $updates)) {
            echo json_encode(['success' => false, 'message' => 'Todo not found']);
            exit;
        }
        
        saveTodos($todos);
        echo json_encode(['success' => true, 'message' => 'Todo updated successfully']);
        break;
        
    case 'toggleTodo':
        if (!isset($input['tabId']) || !isset($input['todoId']) || !isset($input['completed'])) {
            echo json_encode(['success' => false, 'message' => 'Tab ID, Todo ID, and completed status are required']);
            exit;
        }
        
        $tabId = $input['tabId'];
        $todoId = intval($input['todoId']);
        $completed = $input['completed'];
        
        if (!isset($todos['tabs'][$tabId])) {
            echo json_encode(['success' => false, 'message' => 'Tab not found']);
            exit;
        }
        
        $updates = [
            'completed' => $completed,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if (!updateTodoById($todos, $tabId, $todoId, $updates)) {
            echo json_encode(['success' => false, 'message' => 'Todo not found']);
            exit;
        }
        
        saveTodos($todos);
        echo json_encode(['success' => true, 'message' => 'Todo status updated successfully']);
        break;
        
    case 'deleteTodo':
        if (!isset($input['tabId']) || !isset($input['todoId'])) {
            echo json_encode(['success' => false, 'message' => 'Tab ID and Todo ID are required']);
            exit;
        }
        
        $tabId = $input['tabId'];
        $todoId = intval($input['todoId']);
        
        if (!isset($todos['tabs'][$tabId])) {
            echo json_encode(['success' => false, 'message' => 'Tab not found']);
            exit;
        }
        
        if (!deleteTodoById($todos, $tabId, $todoId)) {
            echo json_encode(['success' => false, 'message' => 'Todo not found']);
            exit;
        }
        
        saveTodos($todos);
        echo json_encode(['success' => true, 'message' => 'Todo deleted successfully']);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>