<?php
/**
 * update_profile.php
 * อัปเดตข้อมูลโปรไฟล์ผู้ใช้ (ชื่อ, ชื่อผู้ใช้, อีเมล, ประวัติ, รูปโปรไฟล์) และเปลี่ยนรหัสผ่าน
 *
 * Method: POST
 * Body (JSON):
 *   - user_id          (required int)
 *   - full_name        (required string)
 *   - username         (required string)
 *   - email            (required string)
 *   - bio              (optional string)
 *   - avatar_url       (optional string)
 *   - current_password (optional string - ต้องส่งหากต้องการเปลี่ยนรหัสผ่าน)
 *   - new_password     (optional string - รหัสผ่านใหม่)
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method POST เท่านั้น']);
}

$body = get_json_body();

$userId          = isset($body['user_id']) ? (int) $body['user_id'] : 0;
$fullName        = trim($body['full_name'] ?? '');
$username        = trim($body['username'] ?? '');
$email           = trim($body['email'] ?? '');
$bio             = trim($body['bio'] ?? '');
$avatarUrl       = trim($body['avatar_url'] ?? '');
$currentPassword = trim($body['current_password'] ?? '');
$newPassword     = trim($body['new_password'] ?? '');

if ($userId <= 0) {
    send_response(400, ['success' => false, 'message' => 'กรุณาระบุ user_id']);
}

if ($fullName === '' || $username === '' || $email === '') {
    send_response(400, ['success' => false, 'message' => 'กรุณากรอกชื่อ-นามสกุล, ชื่อผู้ใช้ และอีเมล']);
}

// 1. ตรวจสอบว่ามีผู้ใช้รายนี้ในระบบจริงหรือไม่
$checkUser = mysqli_prepare($conn, "SELECT id, password FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($checkUser, 'i', $userId);
mysqli_stmt_execute($checkUser);
$userRes = mysqli_stmt_get_result($checkUser);
$existingUser = mysqli_fetch_assoc($userRes);
mysqli_stmt_close($checkUser);

if (!$existingUser) {
    send_response(404, ['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้ในระบบ']);
}

// 2. ตรวจสอบว่า username หรือ email ซ้ำกับผู้ใช้อื่นหรือไม่
$dupCheck = mysqli_prepare($conn, "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? LIMIT 1");
mysqli_stmt_bind_param($dupCheck, 'ssi', $username, $email, $userId);
mysqli_stmt_execute($dupCheck);
$dupRes = mysqli_stmt_get_result($dupCheck);

if (mysqli_num_rows($dupRes) > 0) {
    mysqli_stmt_close($dupCheck);
    send_response(400, ['success' => false, 'message' => 'ชื่อผู้ใช้ หรือ อีเมล นี้มีผู้ใช้อื่นใช้งานแล้ว']);
}
mysqli_stmt_close($dupCheck);

// 3. ตรวจสอบการเปลี่ยนรหัสผ่าน (ถ้ามีการระบุ new_password)
$updatePassword = false;
$hashedNewPassword = '';

if ($newPassword !== '') {
    if ($currentPassword === '') {
        send_response(400, ['success' => false, 'message' => 'กรุณากรอกรหัสผ่านปัจจุบันเพื่อยืนยันการเปลี่ยนรหัสผ่าน']);
    }

    if (!password_verify($currentPassword, $existingUser['password'])) {
        send_response(400, ['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง']);
    }

    $hashedNewPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    $updatePassword = true;
}

// 4. ดำเนินการอัปเดตข้อมูลในตาราง users
if ($updatePassword) {
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE users SET full_name = ?, username = ?, email = ?, bio = ?, avatar_url = ?, password = ? WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ssssssi', $fullName, $username, $email, $bio, $avatarUrl, $hashedNewPassword, $userId);
} else {
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE users SET full_name = ?, username = ?, email = ?, bio = ?, avatar_url = ? WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'sssssi', $fullName, $username, $email, $bio, $avatarUrl, $userId);
}

$executed = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$executed) {
    send_response(500, ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปเดตข้อมูลโปรไฟล์']);
}

// 5. ดึงข้อมูลผู้ใช้ที่อัปเดตล่าสุดส่งกลับไปที่ Flutter
$getStmt = mysqli_prepare($conn, "SELECT id, username, email, full_name, avatar_url, bio, role FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($getStmt, 'i', $userId);
mysqli_stmt_execute($getStmt);
$updatedUser = mysqli_fetch_assoc(mysqli_stmt_get_result($getStmt));
mysqli_stmt_close($getStmt);

send_response(200, [
    'success' => true,
    'message' => 'อัปเดตโปรไฟล์เรียบร้อยแล้ว',
    'user'    => $updatedUser,
]);

mysqli_close($conn);
