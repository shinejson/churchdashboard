<?php
$pdo = new PDO('mysql:host=localhost;dbname=church_management', 'root', '');
$res = $pdo->query('SHOW CREATE TABLE users')->fetch(PDO::FETCH_ASSOC);
print_r($res);
