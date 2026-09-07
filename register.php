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

$username  = trim($body['username']  ?? $_POST['username']  ?? $_REQUEST['username']  ?? '');
$email     = trim($body['email']     ?? $_POST['email']     ?? $_REQUEST['email']     ?? '');
$password  = trim($body['password']  ?? $_POST['password']  ?? $_REQUEST['password']  ?? '');
$full_name = trim($body['full_name'] ?? $body['name'] ?? $_POST['full_name'] ?? $_POST['name'] ?? $username);

if (empty($full_name)) {
    $full_name = $username;
}

// ----- Validation -----
if ($username === '' || $email === '' || $password === '') {
    send_response(400, ['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบทุกช่อง']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_response(400, ['success' => false, 'message' => 'รูปแบบอีเมลไม่ถูกต้อง']);
}

if (strlen($password) < 6) {
    send_response(400, ['success' => false, 'message' => 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร']);
}

// ----- ตรวจสอบว่า username หรือ email ซ้ำหรือไม่ -----
$checkStmt = db_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
if ($checkStmt) {
    db_bind_param($checkStmt, 'ss', $username, $email);
    db_execute($checkStmt);
    $checkResult = db_get_result($checkStmt);

    if ($checkResult && db_num_rows($checkResult) > 0) {
        send_response(409, ['success' => false, 'message' => 'มีชื่อผู้ใช้หรืออีเมลนี้ในระบบแล้ว']);
    }
    db_stmt_close($checkStmt);
}

// ----- แฮชรหัสผ่านด้วย password_hash() -----
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);
$defaultAvatar = 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=300&q=80';

// ----- บันทึกผู้ใช้ใหม่ -----
$insertStmt = db_prepare(
    $conn,
    "INSERT INTO users (username, email, password, full_name, avatar_url, role) VALUES (?, ?, ?, ?, ?, 'user')"
);

if ($insertStmt) {
    db_bind_param($insertStmt, 'sssss', $username, $email, $hashedPassword, $full_name, $defaultAvatar);
    $executed = db_execute($insertStmt);

    if ($executed) {
        $newUserId = db_insert_id($conn);
        send_response(201, [
            'success' => true,
            'message' => 'สมัครสมาชิกสำเร็จ',
            'user' => [
                'id'        => $newUserId,
                'username'  => $username,
                'email'     => $email,
                'full_name' => $full_name,
                'role'      => 'user',
                'avatar_url'=> $defaultAvatar,
            ],
        ]);
    } else {
        $dbErr = db_error($conn, $insertStmt) ?: 'ไม่สามารถบันทึกข้อมูลได้';
        send_response(500, ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการสมัครสมาชิก: ' . $dbErr]);
    }
    db_stmt_close($insertStmt);
} else {
    $dbErr = db_error($conn) ?: 'ไม่สามารถเตรียมคำสั่ง SQL ได้';
    send_response(500, ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: ' . $dbErr]);
}

db_close($conn);
?>
