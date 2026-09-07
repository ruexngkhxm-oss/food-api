<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Target folder inside Render server
$uploadDir = __DIR__ . '/uploads/';

// Create folder if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (!isset($_FILES['image']) && !isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "No image file provided."]);
    exit();
}

$file = $_FILES['image'] ?? $_FILES['file'];
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
if (empty($fileExtension)) {
    $fileExtension = 'jpg';
}

// Generate unique filename to avoid collision
$filename = uniqid('img_', true) . '.' . strtolower($fileExtension);
$targetFile = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $targetFile)) {
    // Automatically detect HTTPS/HTTP protocol and server host name on Render
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $protocol = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    // Direct image URL on Render server e.g. https://my-app.onrender.com/uploads/img_123.jpg
    $imageUrl = "{$protocol}://{$host}/uploads/{$filename}";

    echo json_encode([
        "success" => true,
        "message" => "Image uploaded successfully to Render.com!",
        "image_url" => $imageUrl,
        "url" => $imageUrl
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Failed to save image on Render server."
    ]);
}
?>
