<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'config.php';

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Conexión fallida"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$accion = $data['accion'] ?? '';

switch ($accion) {

    // ==========================================
    // 1. AUTENTICACIÓN Y PERFIL
    // ==========================================

    case 'registrar_usuario':
        $nombre   = $data['nombre']   ?? '';
        $email    = $data['email']    ?? '';
        $password = $data['password'] ?? '';

        $check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            echo json_encode(["status" => "error", "message" => "El correo ya está registrado"]);
            $check->close();
            break;
        }
        $check->close();

        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, 'user')");
        $stmt->bind_param("sss", $nombre, $email, $password);
        echo $stmt->execute()
            ? json_encode(["status" => "success", "message" => "Usuario creado exitosamente"])
            : json_encode(["status" => "error", "message" => "Error al crear el usuario"]);
        $stmt->close();
        break;

    case 'login':
        $email = $data['email']    ?? '';
        $pass  = $data['password'] ?? '';

        $stmt = $conn->prepare("SELECT id, nombre, rol, estado FROM usuarios WHERE email = ? AND password = ?");
        $stmt->bind_param("ss", $email, $pass);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $user = $res->fetch_assoc();
            echo json_encode(["status" => "success", "user_id" => $user['id'], "nombre" => $user['nombre'], "rol" => $user['rol'], "estado" => $user['estado']]);
        } else {
            echo json_encode(["status" => "error", "message" => "Credenciales incorrectas"]);
        }
        $stmt->close();
        break;

    case 'obtener_datos_usuario':
        $u_id = $data['usuario_id'] ?? 0;

        $stmt = $conn->prepare("SELECT nombre FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $u_id);
        $stmt->execute();
        $res  = $stmt->get_result();
        $user = $res->fetch_assoc();
        echo json_encode(["status" => "success", "nombre" => $user['nombre'] ?? 'Usuario']);
        $stmt->close();
        break;

    // ==========================================
    // 2. MOVIMIENTOS (ESTADO DE CUENTA)
    // ==========================================

    case 'registrar_ingreso':
        $u_id  = $data['usuario_id'] ?? 0;
        $monto = $data['monto']      ?? 0;
        $cat   = $data['categoria']  ?? '';

        $stmt = $conn->prepare("INSERT INTO transacciones (user_id, monto, tipo, categoria, fecha) VALUES (?, ?, 'ingreso', ?, NOW())");
        $stmt->bind_param("ids", $u_id, $monto, $cat);
        echo $stmt->execute()
            ? json_encode(["status" => "success"])
            : json_encode(["status" => "error"]);
        $stmt->close();
        break;

    case 'registrar_gasto':
        $u_id  = $data['usuario_id'] ?? 0;
        $monto = $data['monto']      ?? 0;
        $desc  = $data['descripcion'] ?? $data['concepto'] ?? 'Gasto';

        $stmt = $conn->prepare("INSERT INTO gastos (usuario_id, monto, descripcion, fecha) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("ids", $u_id, $monto, $desc);
        echo $stmt->execute()
            ? json_encode(["status" => "success"])
            : json_encode(["status" => "error"]);
        $stmt->close();
        break;

    case 'obtener_saldo_total':
        $u_id = $data['usuario_id'] ?? 0;

        $s1 = $conn->prepare("SELECT COALESCE(SUM(monto),0) as t FROM transacciones WHERE user_id = ? AND tipo = 'ingreso'");
        $s1->bind_param("i", $u_id);
        $s1->execute();
        $ing = $s1->get_result()->fetch_assoc()['t'];
        $s1->close();

        $s2 = $conn->prepare("SELECT COALESCE(SUM(monto),0) as t FROM gastos WHERE usuario_id = ?");
        $s2->bind_param("i", $u_id);
        $s2->execute();
        $gas = $s2->get_result()->fetch_assoc()['t'];
        $s2->close();

        echo json_encode(["status" => "success", "saldo" => (float)$ing - (float)$gas]);
        break;

    case 'obtener_movimientos':
        $u_id = $data['usuario_id'] ?? 0;

        $stmt = $conn->prepare(
            "(SELECT monto, categoria as concepto, fecha, 'ingreso' as tipo 
              FROM transacciones WHERE user_id = ? AND tipo = 'ingreso')
             UNION 
             (SELECT monto, descripcion as concepto, fecha, 'gasto' as tipo 
              FROM gastos WHERE usuario_id = ?)
             ORDER BY fecha DESC LIMIT 15"
        );
        $stmt->bind_param("ii", $u_id, $u_id);
        $stmt->execute();
        $res  = $stmt->get_result();
        $movs = [];
        while ($row = $res->fetch_assoc()) { $movs[] = $row; }
        echo json_encode(["status" => "success", "movimientos" => $movs]);
        $stmt->close();
        break;

    case 'limpiar_historial':
        $u_id = $data['usuario_id'] ?? 0;

        $s1 = $conn->prepare("DELETE FROM transacciones WHERE user_id = ?");
        $s1->bind_param("i", $u_id);
        $r1 = $s1->execute();
        $s1->close();

        $s2 = $conn->prepare("DELETE FROM gastos WHERE usuario_id = ?");
        $s2->bind_param("i", $u_id);
        $r2 = $s2->execute();
        $s2->close();

        echo ($r1 && $r2)
            ? json_encode(["status" => "success"])
            : json_encode(["status" => "error", "message" => $conn->error]);
        break;

    // ==========================================
    // 3. CATEGORÍAS Y METAS
    // ==========================================

    case 'registrar_categoria':
        $u_id = $data['usuario_id']  ?? 0;
        $nom  = $data['nombre']      ?? 'Nueva';
        $pres = $data['presupuesto'] ?? 0;

        $stmt = $conn->prepare("INSERT INTO categorias_personalizadas (usuario_id, nombre, presupuesto_asignado) VALUES (?, ?, ?)");
        $stmt->bind_param("isd", $u_id, $nom, $pres);
        echo $stmt->execute()
            ? json_encode(["status" => "success"])
            : json_encode(["status" => "error"]);
        $stmt->close();
        break;

    case 'listar_categorias':
        $u_id = $data['usuario_id'] ?? 0;

        $stmt = $conn->prepare("SELECT * FROM categorias_personalizadas WHERE usuario_id = ?");
        $stmt->bind_param("i", $u_id);
        $stmt->execute();
        $res  = $stmt->get_result();
        $cats = [];
        while ($row = $res->fetch_assoc()) { $cats[] = $row; }
        echo json_encode(["status" => "success", "categorias" => $cats]);
        $stmt->close();
        break;

    case 'registrar_meta':
        $u_id = $data['usuario_id']   ?? 0;
        $nom  = $data['nombre_meta']  ?? $data['nombre'] ?? 'Nueva Meta';
        $obj  = $data['monto_objetivo'] ?? $data['monto'] ?? 0;

        $stmt = $conn->prepare("INSERT INTO metas (usuario_id, nombre_meta, monto_objetivo, monto_actual) VALUES (?, ?, ?, 0)");
        $stmt->bind_param("isd", $u_id, $nom, $obj);
        echo $stmt->execute()
            ? json_encode(["status" => "success"])
            : json_encode(["status" => "error"]);
        $stmt->close();
        break;

    case 'listar_metas':
        $u_id = $data['usuario_id'] ?? 0;

        $stmt = $conn->prepare("SELECT * FROM metas WHERE usuario_id = ? ORDER BY id DESC");
        $stmt->bind_param("i", $u_id);
        $stmt->execute();
        $res   = $stmt->get_result();
        $metas = [];
        while ($row = $res->fetch_assoc()) { $metas[] = $row; }
        echo json_encode(["status" => "success", "metas" => $metas]);
        $stmt->close();
        break;

    case 'abonar_meta':
        $u_id    = $data['usuario_id'] ?? 0;
        $meta_id = $data['meta_id']    ?? 0;
        $abono   = (float)($data['monto'] ?? 0);

        if ($abono <= 0) {
            echo json_encode(["status" => "error", "message" => "Monto inválido"]);
            break;
        }

        $check = $conn->prepare("SELECT id, monto_objetivo, monto_actual FROM metas WHERE id = ? AND usuario_id = ?");
        $check->bind_param("ii", $meta_id, $u_id);
        $check->execute();
        $res = $check->get_result();
        if ($res->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "Meta no encontrada"]);
            $check->close();
            break;
        }
        $meta  = $res->fetch_assoc();
        $check->close();

        $nuevo = min((float)$meta['monto_actual'] + $abono, (float)$meta['monto_objetivo']);
        $upd   = $conn->prepare("UPDATE metas SET monto_actual = ? WHERE id = ?");
        $upd->bind_param("di", $nuevo, $meta_id);
        $upd->execute();
        $upd->close();

        echo json_encode(["status" => "success", "monto_actual" => $nuevo, "monto_objetivo" => $meta['monto_objetivo']]);
        break;

    case 'eliminar_meta':
        $u_id    = $data['usuario_id'] ?? 0;
        $meta_id = $data['meta_id']    ?? 0;

        $stmt = $conn->prepare("DELETE FROM metas WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param("ii", $meta_id, $u_id);
        echo $stmt->execute()
            ? json_encode(["status" => "success"])
            : json_encode(["status" => "error"]);
        $stmt->close();
        break;

    // ==========================================
    // 4. PANEL ADMINISTRADOR
    // ==========================================

    case 'admin_listar_usuarios':
        $res   = $conn->query("SELECT id, nombre, email, estado FROM usuarios WHERE rol != 'admin'");
        $users = [];
        while ($row = $res->fetch_assoc()) { $users[] = $row; }
        echo json_encode(["status" => "success", "usuarios" => $users]);
        break;

    case 'admin_cambiar_estado':
        $u_id         = $data['id_usuario_cambiar'] ?? 0;
        $nuevo_estado = $data['nuevo_estado']       ?? '';

        $stmt = $conn->prepare("UPDATE usuarios SET estado = ? WHERE id = ?");
        $stmt->bind_param("si", $nuevo_estado, $u_id);
        echo $stmt->execute()
            ? json_encode(["status" => "success"])
            : json_encode(["status" => "error"]);
        $stmt->close();
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Acción no reconocida"]);
        break;
}

$conn->close();
?>
