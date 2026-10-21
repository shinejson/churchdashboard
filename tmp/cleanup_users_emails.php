<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=church_management', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $count = $pdo->exec("UPDATE users SET email = NULL WHERE email = '' OR TRIM(email) = ''");
    echo "Updated $count rows in users table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
