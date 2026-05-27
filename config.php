<?php
// config.php - Central de conexión para Railway

$host     = getenv('MYSQLHOST');
$user     = getenv('MYSQLUSER');
$password = getenv('MYSQLPASSWORD');
$dbname   = getenv('MYSQLDATABASE');
$port     = getenv('MYSQLPORT');

// Crear la conexión
$conn = new mysqli($host, $user, $password, $dbname, $port);

// Verificar conexión
if ($conn->connect_error) {
    echo json_encode(["error" => "Error de conexión a la BD en Railway"]);
    exit;
}

// Configurar charset para evitar problemas con tildes
$conn->set_charset("utf8");
?>