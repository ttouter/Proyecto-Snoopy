<?php
session_start();
header('Content-Type: application/json');

// Ajuste de ruta: Al estar en la misma carpeta, basta con el nombre del archivo
require_once '../Configuracion/conexion.php';

$response = [
    "success" => false, 
    "message" => "Error desconocido",
    "redirect" => ""
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    $role = $input['role'] ?? '';

    if (empty($email) || empty($password) || empty($role)) {
        echo json_encode(["success" => false, "message" => "Por favor complete todos los campos."]);
        exit;
    }

    try {
        // Llamada al Procedimiento Almacenado verificado
        $stmt = $pdo->prepare("CALL sp_ObtenerDatosLogin(?, ?)");
        $stmt->execute([$email, $role]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($user['activo'] == 0) {
                $response["message"] = "Esta cuenta ha sido desactivada.";
            } 
            else if ($password === $user['password_hash']) {
                $_SESSION['user_id'] = $user['id_usuario'];
                $_SESSION['user_name'] = $user['nombres'] . ' ' . $user['apellidos'];
                $_SESSION['user_role'] = $user['tipo_usuario'];
                
                $response["success"] = true;
                $response["message"] = "Login correcto";
                
                // Redirecciones
                if ($user['tipo_usuario'] == 'ADMIN') {
                    $response["redirect"] = "adminPanel.html";
                } elseif ($role == 'JUEZ') { 
                    $response["redirect"] = "juezPanel.html";
                } elseif ($role == 'COACH') { 
                    $response["redirect"] = "coachPanel.html";
                }
            } else {
                $response["message"] = "Contraseña incorrecta.";
            }
        } else {
            $response["message"] = "Usuario no encontrado o rol incorrecto.";
        }
    } catch (PDOException $e) {
        $response["message"] = "Error de Base de Datos: " . $e->getMessage();
    }
} else {
    $response["message"] = "Método no permitido.";
}

echo json_encode($response);
?>