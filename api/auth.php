<?php

require_once 'config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            $data = getRequestBody();
            $action = $data['action'] ?? '';
            
            if ($action === 'register') {
                if (empty($data['username']) || empty($data['password'])) {
                    sendJSON(['error' => 'Wszystkie pola są wymagane'], 400);
                }
                
                $username = validateUsername($data['username']);
                if (!$username) {
                    sendJSON(['error' => 'Nazwa użytkownika musi mieć 3-50 znaków i zawierać tylko litery, cyfry i podkreślenia'], 400);
                }
                
                $password = $data['password'];
                if (!validatePassword($password)) {
                    sendJSON(['error' => 'Hasło musi mieć co najmniej 6 znaków'], 400);
                }
                
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    sendJSON(['error' => 'Użytkownik o tej nazwie już istnieje'], 400);
                }
                
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmt->execute([$username, $hashedPassword]);
                
                $_SESSION['user_id'] = $db->lastInsertId();
                $_SESSION['username'] = $username;
                
                sendJSON([
                    'message' => 'Rejestracja zakończona sukcesem',
                    'user' => [
                        'id' => $_SESSION['user_id'],
                        'username' => $username
                    ]
                ], 201);
            }
            
            if ($action === 'login') {
                if (empty($data['username']) || empty($data['password'])) {
                    sendJSON(['error' => 'Nazwa użytkownika i hasło są wymagane'], 400);
                }
                
                $username = sanitizeString($data['username']);
                $password = $data['password'];
                
                $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if (!$user || !password_verify($password, $user['password'])) {
                    sendJSON(['error' => 'Nieprawidłowa nazwa użytkownika lub hasło'], 401);
                }
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                sendJSON([
                    'message' => 'Logowanie zakończone sukcesem',
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username']
                    ]
                ]);
            }
            
            sendJSON(['error' => 'Nieprawidłowa akcja'], 400);
            break;
            
        case 'GET':
            if (isLoggedIn()) {
                sendJSON([
                    'loggedIn' => true,
                    'user' => [
                        'id' => $_SESSION['user_id'],
                        'username' => $_SESSION['username']
                    ]
                ]);
            } else {
                sendJSON(['loggedIn' => false]);
            }
            break;
            
        case 'DELETE':
            session_destroy();
            sendJSON(['message' => 'Wylogowano pomyślnie']);
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

