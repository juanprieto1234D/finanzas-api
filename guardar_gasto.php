<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'config.php';

if ($conn->connect_error) {
    echo json_encode(["error" => "Error de conexión a BD"]);
    exit;
}

$data        = json_decode(file_get_contents("php://input"), true);
$usuario_id  = $data['usuario_id']  ?? 0;
$monto       = $data['monto']       ?? 0;
$descripcion = $data['descripcion'] ?? '';

if (empty($usuario_id) || empty($monto)) {
    echo json_encode(["error" => "usuario_id y monto son requeridos"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO gastos (usuario_id, descripcion, monto, fecha) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("isd", $usuario_id, $descripcion, $monto);

echo $stmt->execute()
    ? json_encode(["message" => "Gasto guardado"])
    : json_encode(["error" => "Error al guardar"]);

$stmt->close();
$conn->close();
?>
