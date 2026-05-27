<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'config.php';

if ($conn->connect_error) {
    echo json_encode(["error" => "Conexión fallida: " . $conn->connect_error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    $nombre   = $data['nombre']   ?? '';
    $email    = $data['email']    ?? '';
    $password = $data['password'] ?? '';

    if (empty($nombre) || empty($email) || empty($password)) {
        echo json_encode(["error" => "Todos los campos son requeridos"]);
        exit;
    }

    // Verificar si el correo ya existe
    $check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo json_encode(["error" => "El correo ya está registrado"]);
        $check->close();
        exit;
    }
    $check->close();

    // Insertar usuario
    $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, 'user')");
    $stmt->bind_param("sss", $nombre, $email, $password);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Usuario creado exitosamente"]);
    } else {
        echo json_encode(["error" => "Error al crear el usuario"]);
    }

    $stmt->close();
}

$conn->close();
?>
