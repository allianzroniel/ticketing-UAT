<?php
// db_config.php - Database connection settings

define('DB_HOST', 'mysql.railway.internal');
define('DB_USER', 'root');
define('DB_PORT', '3306');
define('DB_PASS', 'mqlULJvqPGRiFGjQtFbLgwUlDriAonij');
define('DB_NAME', 'railway');

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PORT, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    return $conn;
}
