<?php
// api.php — Proste REST API z JWT i MySQL
// Wymaga: composer require firebase/php-jwt

header("Content-Type: application/json; charset=UTF-8");

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require __DIR__ . '/vendor/autoload.php';

// ——— Konfiguracja ———
$dsn        = "mysql:host=localhost;dbname=school_api;charset=utf8mb4";
$dbUser     = "root";     // zmień na swoje dane
$dbPass     = "";         // zmień na swoje dane
$jwt_secret = "super_secret_key_change_me"; // zmień i trzymaj w bezpiecznym miejscu

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

function getJsonInput(): array {
    $raw = file_get_contents("php://input");
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Poprawne parsowanie ścieżki dla:
 * - /auth
 * - /api.php/auth
 * - /subdir/api.php/auth
 */
function parsePath(): array {
    $uriPath   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? "/";
    $script    = $_SERVER['SCRIPT_NAME'] ?? "";
    $scriptBase= basename($script);

    // Usuń katalog bazowy skryptu (jeśli jest w subfolderze)
    $scriptDir = rtrim(dirname($script), '/');
    if ($scriptDir !== '' && $scriptDir !== '/' && strpos($uriPath, $scriptDir) === 0) {
        $uriPath = substr($uriPath, strlen($scriptDir));
        if ($uriPath === false) $uriPath = "/";
    }

    // Usuń samą nazwę skryptu, jeśli jest w ścieżce (np. /api.php/auth)
    $uriPath = ltrim($uriPath, '/');
    if ($scriptBase && strpos($uriPath, $scriptBase) === 0) {
        $uriPath = substr($uriPath, strlen($scriptBase));
    }

    $uriPath = trim($uriPath, "/");
    return $uriPath === '' ? [] : explode("/", $uriPath);
}

// Wymaga tokena dla wszystkich endpointów poza /auth
function requireAuth($jwt_secret) {
    // 1. standardowo w $_SERVER
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    // 2. czasem w REDIRECT_HTTP_AUTHORIZATION
    if (!$authHeader && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    // 3. getallheaders fallback
    if (!$authHeader && function_exists('getallheaders')) {
        $hdrs = getallheaders();
        foreach ($hdrs as $k => $v) {
            if (strtolower($k) === 'authorization') { $authHeader = $v; break; }
        }
    }
    // 4. apache_request_headers fallback
    if (!$authHeader && function_exists('apache_request_headers')) {
        $hdrs = apache_request_headers();
        foreach ($hdrs as $k => $v) {
            if (strtolower($k) === 'authorization') { $authHeader = $v; break; }
        }
    }

    if (!$authHeader) jsonResponse(["error" => "Missing token"], 401);

    if (stripos($authHeader, 'bearer ') === 0) {
        $token = trim(substr($authHeader, 7));
    } else {
        $token = trim($authHeader);
    }
    if (!$token) jsonResponse(["error" => "Missing token"], 401);

    try {
        $decoded = Firebase\JWT\JWT::decode($token, new Firebase\JWT\Key($jwt_secret, 'HS256'));
        if (!isset($decoded->userId) || !isset($decoded->isTeacher)) jsonResponse(["error" => "Invalid token payload"], 401);
        return $decoded;
    } catch (Exception $e) {
        jsonResponse(["error" => "Invalid token"], 401);
    }
}


// ——— Routing ———
$method   = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$parts    = parsePath();
$endpoint = $parts[0] ?? "";

/**
 * AUTH — jedyny endpoint bez tokena
 * Metoda: GET
 * Body (JSON): { "login": "...", "password": "..." }
 */
if ($endpoint === "auth" && $method === "GET") {
    $input    = getJsonInput();
    $login    = $input['login'] ?? null;
    $password = $input['password'] ?? null;

    if (!$login || !$password) {
        jsonResponse(["error" => "Missing login or password"], 400);
    }

    // Użytkownik: users(id, login, password(hash), isTeacher)
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

// Wszystkie inne endpointy wymagają tokena
$decoded        = requireAuth($jwt_secret);
$authUserId     = (int)$decoded->userId;
$authIsTeacher  = (bool)$decoded->isTeacher;

// ——— MESSAGES ———
// GET /messages?last_id=X — zwraca nowe wiadomości (polling)
// POST /messages — dodaje wiadomość od zalogowanego użytkownika
if ($endpoint === "messages") {
    if ($method === "GET") {
        $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
        $stmt = $pdo->prepare("SELECT id, userId, content, created_at FROM messages WHERE id > ? ORDER BY id ASC");
        $stmt->execute([$lastId]);
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    if ($method === "POST") {
        $input   = getJsonInput();
        $content = $input['content'] ?? null;
        if (!$content) jsonResponse(["error" => "Missing content"], 400);

        $stmt = $pdo->prepare("INSERT INTO messages (userId, content) VALUES (?, ?)");
        $stmt->execute([$authUserId, $content]);
        jsonResponse(["success" => true, "id" => (int)$pdo->lastInsertId()], 201);
    }
    jsonResponse(["error" => "Method not allowed"], 405);
}

// ——— BOARD ———
// GET /board — odczyt (dla każdego zalogowanego)
// POST /board — zapis (tylko nauczyciel)
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
        $input   = getJsonInput();
        $content = $input['content'] ?? null;
        if (!$content) jsonResponse(["error" => "Missing content"], 400);

        $stmt = $pdo->prepare("INSERT INTO board (content) VALUES (?)");
        $stmt->execute([$content]);
        jsonResponse(["success" => true, "id" => (int)$pdo->lastInsertId()], 201);
    }
    jsonResponse(["error" => "Method not allowed"], 405);
}

// ——— NOTES ———
// GET /notes — odczyt notatek właściciela (z tokena)
// POST /notes — zapis notatki jako właściciel
if ($endpoint === "notes") {
    if ($method === "GET") {
        $stmt = $pdo->prepare("SELECT id, userId, content FROM notes WHERE userId = ?");
        $stmt->execute([$authUserId]);
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    if ($method === "POST") {
        $input   = getJsonInput();
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
