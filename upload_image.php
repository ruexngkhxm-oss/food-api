<?php
/**
 * upload_image.php
 * อัปโหลดรูปภาพอาหารและบันทึกลงโฟลเดอร์ uploads/ บนเซิร์ฟเวอร์
 *
 * Method: POST
 * Format 1 (Multipart): Key 'image' ใน $_FILES
 * Format 2 (JSON): { "image_base64": "...", "filename": "example.jpg" }
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method POST เท่านั้น']);
}

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$baseUrl = rtrim($protocol . "://" . $host . $scriptDir, '/\\');

$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$savedFileName = '';

// 1. ตรวจสอบว่าส่งมาแบบ Multipart $_FILES หรือไม่
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $tmpName = $_FILES['image']['tmp_name'];
    $originalName = $_FILES['image']['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        $ext = 'jpg';
    }

    $savedFileName = 'recipe_' . uniqid() . '_' . time() . '.' . $ext;
    $destination = $uploadDir . $savedFileName;

    if (!move_uploaded_file($tmpName, $destination)) {
        send_response(500, ['success' => false, 'message' => 'ไม่สามารถบันทึกไฟล์รูปภาพได้']);
    }
} else {
    // 2. ตรวจสอบว่าส่งมาแบบ JSON Base64 หรือไม่
    $body = get_json_body();
    $base64Data = $body['image_base64'] ?? '';
    $originalName = $body['filename'] ?? 'image.jpg';

    if (empty($base64Data)) {
        send_response(400, ['success' => false, 'message' => 'กรุณาแนบไฟล์รูปภาพที่ต้องการอัปโหลด']);
    }

    // ตัดส่วน Prefix data:image/png;base64, ออกหากมี
    if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
        $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
        $ext = strtolower($type[1]);
    } else {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    }

    if (!in_array($ext, $allowedExtensions)) {
        $ext = 'jpg';
    }

    $decodedBytes = base64_decode($base64Data);
    if ($decodedBytes === false) {
        send_response(400, ['success' => false, 'message' => 'รูปแบบ Base64 รูปภาพไม่ถูกต้อง']);
    }

    $savedFileName = 'recipe_' . uniqid() . '_' . time() . '.' . $ext;
    $destination = $uploadDir . $savedFileName;

    if (file_put_contents($destination, $decodedBytes) === false) {
        send_response(500, ['success' => false, 'message' => 'ไม่สามารถบันทึกไฟล์รูปภาพได้']);
    }
}

$fileUrl = $baseUrl . '/uploads/' . $savedFileName;

send_response(200, [
    'success'       => true,
    'message'       => 'อัปโหลดรูปภาพสำเร็จ',
    'image_url'     => $fileUrl,
    'relative_path' => 'uploads/' . $savedFileName,
    'filename'      => $savedFileName,
]);
