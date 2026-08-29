<?php
// Use Railway's environment variables if available, otherwise fallback to XAMPP local settings
$host = $_SERVER['MYSQLHOST'] ?? getenv('MYSQLHOST') ?: 'mysql.railway.internal';
$port = $_SERVER['MYSQLPORT'] ?? getenv('MYSQLPORT') ?: '3306';
$db   = $_SERVER['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?: 'railway';
$user = $_SERVER['MYSQLUSER'] ?? getenv('MYSQLUSER') ?: 'root';
$pass = $_SERVER['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?: 'mqlULJvqPGRiFGjQtFbLgwUlDriAonij';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (\PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
