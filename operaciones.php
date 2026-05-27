<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// ✅ CORRECTO
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Conexión a la base de datos gestion_finanzas
include 'config.php';

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Conexión fallida"]));
}

$data = json_decode(file_get_contents("php://input"), true);
$accion = $data['accion'] ?? '';

switch ($accion) {
    // ==========================================
    // 1. AUTENTICACIÓN Y PERFIL
    // ==========================================
    case 'registrar_usuario':
    $nombre   = $conn->real_escape_string($data['nombre']);
    $email    = $conn->real_escape_string($data['email']);
    $password = $conn->real_escape_string($data['password']);

    // Verificar si el email ya existe
    $check = $conn->query("SELECT id FROM usuarios WHERE email = '$email'");
    if ($check->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "El correo ya está registrado"]);
        break;
    }

    $sql = "INSERT INTO usuarios (nombre, email, password, rol) 
            VALUES ('$nombre', '$email', '$password', 'user')";

    if ($conn->query($sql)) {
        echo json_encode(["status" => "success", "message" => "Usuario creado exitosamente"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al crear el usuario"]);
    }
    break;
    case 'login':
        $email = $conn->real_escape_string($data['email']);
        $pass = $conn->real_escape_string($data['password']);
        $res = $conn->query("SELECT id, nombre, rol, estado FROM usuarios WHERE email = '$email' AND password = '$pass'");
        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            echo json_encode(["status" => "success", "user_id" => $user['id'], "nombre" => $user['nombre'], "rol" => $user['rol'], "estado" => $user['estado']]);
        } else {
            echo json_encode(["status" => "error", "message" => "Credenciales incorrectas"]);
        }
        break;

    case 'obtener_datos_usuario':
        $u_id = $conn->real_escape_string($data['usuario_id']);
        $res = $conn->query("SELECT nombre FROM usuarios WHERE id = '$u_id'");
        $user = $res->fetch_assoc();
        echo json_encode(["status" => "success", "nombre" => $user['nombre'] ?? 'Usuario']);
        break;

    // ==========================================
    // 2. MOVIMIENTOS (ESTADO DE CUENTA)
    // ==========================================

    case 'registrar_ingreso':
        $u_id = $conn->real_escape_string($data['usuario_id']);
        $monto = $conn->real_escape_string($data['monto']);
        $cat = $conn->real_escape_string($data['categoria']);
        $sql = "INSERT INTO transacciones (user_id, monto, tipo, categoria, fecha) 
                VALUES ('$u_id', '$monto', 'ingreso', '$cat', NOW())";
        echo ($conn->query($sql)) ? json_encode(["status" => "success"]) : json_encode(["status" => "error"]);
        break;

    case 'registrar_gasto':
        $u_id = $conn->real_escape_string($data['usuario_id']);
        $monto = $conn->real_escape_string($data['monto']);
        $desc = $conn->real_escape_string($data['descripcion'] ?? $data['concepto'] ?? 'Gasto');
        $sql = "INSERT INTO gastos (usuario_id, monto, descripcion, fecha) 
                VALUES ('$u_id', '$monto', '$desc', NOW())";
        echo ($conn->query($sql)) ? json_encode(["status" => "success"]) : json_encode(["status" => "error"]);
        break;

    case 'obtener_saldo_total':
        $u_id = $conn->real_escape_string($data['usuario_id']);
        $ing = $conn->query("SELECT SUM(monto) as t FROM transacciones WHERE user_id = '$u_id' AND tipo = 'ingreso'")->fetch_assoc()['t'] ?? 0;
        $gas = $conn->query("SELECT SUM(monto) as t FROM gastos WHERE usuario_id = '$u_id'")->fetch_assoc()['t'] ?? 0;
        echo json_encode(["status" => "success", "saldo" => (float)$ing - (float)$gas]);
        break;

    case 'obtener_movimientos':
        $u_id = $conn->real_escape_string($data['usuario_id']);
        // CORRECCIÓN: Unión de tablas para mostrar ingresos y gastos combinados
        $sql = "(SELECT monto, categoria as concepto, fecha, 'ingreso' as tipo 
                 FROM transacciones
                 WHERE user_id = '$u_id' AND tipo = 'ingreso')
                UNION 
                (SELECT monto, descripcion as concepto, fecha, 'gasto' as tipo 
                 FROM gastos 
                 WHERE usuario_id = '$u_id')
                ORDER BY fecha DESC LIMIT 15";
        $res = $conn->query($sql);
        $movs = [];
        while($row = $res->fetch_assoc()) { $movs[] = $row; }
        echo json_encode(["status" => "success", "movimientos" => $movs]);
        break;

    // NUEVA FUNCIÓN: Limpiar historial de movimientos
    case 'limpiar_historial':
        $u_id = $conn->real_escape_string($data['usuario_id']);
        // Borra registros de ambas tablas para el usuario específico
        $sql1 = "DELETE FROM transacciones WHERE user_id = '$u_id'";
        $sql2 = "DELETE FROM gastos WHERE usuario_id = '$u_id'";
        
        if ($conn->query($sql1) && $conn->query($sql2)) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => $conn->error]);
        }
        break;

    // ==========================================
    // 3. CATEGORÍAS Y METAS
    // ==========================================
    case 'registrar_categoria':
        $u_id = $conn->real_escape_string($data['usuario_id']);
        $nom = $conn->real_escape_string($data['nombre'] ?? 'Nueva');
        $pres = $conn->real_escape_string($data['presupuesto'] ?? 0);
        $sql = "INSERT INTO categorias_personalizadas (usuario_id, nombre, presupuesto_asignado) 
                VALUES ('$u_id', '$nom', '$pres')";
        echo ($conn->query($sql)) ? json_encode(["status" => "success"]) : json_encode(["status" => "error"]);
        break;

    case 'listar_categorias':
        $u_id = $conn->real_escape_string($data['usuario_id']);
        $res = $conn->query("SELECT * FROM categorias_personalizadas WHERE usuario_id = '$u_id'");
        $cats = [];
        while($row = $res->fetch_assoc()) { $cats[] = $row; }
        echo json_encode(["status" => "success", "categorias" => $cats]);
        break;

    case 'registrar_meta':
        $u_id = $conn->real_escape_string($data['usuario_id']);
        // CORRECCIÓN: Captura el nombre enviado desde la app, evitando el nombre por defecto
        $nom = $conn->real_escape_string($data['nombre_meta'] ?? $data['nombre'] ?? 'Nueva Meta');
        $obj = $conn->real_escape_string($data['monto_objetivo'] ?? $data['monto'] ?? 0);
        $sql = "INSERT INTO metas (usuario_id, nombre_meta, monto_objetivo, monto_actual) 
                VALUES ('$u_id', '$nom', '$obj', 0)";
        echo ($conn->query($sql)) ? json_encode(["status" => "success"]) : json_encode(["status" => "error"]);
        break;

    case 'listar_metas':
        $u_id = $conn->real_escape_string($data['usuario_id']);
        $res = $conn->query("SELECT * FROM metas WHERE usuario_id = '$u_id' ORDER BY id DESC");
        $metas = [];
        while($row = $res->fetch_assoc()) { $metas[] = $row; }
        echo json_encode(["status" => "success", "metas" => $metas]);
        break;

    case 'abonar_meta':
        $u_id  = $conn->real_escape_string($data['usuario_id']);
        $meta_id = $conn->real_escape_string($data['meta_id']);
        $abono = (float)($data['monto'] ?? 0);
        if ($abono <= 0) { echo json_encode(["status" => "error", "message" => "Monto inválido"]); break; }
        // Verificar que la meta pertenezca al usuario
        $check = $conn->query("SELECT id, monto_objetivo, monto_actual FROM metas WHERE id = '$meta_id' AND usuario_id = '$u_id'");
        if ($check->num_rows === 0) { echo json_encode(["status" => "error", "message" => "Meta no encontrada"]); break; }
        $meta = $check->fetch_assoc();
        $nuevo = min((float)$meta['monto_actual'] + $abono, (float)$meta['monto_objetivo']);
        $conn->query("UPDATE metas SET monto_actual = '$nuevo' WHERE id = '$meta_id'");
        echo json_encode(["status" => "success", "monto_actual" => $nuevo, "monto_objetivo" => $meta['monto_objetivo']]);
        break;

    case 'eliminar_meta':
        $u_id    = $conn->real_escape_string($data['usuario_id']);
        $meta_id = $conn->real_escape_string($data['meta_id']);
        $sql = "DELETE FROM metas WHERE id = '$meta_id' AND usuario_id = '$u_id'";
        echo ($conn->query($sql)) ? json_encode(["status" => "success"]) : json_encode(["status" => "error"]);
        break;

    // ==========================================
    // 4. PANEL ADMINISTRADOR
    // ==========================================
    case 'admin_listar_usuarios':
        $res = $conn->query("SELECT id, nombre, email, estado FROM usuarios WHERE rol != 'admin'");
        $users = [];
        while($row = $res->fetch_assoc()) { $users[] = $row; }
        echo json_encode(["status" => "success", "usuarios" => $users]);
        break;

    case 'admin_cambiar_estado':
        $u_id = $conn->real_escape_string($data['id_usuario_cambiar']);
        $nuevo_estado = $conn->real_escape_string($data['nuevo_estado']);
        $sql = "UPDATE usuarios SET estado = '$nuevo_estado' WHERE id = '$u_id'";
        echo ($conn->query($sql)) ? json_encode(["status" => "success"]) : json_encode(["status" => "error"]);
        break;
}

$conn->close();
?>