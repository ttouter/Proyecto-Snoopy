<?php
$host = "localhost";
$port = "3307"; // Cambien su puerto nmmn 
$dbname = "concurso_robotica";
$user = "root";
$pass = ""; 

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Mensaje de conexión exitosa agregado
    echo "✅ Conexión exitosa a la base de datos";
    
} catch (PDOException $e) {
    // En caso que falle la conexión
    die("❌ Error Crítico de Conexión: " . $e->getMessage());
}
?>