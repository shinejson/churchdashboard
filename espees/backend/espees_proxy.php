<?php
// Allow from any origin for testing; you may restrict this in production
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// $content = file_get_contents('./../espeesdata.txt');
// $content = explode('=', $content, 2);
// $apiKey = trim($content[1]);

$requestMethod = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($requestMethod !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Only POST requests are allowed."]);
    exit;
}

$body = file_get_contents("php://input");
$data = json_decode($body, true);
if ($data === null) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON body."]);
    exit;
}
 $apiKey = $data['token'];
// echo $apiKey;
if ($action === "initiate") {
    $url = "https://api.espees.org/v2/payment/product";
} elseif ($action === "confirm") {
    $url = "https://api.espees.org/v2/payment/confirm/";
} else {
    http_response_code(400);
    echo json_encode(["error" => "Invalid action parameter. Use 'action=initiate' or 'action=confirm'."]);
    exit;
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "x-api-key: " . $apiKey
]);

$response = curl_exec($ch);
$err = curl_error($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    http_response_code(500);
    echo json_encode(["error" => $err]);
} else {
    http_response_code($statusCode);
    echo $response;
}
