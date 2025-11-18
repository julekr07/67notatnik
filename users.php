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

$sql = "SELECT id, login FROM users";
$result = $conn->query($sql);

$users = [];
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

echo json_encode($users, JSON_UNESCAPED_UNICODE);
$conn->close();
?>
