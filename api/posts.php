<?php

require_once 'config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id = sanitizeInteger($_GET['id'] ?? null);

try {
    switch ($method) {
        case 'GET':
            $myPosts = isset($_GET['my']) && $_GET['my'] === 'true';
            
            if ($id) {
                $stmt = $db->prepare("
                    SELECT p.*, u.username as author 
                    FROM posts p 
                    LEFT JOIN users u ON p.user_id = u.id 
                    WHERE p.id = ?
                ");
                $stmt->execute([$id]);
                $post = $stmt->fetch();
                
                if (!$post) {
                    sendJSON(['error' => 'Post nie znaleziony'], 404);
                }
                
                if (!isLoggedIn() && $post['status'] !== 'published') {
                    sendJSON(['error' => 'Post nie znaleziony'], 404);
                }
                
                if (!isLoggedIn() || (isLoggedIn() && $post['user_id'] != getCurrentUserId() && $post['status'] !== 'published')) {
                    unset($post['content']);
                    $post['content'] = 'Zaloguj się, aby zobaczyć treść';
                }
                
                sendJSON($post);
            } else {
                if ($myPosts && isLoggedIn()) {
                    $userId = getCurrentUserId();
                    $stmt = $db->prepare("
                        SELECT p.*, u.username as author 
                        FROM posts p 
                        LEFT JOIN users u ON p.user_id = u.id 
                        WHERE p.user_id = ? 
                        ORDER BY p.created_at DESC
                    ");
                    $stmt->execute([$userId]);
                    $posts = $stmt->fetchAll();
                } else {
                    $stmt = $db->query("
                        SELECT p.id, p.title, p.content, p.created_at, p.updated_at, u.username as author 
                        FROM posts p 
                        LEFT JOIN users u ON p.user_id = u.id 
                        WHERE p.status = 'published' 
                        ORDER BY p.created_at DESC
                    ");
                    $posts = $stmt->fetchAll();
                }
                sendJSON($posts);
            }
            break;
            
        case 'POST':
            requireAuth();
            
            $data = getRequestBody();
            
            if (empty($data['title']) || empty($data['content'])) {
                sendJSON(['error' => 'Tytuł i treść są wymagane'], 400);
            }
            
            $title = sanitizeString($data['title']);
            $content = sanitizeString($data['content']);
            $status = isset($data['status']) ? sanitizeString($data['status']) : 'draft';
            
            if (!in_array($status, ['draft', 'published'])) {
                $status = 'draft';
            }
            
            if (strlen($title) > 255) {
                sendJSON(['error' => 'Tytuł nie może przekraczać 255 znaków'], 400);
            }
            
            if (empty($title) || empty($content)) {
                sendJSON(['error' => 'Tytuł i treść nie mogą być puste'], 400);
            }
            
            $userId = getCurrentUserId();
            
            $stmt = $db->prepare("
                INSERT INTO posts (title, content, user_id, status) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$title, $content, $userId, $status]);
            
            $postId = $db->lastInsertId();
            
            $stmt = $db->prepare("
                SELECT p.*, u.username as author 
                FROM posts p 
                LEFT JOIN users u ON p.user_id = u.id 
                WHERE p.id = ?
            ");
            $stmt->execute([$postId]);
            $post = $stmt->fetch();
            
            sendJSON($post, 201);
            break;
            
        case 'PUT':
            requireAuth();
            
            if (!$id) {
                sendJSON(['error' => 'ID posta jest wymagane'], 400);
            }
            
            $data = getRequestBody();
            
            $stmt = $db->prepare("SELECT user_id FROM posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch();
            
            if (!$post) {
                sendJSON(['error' => 'Post nie znaleziony'], 404);
            }
            
            if ($post['user_id'] != getCurrentUserId()) {
                sendJSON(['error' => 'Brak uprawnień'], 403);
            }
            
            $fields = [];
            $values = [];
            
            if (isset($data['title'])) {
                $title = sanitizeString($data['title']);
                if (strlen($title) > 255) {
                    sendJSON(['error' => 'Tytuł nie może przekraczać 255 znaków'], 400);
                }
                if (!empty($title)) {
                    $fields[] = "title = ?";
                    $values[] = $title;
                }
            }
            
            if (isset($data['content'])) {
                $content = sanitizeString($data['content']);
                if (!empty($content)) {
                    $fields[] = "content = ?";
                    $values[] = $content;
                }
            }
            
            if (isset($data['status'])) {
                $status = sanitizeString($data['status']);
                if (in_array($status, ['draft', 'published'])) {
                    $fields[] = "status = ?";
                    $values[] = $status;
                }
            }
            
            if (empty($fields)) {
                sendJSON(['error' => 'Brak pól do aktualizacji'], 400);
            }
            
            $fields[] = "updated_at = CURRENT_TIMESTAMP";
            
            $values[] = $id;
            $sql = "UPDATE posts SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($values);
            
            $stmt = $db->prepare("
                SELECT p.*, u.username as author 
                FROM posts p 
                LEFT JOIN users u ON p.user_id = u.id 
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $post = $stmt->fetch();
            
            sendJSON($post);
            break;
            
        case 'DELETE':
            requireAuth();
            
            if (!$id) {
                sendJSON(['error' => 'ID posta jest wymagane'], 400);
            }
            
            $stmt = $db->prepare("SELECT user_id FROM posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch();
            
            if (!$post) {
                sendJSON(['error' => 'Post nie znaleziony'], 404);
            }
            
            if ($post['user_id'] != getCurrentUserId()) {
                sendJSON(['error' => 'Brak uprawnień'], 403);
            }
            
            $stmt = $db->prepare("DELETE FROM posts WHERE id = ?");
            $stmt->execute([$id]);
            
            sendJSON(['message' => 'Post usunięty pomyślnie']);
            break;
            
        default:
            sendJSON(['error' => 'Method not allowed'], 405);
            break;
    }
    
} catch (PDOException $e) {
    sendJSON(['error' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    sendJSON(['error' => 'Server error: ' . $e->getMessage()], 500);
}

