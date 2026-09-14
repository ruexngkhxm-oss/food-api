<?php
/**
 * get_image.php
 * อ่านและส่งคืนไฟล์รูปภาพจากโฟลเดอร์ uploads/ พร้อม CORS Headers 100%
 * ป้องกันปัญหา Web Server บล็อกไฟล์ static หรือส่ง 404 ในบางระบบ
 *
 * Method: GET
 * Query Params: ?file=recipe_123.jpg
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: public, max-age=86400');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$file = $_GET['file'] ?? '';
$file = basename($file); // ป้องกัน Directory Traversal attacks

if ($file === '') {
    http_response_code(400);
    echo 'Missing file parameter';
    exit();
}

$filePath = __DIR__ . '/uploads/' . $file;

if (!file_exists($filePath)) {
    http_response_code(404);
    echo 'Image not found';
    exit();
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mimeTypes = [
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'gif'  => 'image/gif',
];

$contentType = $mimeTypes[$ext] ?? 'image/jpeg';
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($filePath));

readfile($filePath);
exit();
?>
