<?php
header("Content-Type: application/json; charset=UTF-8");

$host = "localhost";
$user = "root";       // dostosuj do swojego środowiska
$password = "";       // w XAMPP root zwykle nie ma hasła
$dbname = "school_api";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Błąd połączenia z bazą"]);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$sql = "SELECT id, login FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode(["error" => "Nie znaleziono użytkownika"]);
}

$stmt->close();
$conn->close();
?>
