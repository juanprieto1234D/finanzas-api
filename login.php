<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Manejar preflight de React Native
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'config.php';

if ($conn->connect_error) {
    echo json_encode(["error" => "Error de conexión a BD"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$email    = $data['email']    ?? '';
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(["error" => "Correo o contraseña requeridos"]);
    exit;
}

// Prepared statement (evita SQL injection)
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ? AND password = ?");
$stmt->bind_param("ss", $email, $password);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $usuario = $result->fetch_assoc();
    echo json_encode(["message" => "Bienvenido", "usuario" => $usuario]);
} else {
    echo json_encode(["error" => "Correo o contraseña no válidos"]);
}

$stmt->close();
$conn->close();
?>
