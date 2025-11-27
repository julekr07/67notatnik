
<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header("Content-Type: application/json; charset=UTF-8");

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require __DIR__ . '/vendor/autoload.php';

$dsn        = "mysql:host=localhost;dbname=school_api;charset=utf8mb4";
$dbUser     = "root";
$dbPass     = "";
$jwt_secret = "super_secret_key_change_me";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

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

function requireAuth($jwt_secret) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if (!$authHeader && function_exists('getallheaders')) {
        $hdrs = getallheaders();
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
        if (!isset($decoded->userId)) jsonResponse(["error" => "Invalid token payload"], 401);
        return $decoded;
    } catch (Exception $e) {
        jsonResponse(["error" => "Invalid token"], 401);
    }
}

$decoded    = requireAuth($jwt_secret);
$authUserId = (int)$decoded->userId;
$input      = getJsonInput();

// pobieranie wszystkich wiadomości (globalny czat)
if (isset($input['read']) && $input['read'] === true) {
    $stmt = $pdo->query("SELECT m.id,
                                m.userId AS senderId,
                                u.login AS senderLogin,
                                m.content,
                                m.created_at
                         FROM messages m
                         JOIN users u ON m.userId = u.id
                         ORDER BY m.created_at ASC");
    jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// wysyłanie wiadomości
if (isset($input['content'])) {
    $content = trim($input['content']);
    if (!$content) jsonResponse(["error" => "Missing content"], 400);

    try {
        $stmt = $pdo->prepare("INSERT INTO messages (userId, content, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$authUserId, $content]);
        jsonResponse(["success" => true, "id" => (int)$pdo->lastInsertId()], 201);
    } catch (Exception $e) {
        jsonResponse(["error" => $e->getMessage()], 500);
    }
}

jsonResponse(["error" => "Invalid payload"], 400);
