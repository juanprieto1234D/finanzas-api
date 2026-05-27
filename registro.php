<?php
// Permitir acceso desde cualquier origen (necesario para React Native)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// 1. Conexión a la base de datos (XAMPP)
include 'config.php';

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(["error" => "Conexión fallida: " . $conn->connect_error]));
}

// 2. Escuchar la petición POST de la App
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Leer los datos que vienen de React Native
    $data = json_decode(file_get_contents("php://input"), true);
    
    $nombre = $data['nombre'];
    $email = $data['email'];
    $password = $data['password'];

   
    $sql = "INSERT INTO usuarios (nombre, email, password, rol) VALUES ('$nombre', '$email', '$password', 'user')";

    if ($conn->query($sql) === TRUE) {
        echo json_encode(["message" => "Usuario creado exitosamente"]);
    } else {
        // Si el correo ya existe, MySQL dará un error
        echo json_encode(["error" => "El correo ya está registrado o hubo un error."]);
    }
}

$conn->close();
?>