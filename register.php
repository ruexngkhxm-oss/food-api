<?php
/**
 * register.php
 * สมัครสมาชิกใหม่
 *
 * Method: POST
 * Body (JSON): { "username", "email", "password", "full_name" }
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method POST เท่านั้น']);
}

$body = get_json_body();

$username  = trim($body['username']  ?? '');
$email     = trim($body['email']     ?? '');
$password  = trim($body['password']  ?? '');
$full_name = trim($body['full_name'] ?? '');

// ----- Validation -----
if ($username === '' || $email === '' || $password === '' || $full_name === '') {
    send_response(400, ['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบทุกช่อง']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_response(400, ['success' => false, 'message' => 'รูปแบบอีเมลไม่ถูกต้อง']);
}

if (strlen($password) < 6) {
    send_response(400, ['success' => false, 'message' => 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร']);
}

// ----- ตรวจสอบว่า username หรือ email ซ้ำหรือไม่ (Prepared Statement) -----
$checkStmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
mysqli_stmt_bind_param($checkStmt, 'ss', $username, $email);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);

if (mysqli_num_rows($checkResult) > 0) {
    send_response(409, ['success' => false, 'message' => 'มีชื่อผู้ใช้หรืออีเมลนี้ในระบบแล้ว']);
}
mysqli_stmt_close($checkStmt);

// ----- แฮชรหัสผ่านด้วย password_hash() -----
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// ----- บันทึกผู้ใช้ใหม่ (Prepared Statement) -----
$insertStmt = mysqli_prepare(
    $conn,
    'INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)'
);
mysqli_stmt_bind_param($insertStmt, 'ssss', $username, $email, $hashedPassword, $full_name);

if (mysqli_stmt_execute($insertStmt)) {
    $newUserId = mysqli_insert_id($conn);
    send_response(201, [
        'success' => true,
        'message' => 'สมัครสมาชิกสำเร็จ',
        'user' => [
            'id'        => $newUserId,
            'username'  => $username,
            'email'     => $email,
            'full_name' => $full_name,
            'avatar_url'=> null,
        ],
    ]);
} else {
    send_response(500, ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการสมัครสมาชิก']);
}

mysqli_stmt_close($insertStmt);
mysqli_close($conn);
