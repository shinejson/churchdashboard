<?php
$pdo = new PDO('mysql:host=localhost;dbname=church_management', 'root', '');
$res = $pdo->query('SELECT * FROM permissions WHERE role = "member"')->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
