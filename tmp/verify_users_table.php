<?php
$pdo = new PDO('mysql:host=localhost;dbname=church_management', 'root', '');
$res = $pdo->query('DESCRIBE users')->fetchAll(PDO::FETCH_ASSOC);
foreach($res as $col) {
    if($col['Field'] == 'email') {
        var_dump($col);
    }
}
$res2 = $pdo->query('SELECT email FROM users WHERE email IS NULL OR email = ""')->fetchAll(PDO::FETCH_ASSOC);
echo "Count of empty/null emails: " . count($res2) . "\n";
var_dump($res2);

