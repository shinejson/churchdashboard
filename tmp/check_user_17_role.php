<?php
$pdo = new PDO('mysql:host=localhost;dbname=church_management', 'root', '');
$res = $pdo->query('SELECT id, username, role FROM users WHERE id = 17')->fetch(PDO::FETCH_ASSOC);
print_r($res);
