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
$postId = sanitizeInteger($_GET['post_id'] ?? null);

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                $stmt = $db->prepare("
                    SELECT c.*, u.username as author 
                    FROM comments c 
                    LEFT JOIN users u ON c.user_id = u.id 
                    WHERE c.id = ?
                ");
                $stmt->execute([$id]);
                $comment = $stmt->fetch();
                
                if (!$comment) {
                    sendJSON(['error' => 'Komentarz nie znaleziony'], 404);
                }
                
                sendJSON($comment);
            } else if ($postId) {
                $stmt = $db->prepare("
                    SELECT c.*, u.username as author 
                    FROM comments c 
                    LEFT JOIN users u ON c.user_id = u.id 
                    WHERE c.post_id = ? 
                    ORDER BY c.created_at DESC
                ");
                $stmt->execute([$postId]);
                $comments = $stmt->fetchAll();
                sendJSON($comments);
            } else {
                sendJSON(['error' => 'ID posta lub komentarza jest wymagane'], 400);
            }
            break;
            
        case 'POST':
            requireAuth();
            
            if (!$postId) {
                sendJSON(['error' => 'ID posta jest wymagane'], 400);
            }
            
            $stmt = $db->prepare("SELECT id, status FROM posts WHERE id = ?");
            $stmt->execute([$postId]);
            $post = $stmt->fetch();
            
            if (!$post) {
                sendJSON(['error' => 'Post nie znaleziony'], 404);
            }
            
            if ($post['status'] !== 'published') {
                sendJSON(['error' => 'Nie można komentować nieopublikowanych postów'], 403);
            }
            
            $data = getRequestBody();
            
            if (empty($data['content'])) {
                sendJSON(['error' => 'Treść komentarza jest wymagana'], 400);
            }
            
            $content = sanitizeString($data['content']);
            
            if (empty($content)) {
                sendJSON(['error' => 'Treść komentarza nie może być pusta'], 400);
            }
            
            $userId = getCurrentUserId();
            
            $stmt = $db->prepare("
                INSERT INTO comments (content, user_id, post_id) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$content, $userId, $postId]);
            
            $commentId = $db->lastInsertId();
            
            $stmt = $db->prepare("
                SELECT c.*, u.username as author 
                FROM comments c 
                LEFT JOIN users u ON c.user_id = u.id 
                WHERE c.id = ?
            ");
            $stmt->execute([$commentId]);
            $comment = $stmt->fetch();
            
            sendJSON($comment, 201);
            break;
            
        case 'PUT':
            requireAuth();
            
            if (!$id) {
                sendJSON(['error' => 'ID komentarza jest wymagane'], 400);
            }
            
            $stmt = $db->prepare("SELECT user_id FROM comments WHERE id = ?");
            $stmt->execute([$id]);
            $comment = $stmt->fetch();
            
            if (!$comment) {
                sendJSON(['error' => 'Komentarz nie znaleziony'], 404);
            }
            
            if ($comment['user_id'] != getCurrentUserId()) {
                sendJSON(['error' => 'Brak uprawnień'], 403);
            }
            
            $data = getRequestBody();
            
            if (empty($data['content'])) {
                sendJSON(['error' => 'Treść komentarza jest wymagana'], 400);
            }
            
            $content = sanitizeString($data['content']);
            
            if (empty($content)) {
                sendJSON(['error' => 'Treść komentarza nie może być pusta'], 400);
            }
            
            $stmt = $db->prepare("UPDATE comments SET content = ? WHERE id = ?");
            $stmt->execute([$content, $id]);
            
            $stmt = $db->prepare("
                SELECT c.*, u.username as author 
                FROM comments c 
                LEFT JOIN users u ON c.user_id = u.id 
                WHERE c.id = ?
            ");
            $stmt->execute([$id]);
            $updatedComment = $stmt->fetch();
            
            sendJSON($updatedComment);
            break;
            
        case 'DELETE':
            requireAuth();
            
            if (!$id) {
                sendJSON(['error' => 'ID komentarza jest wymagane'], 400);
            }
            
            $stmt = $db->prepare("SELECT user_id FROM comments WHERE id = ?");
            $stmt->execute([$id]);
            $comment = $stmt->fetch();
            
            if (!$comment) {
                sendJSON(['error' => 'Komentarz nie znaleziony'], 404);
            }
            
            if ($comment['user_id'] != getCurrentUserId()) {
                sendJSON(['error' => 'Brak uprawnień'], 403);
            }
            
            $stmt = $db->prepare("DELETE FROM comments WHERE id = ?");
            $stmt->execute([$id]);
            
            sendJSON(['message' => 'Komentarz usunięty pomyślnie']);
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
