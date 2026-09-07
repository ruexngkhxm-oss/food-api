<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendJsonResponse(["status" => "ok"]);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$fullName = trim($input['full_name'] ?? $input['name'] ?? '');
$username = trim($input['username'] ?? '');
$email = trim($input['email'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($username) || empty($password)) {
    sendJsonResponse(["success" => false, "message" => "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน"], 400);
}

$db = getDb();

// Check if username already exists
$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM users WHERE username = :u");
$stmt->execute([':u' => $username]);
if ($stmt->fetch()['cnt'] > 0) {
    sendJsonResponse(["success" => false, "message" => "ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว"], 400);
}

// Insert new user
$stmt = $db->prepare("INSERT INTO users (username, full_name, email, password, role, avatar_url) VALUES (:u, :fn, :e, :p, 'user', 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=400&q=80')");
$stmt->execute([
    ':u' => $username,
    ':fn' => $fullName ?: $username,
    ':e' => $email,
    ':p' => $password
]);

$newId = $db->lastInsertId();

sendJsonResponse([
    "success" => true,
    "message" => "สมัครสมาชิกสำเร็จเรียบร้อยแล้ว",
    "user" => [
        "id" => $newId,
        "username" => $username,
        "full_name" => $fullName ?: $username,
        "email" => $email,
        "role" => "user",
        "avatar_url" => "https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=400&q=80"
    ]
]);
?>
