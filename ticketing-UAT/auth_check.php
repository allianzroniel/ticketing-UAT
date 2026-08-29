<?php
// auth_check.php - Include this file at the top of protected pages
// to restrict access by role.
//
// Usage:
//   require 'auth_check.php';
//   requireRole(['admin', 'super_admin']); // allows these roles only

session_start();

// Prevent browser caching of protected pages.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function requireRole(array $allowedRoles) {
    requireLogin();

    if (!in_array($_SESSION['role'], $allowedRoles, true)) {
        // Not authorized for this page
        header('Location: access_denied.php');
        exit;
    }
}
