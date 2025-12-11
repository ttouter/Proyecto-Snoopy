<?php
// 1. Configuración de cabeceras para asegurar que SIEMPRE sea JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 2. Desactivar impresión de errores HTML que rompen el JSON
error_reporting(0);
ini_set('display_errors', 0);

$response = ["success" => false, "message" => "Error desconocido"];

try {
    // 3. Ruta Absoluta: Esto arregla problemas de 'file not found'
    $rutaConexion = __DIR__ . '/conexion.php';

    if (!file_exists($rutaConexion)) {
        throw new Exception("No se encuentra el archivo 'conexion.php' en: " . $rutaConexion);
    }

    require_once $rutaConexion;

    // 4. Verificar conexión
    if (!isset($pdo) || !$pdo) {
        throw new Exception("La conexión a la base de datos falló. Verifique 'conexion.php'.");
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // --- GET: LISTAR COACHES ---
    if ($method === 'GET') {
        // Consulta directa para listar coaches activos
        $sql = "SELECT id_usuario, nombres, apellidos, email, escuela_proc, tipo_usuario 
                FROM usuarios 
                WHERE tipo_usuario = 'COACH' AND activo = 1 
                ORDER BY nombres ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $coaches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si no hay coaches, enviamos un array vacío pero con success = true
        echo json_encode(["success" => true, "data" => $coaches]);
        exit;
    }

    // --- POST: PROMOVER A JUEZ ---
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['accion']) || $input['accion'] !== 'promover') {
            throw new Exception("Acción no válida.");
        }

        $idUsuario = $input['id'];

        // Llamada al Procedimiento Almacenado
        $sql = "CALL PromoverCoachAJuez(:id, @resultado)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->closeCursor();

        $res = $pdo->query("SELECT @resultado as mensaje")->fetch(PDO::FETCH_ASSOC);
        $mensajeBD = $res['mensaje'] ?? 'Error desconocido al ejecutar SP.';

        if (strpos($mensajeBD, 'ÉXITO') !== false) {
            $response["success"] = true;
            $response["message"] = $mensajeBD;
        } else {
            $response["success"] = false;
            $response["message"] = $mensajeBD;
        }
    }

} catch (Exception $e) {
    // Capturamos cualquier error y lo enviamos como JSON válido
    $response["success"] = false;
    $response["message"] = $e->getMessage();
}

echo json_encode($response);
?>