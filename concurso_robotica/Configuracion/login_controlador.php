<?php
session_start();
header('Content-Type: application/json');

// Incluir tu archivo de conexión existente
// Asegúrate de que la ruta sea correcta relativa a este archivo
require_once '../Configuracion/conexion.php';

$response = [
    "success" => false, 
    "message" => "Error desconocido",
    "redirect" => ""
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Obtener los datos enviados desde el fetch de JS (JSON)
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Si no es JSON, intentar con POST normal (form-data)
    $email = $input['email'] ?? $_POST['email'] ?? '';
    $password = $input['password'] ?? $_POST['password'] ?? '';
    $role = $input['role'] ?? $_POST['role'] ?? '';

    // Validaciones básicas
    if (empty($email) || empty($password) || empty($role)) {
        $response["message"] = "Por favor complete todos los campos.";
        echo json_encode($response);
        exit;
    }

    try {
        // =================================================================
        // LLAMADA AL PROCEDIMIENTO ALMACENADO
        // sp_ObtenerDatosLogin(IN p_email VARCHAR(100), IN p_tipo VARCHAR(20))
        // =================================================================
        $stmt = $pdo->prepare("CALL sp_ObtenerDatosLogin(?, ?)");
        $stmt->execute([$email, $role]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si el procedimiento devuelve una fila, el usuario existe con ese rol
        if ($user) {
            
            // 1. Verificar si el usuario está activo (campo 'activo' en tu SQL)
            if ($user['activo'] == 0) {
                $response["message"] = "Esta cuenta ha sido desactivada.";
            } 
            // 2. Verificar contraseña
            // NOTA: En tu SQL (Proyecto_RazoV2.sql) insertaste 'admin123' como texto plano.
            // Por eso usamos comparación directa (==). Si usas hash en el futuro, usa password_verify().
            else if ($password === $user['password_hash']) {
                
                // --- LOGIN EXITOSO ---
                
                // Guardar datos en sesión PHP
                $_SESSION['user_id'] = $user['id_usuario'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['nombres'] . ' ' . $user['apellidos'];
                $_SESSION['user_role'] = $user['tipo_usuario'];
                $_SESSION['logged_in'] = true;

                $response["success"] = true;
                $response["message"] = "Login correcto";
                
                // Definir redirección según el rol
                // Nota: Los usuarios 'COACH_JUEZ' pueden entrar como JUEZ o COACH
                if ($user['tipo_usuario'] == 'ADMIN') {
                    $response["redirect"] = "adminPanel.html";
                } elseif ($role == 'JUEZ') { // Entró seleccionando perfil JUEZ
                    $response["redirect"] = "juezPanel.html";
                } elseif ($role == 'COACH') { // Entró seleccionando perfil COACH
                    $response["redirect"] = "coachPanel.html";
                } else {
                    $response["redirect"] = "index.html"; // Fallback
                }
                
            } else {
                $response["message"] = "Contraseña incorrecta.";
            }
        } else {
            // El SP no devolvió nada: El email no existe O el rol no coincide
            $response["message"] = "Usuario no encontrado o no tiene permisos para el rol seleccionado ($role).";
        }

        $stmt->closeCursor(); // Liberar el puntero para siguientes consultas si hubiese

    } catch (PDOException $e) {
        $response["message"] = "Error de Base de Datos: " . $e->getMessage();
    }
} else {
    $response["message"] = "Método de solicitud no válido.";
}

echo json_encode($response);
?>