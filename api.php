<?php
// --- CORS ---
header("Access-Control-Allow-Origin: *"); // albo wpisz konkretny adres np. http://192.168.0.23:3000
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With");

// Obsługa preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header("Content-Type: application/json; charset=UTF-8");


use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require __DIR__ . '/vendor/autoload.php';

// ——— Konfiguracja ———
$dsn        = "mysql:host=localhost;dbname=school_api;charset=utf8mb4";
$dbUser     = "root";
$dbPass     = "";
$jwt_secret = "super_secret_key_change_me";

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

function parsePath(): array {
    $uriPath   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? "/";
    $script    = $_SERVER['SCRIPT_NAME'] ?? "";
    $scriptBase= basename($script);
    $scriptDir = rtrim(dirname($script), '/');
    if ($scriptDir !== '' && $scriptDir !== '/' && strpos($uriPath, $scriptDir) === 0) {
        $uriPath = substr($uriPath, strlen($scriptDir));
        if ($uriPath === false) $uriPath = "/";
    }
    $uriPath = ltrim($uriPath, '/');
    if ($scriptBase && strpos($uriPath, $scriptBase) === 0) {
        $uriPath = substr($uriPath, strlen($scriptBase));
    }
    $uriPath = trim($uriPath, "/");
    return $uriPath === '' ? [] : explode("/", $uriPath);
}

function requireAuth($jwt_secret) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if (!$authHeader && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if (!$authHeader && function_exists('getallheaders')) {
        $hdrs = getallheaders();
        foreach ($hdrs as $k => $v) {
            if (strtolower($k) === 'authorization') { $authHeader = $v; break; }
        }
    }
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
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));
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

// Wszystko tylko POST
if ($method !== "POST") {
    jsonResponse(["error" => "Only POST allowed"], 405);
}

/**
 * AUTH — jedyny endpoint bez tokena
 * Metoda: POST
 * Body (JSON): { "login": "...", "password": "..." }
 */
if ($endpoint === "auth") {
    $input    = getJsonInput();
    $login    = $input['login'] ?? null;
    $password = $input['password'] ?? null;

    if (!$login || !$password) {
        jsonResponse(["error" => "Missing login or password"], 400);
    }

    $stmt = $pdo->prepare("SELECT id, login, password, isTeacher FROM users WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        jsonResponse(["error" => "Invalid credentials"], 401);
    }

    $payload = [
        "userId"    => (int)$user['id'],
        "isTeacher" => (bool)$user['isTeacher'],
        "exp"       => time() + 3600
    ];

    $jwt = JWT::encode($payload, $jwt_secret, 'HS256');
    jsonResponse(["token" => $jwt,
                    "userid"=> $user['id']]);
}

// Wszystkie inne endpointy wymagają tokena
$decoded        = requireAuth($jwt_secret);
$authUserId     = (int)$decoded->userId;
$authIsTeacher  = (bool)$decoded->isTeacher;

if ($endpoint === "checkauth") 
    jsonResponse(["Auth" => "True"]);

// ——— MESSAGES ———
if ($endpoint === "messages") {
    $input   = getJsonInput();
    if (isset($input['last_id'])) {
        $lastId = (int)$input['last_id'];
        $stmt = $pdo->prepare("SELECT id, userId, content, created_at FROM messages WHERE id > ? ORDER BY id ASC");
        $stmt->execute([$lastId]);
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif (isset($input['content'])) {
        $content = $input['content'];
        if (!$content) jsonResponse(["error" => "Missing content"], 400);
        $stmt = $pdo->prepare("INSERT INTO messages (userId, content) VALUES (?, ?)");
        $stmt->execute([$authUserId, $content]);
        jsonResponse(["success" => true, "id" => (int)$pdo->lastInsertId()], 201);
    }
    jsonResponse(["error" => "Invalid payload"], 400);
}

// ——— BOARD ———
if ($endpoint === "board") {
    $input   = getJsonInput();
    if (isset($input['read']) && $input['read'] === true) {
        $stmt = $pdo->query("SELECT id, content FROM board ORDER BY id DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        jsonResponse($row ?: []);
    } elseif (isset($input['content'])) {
        if (!$authIsTeacher) {
            jsonResponse(["error" => "Only teacher can update board"], 403);
        }
        $content = $input['content'];
        if (!$content) jsonResponse(["error" => "Missing content"], 400);
        $stmt = $pdo->prepare("INSERT INTO board (content) VALUES (?)");
        $stmt->execute([$content]);
        jsonResponse(["success" => true, "id" => (int)$pdo->lastInsertId()], 201);
    }
    jsonResponse(["error" => "Invalid payload"], 400);
}

// ——— NOTES ———
if ($endpoint === "notes") {
    $input   = getJsonInput();
    if (isset($input['read']) && $input['read'] === true) {
        $stmt = $pdo->prepare("SELECT id, userId, content FROM notes WHERE userId = ?");
        $stmt->execute([$authUserId]);
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif (isset($input['content'])) {
        $content = $input['content'];
        if (!$content) jsonResponse(["error" => "Missing content"], 400);
        $stmt = $pdo->prepare("INSERT INTO notes (userId, content) VALUES (?, ?)");
        $stmt->execute([$authUserId, $content]);
        jsonResponse(["success" => true, "id" => (int)$pdo->lastInsertId()], 201);
    }
    jsonResponse(["error" => "Invalid payload"], 400);
}

// ——— Nieznany endpoint ———
jsonResponse(["error" => "Unknown endpoint"], 404);
