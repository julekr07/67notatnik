<?php
// api.php — Proste REST API z JWT i MySQL
// Wymaga: composer require firebase/php-jwt

header("Content-Type: application/json; charset=UTF-8");

// ——— Konfiguracja bazy i JWT ———
$dsn = "mysql:host=localhost;dbname=school_api;charset=utf8mb4";
$dbUser = "root";     // zmień na swoje dane
$dbPass = "";         // zmień na swoje dane
$jwt_secret = "super_secret_key_change_me"; // zmień i trzymaj w bezpiecznym miejscu

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require __DIR__ . '/vendor/autoload.php';

// ——— Połączenie DB ———
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

// ——— Pomocnicze ———
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function getJsonInput() {
    $raw = file_get_contents("php://input");
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function parsePath() {
    // Obsługa ścieżek w stylu: /messages, /messages?last_id=...
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $parts = explode("/", trim($uri, "/"));
    return $parts;
}

// Wymaga tokena dla wszystkich endpointów poza /auth
function requireAuth($jwt_secret) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
        jsonResponse(["error" => "Missing token"], 401);
    }
    $token = trim(substr($authHeader, 7));
    try {
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
        // Spodziewane pola: userId (int), isTeacher (bool), exp (int)
        if (!isset($decoded->userId) || !isset($decoded->isTeacher)) {
            jsonResponse(["error" => "Invalid token payload"], 401);
        }
        return $decoded;
    } catch (Exception $e) {
        jsonResponse(["error" => "Invalid token"], 401);
    }
}

// ——— Routing ———
$method = $_SERVER['REQUEST_METHOD'];
$parts = parsePath();
$endpoint = $parts[0] ?? "";

// ——— AUTH (jedyny endpoint bez tokena) ———
if ($endpoint === "auth" && $method === "GET") {
    $login = $_GET['login'] ?? null;
    $password = $_GET['password'] ?? null;

    if (!$login || !$password) {
        jsonResponse(["error" => "Missing login or password"], 400);
    }

    // Użytkownik: users(login, password[hash], isTeacher)
    $stmt = $pdo->prepare("SELECT id, login, password, isTeacher FROM users WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        jsonResponse(["error" => "Invalid credentials"], 401);
    }

    $payload = [
        "userId"    => (int)$user['id'],
        "isTeacher" => (bool)$user['isTeacher'],
        "exp"       => time() + 3600 // 1h ważności
    ];

    $jwt = JWT::encode($payload, $jwt_secret, 'HS256');
    jsonResponse(["token" => $jwt]);
}

// ——— Wszystkie pozostałe endpointy wymagają tokena ———
$decoded = requireAuth($jwt_secret);
$authUserId = (int)$decoded->userId;
$authIsTeacher = (bool)$decoded->isTeacher;

// ——— MESSAGES ———
// GET /messages?last_id=X — zwraca nowe wiadomości
// POST /messages — dodaje wiadomość (od zalogowanego użytkownika)
if ($endpoint === "messages") {
    if ($method === "GET") {
        $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
        $stmt = $pdo->prepare("SELECT id, userId, content, created_at FROM messages WHERE id > ? ORDER BY id ASC");
        $stmt->execute([$lastId]);
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    if ($method === "POST") {
        $input = getJsonInput();
        $content = $input['content'] ?? null;
        if (!$content) jsonResponse(["error" => "Missing content"], 400);

        $stmt = $pdo->prepare("INSERT INTO messages (userId, content) VALUES (?, ?)");
        $stmt->execute([$authUserId, $content]);

        jsonResponse(["success" => true, "id" => (int)$pdo->lastInsertId()], 201);
    }
    jsonResponse(["error" => "Method not allowed"], 405);
}

// ——— BOARD ———
// GET /board — odczyt tablicy (dla każdego zalogowanego)
// POST /board — zapis tablicy (tylko nauczyciel)
if ($endpoint === "board") {
    if ($method === "GET") {
        $stmt = $pdo->query("SELECT id, content FROM board ORDER BY id DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        jsonResponse($row ?: []);
    }
    if ($method === "POST") {
        if (!$authIsTeacher) {
            jsonResponse(["error" => "Only teacher can update board"], 403);
        }
        $input = getJsonInput();
        $content = $input['content'] ?? null;
        if (!$content) jsonResponse(["error" => "Missing content"], 400);

        $stmt = $pdo->prepare("INSERT INTO board (content) VALUES (?)");
        $stmt->execute([$content]);
        jsonResponse(["success" => true, "id" => (int)$pdo->lastInsertId()], 201);
    }
    jsonResponse(["error" => "Method not allowed"], 405);
}

// ——— NOTES ———
// GET /notes — odczyt notatek tylko właściciela (z tokena)
// POST /notes — zapis notatki tylko jako właściciel
if ($endpoint === "notes") {
    if ($method === "GET") {
        $stmt = $pdo->prepare("SELECT id, userId, content FROM notes WHERE userId = ?");
        $stmt->execute([$authUserId]);
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    if ($method === "POST") {
        $input = getJsonInput();
        $content = $input['content'] ?? null;
        if (!$content) jsonResponse(["error" => "Missing content"], 400);

        $stmt = $pdo->prepare("INSERT INTO notes (userId, content) VALUES (?, ?)");
        $stmt->execute([$authUserId, $content]);
        jsonResponse(["success" => true, "id" => (int)$pdo->lastInsertId()], 201);
    }
    jsonResponse(["error" => "Method not allowed"], 405);
}

// ——— Nieznany endpoint ———
jsonResponse(["error" => "Unknown endpoint"], 404);
