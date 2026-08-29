<?php
session_start();
require 'db_config.php';
require_once 'history.php';

// Prevent caching of the logout transition so back-button requires a new request.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$username = $_SESSION['username'] ?? null;
$conn = getDBConnection();
if ($username) {
    recordLogHistory($conn, $username);
}
$conn->close();

session_unset();
session_destroy();
header('Location: login.php');
exit;
