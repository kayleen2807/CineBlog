<?php
//crear conexion
$conn = new mysqli("localhost", "root", "", "cineblog_db");

//verificar conexion
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

//manejar caracteres especiales
$conn->set_charset("utf8mb4");

// Asegurar columna usada por el feed/admin
$conn->query("ALTER TABLE posts ADD COLUMN IF NOT EXISTS editado_por_admin TINYINT(1) NOT NULL DEFAULT 0");
?>