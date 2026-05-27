<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Conexión a la base de datos
include 'config.php';

if ($conn->connect_error) {
    echo json_encode(["error" => "Error de conexión a BD"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'];
$password = $data['password'];

// Verificación de usuario
$sql = "SELECT * FROM usuarios WHERE email = '$email' AND password = '$password'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo json_encode(["message" => "Bienvenido"]);
} else {
    echo json_encode(["error" => "Correo o contraseña no válidos"]);
}

$conn->close();
?>