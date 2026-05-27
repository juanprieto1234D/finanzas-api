<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
$conn = new mysqli("localhost", "root", "", "gestion_finanzas");

$data = json_decode(file_get_contents("php://input"), true);
$monto = $data['monto'];
$descripcion = $data['descripcion'];
$usuario_id = 1; // Por ahora lo dejamos fijo para tu prueba de mañana

$sql = "INSERT INTO gastos (usuario_id, descripcion, monto) VALUES ('$usuario_id', '$descripcion', '$monto')";

if ($conn->query($sql) === TRUE) {
    echo json_encode(["message" => "Gasto guardado"]);
} else {
    echo json_encode(["error" => "Error al guardar"]);
}
?>