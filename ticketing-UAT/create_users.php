<?php
// create_users.php
// Run this once (e.g. php create_users.php or via browser) to seed users
// with properly hashed passwords. Delete this file afterwards.

require 'db_config.php';

$conn = getDBConnection();

$users = [
    ['username' => 'johnuser',   'password' => 'user123',  'role' => 'user'],
    ['username' => 'janeadmin',  'password' => 'admin123', 'role' => 'admin'],
    ['username' => 'superadmin', 'password' => 'super123', 'role' => 'super_admin'],
];

foreach ($users as $u) {
    $hash = password_hash($u['password'], PASSWORD_BCRYPT);

    $stmt = $conn->prepare(
        "INSERT INTO users (username, password, role) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE password = ?, role = ?"
    );
    $stmt->bind_param("sssss", $u['username'], $hash, $u['role'], $hash, $u['role']);
    $stmt->execute();
    $stmt->close();

    echo "Created/updated: {$u['username']} ({$u['role']})\n";
}

$conn->close();
echo "Done. Remember to delete this file.\n";
