<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

echo json_encode([
    "status" => "online",
    "service" => "Food API Service on Render.com",
    "version" => "1.0.0",
    "timestamp" => date("Y-m-d H:i:s")
]);
?>
