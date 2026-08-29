<?php
// db_config.php - Database connection settings

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Oracle9290419');
define('DB_NAME', 'ticketing-uat');

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}
