<?php
/**
 * login.php
 * เข้าสู่ระบบด้วย username/email + password
 *
 * Method: POST
 * Body (JSON): { "username_or_email", "password" }
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method POST เท่านั้น']);
}

$body = get_json_body();

$identifier = trim($body['username_or_email'] ?? '');
$password   = trim($body['password'] ?? '');

if ($identifier === '' || $password === '') {
    send_response(400, ['success' => false, 'message' => 'กรุณากรอกชื่อผู้ใช้/อีเมล และรหัสผ่าน']);
}

// ----- ค้นหาผู้ใช้ด้วย Prepared Statement -----
$stmt = mysqli_prepare(
    $conn,
    'SELECT id, username, email, password, full_name, avatar_url, bio, role
     FROM users WHERE username = ? OR email = ? LIMIT 1'
);
mysqli_stmt_bind_param($stmt, 'ss', $identifier, $identifier);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user || !password_verify($password, $user['password'])) {
    send_response(401, ['success' => false, 'message' => 'ชื่อผู้ใช้/อีเมล หรือรหัสผ่านไม่ถูกต้อง']);
}

// ไม่ส่งฟิลด์ password กลับไปที่ client
unset($user['password']);

// สร้าง token อย่างง่าย (แนะนำให้ใช้ JWT จริงบน production)
$token = bin2hex(random_bytes(32));

send_response(200, [
    'success' => true,
    'message' => 'เข้าสู่ระบบสำเร็จ',
    'token'   => $token,
    'user'    => $user,
]);

mysqli_close($conn);
