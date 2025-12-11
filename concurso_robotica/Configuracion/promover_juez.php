<?php
// 1. Configuración de cabeceras JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 2. Desactivar errores visuales HTML
error_reporting(0);
ini_set('display_errors', 0);

$response = ["success" => false, "message" => "Error desconocido"];

try {
    // 3. Conexión
    $rutaConexion = __DIR__ . '/conexion.php';
    if (!file_exists($rutaConexion)) {
        throw new Exception("No se encuentra el archivo conexion.php");
    }
    require_once $rutaConexion;

    if (!isset($pdo) || !$pdo) {
        throw new Exception("Error de conexión a la base de datos.");
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // --- GET: LISTAR USUARIOS (COACHES Y JUECES) CON DETALLES DE CATEGORÍAS ---
    if ($method === 'GET') {
        // Obtenemos Coaches, Jueces y Ambos. 
        // Agregamos subconsultas para traer las categorías donde participan.
        $sql = "SELECT 
                    u.id_usuario, 
                    u.nombres, 
                    u.apellidos, 
                    u.email, 
                    u.escuela_proc, 
                    u.tipo_usuario,
                    -- Subconsulta: Categorías donde tiene equipos (Rol Coach)
                    (
                        SELECT GROUP_CONCAT(DISTINCT c.nombre_categoria SEPARATOR ', ')
                        FROM equipos e
                        JOIN categorias c ON e.id_categoria = c.id_categoria
                        WHERE e.id_coach = u.id_usuario AND e.activo = 1
                    ) as categorias_equipos,
                    -- Subconsulta: Categorías donde es juez (Rol Juez)
                    (
                        SELECT GROUP_CONCAT(DISTINCT c.nombre_categoria SEPARATOR ', ')
                        FROM jueces_eventos je
                        JOIN categorias c ON je.id_categoria = c.id_categoria
                        WHERE je.id_juez = u.id_usuario
                    ) as categorias_juez
                FROM usuarios u 
                WHERE (u.tipo_usuario = 'COACH' OR u.tipo_usuario = 'COACH_JUEZ' OR u.tipo_usuario = 'JUEZ') 
                  AND u.activo = 1 
                ORDER BY u.nombres ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "data" => $usuarios]);
        exit;
    }

    // --- POST: ACTUALIZAR ROL ---
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validamos que sea la acción correcta
        if (!isset($input['accion']) || ($input['accion'] !== 'actualizar_rol' && $input['accion'] !== 'promover')) {
            throw new Exception("Acción no válida o no especificada.");
        }

        $idUsuario = $input['id'];
        
        // Si viene el campo 'rol' lo usamos, si no, asumimos que es una promoción antigua a COACH_JUEZ
        $nuevoRol = isset($input['rol']) ? $input['rol'] : 'COACH_JUEZ';

        // Validar que el rol sea uno de los permitidos por el ENUM de la BD
        $rolesPermitidos = ['COACH', 'JUEZ', 'COACH_JUEZ'];
        if (!in_array($nuevoRol, $rolesPermitidos)) {
            throw new Exception("El rol seleccionado no es válido.");
        }

        // Ejecutamos UPDATE directo
        $sql = "UPDATE usuarios SET tipo_usuario = :rol WHERE id_usuario = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':rol', $nuevoRol);
        $stmt->bindParam(':id', $idUsuario);
        
        if ($stmt->execute()) {
            $response["success"] = true;
            $response["message"] = "Rol actualizado correctamente a " . $nuevoRol;
        } else {
            $response["success"] = false;
            $response["message"] = "No se pudo actualizar el registro en la base de datos.";
        }
    }

} catch (Exception $e) {
    $response["success"] = false;
    $response["message"] = $e->getMessage();
}

echo json_encode($response);
?>