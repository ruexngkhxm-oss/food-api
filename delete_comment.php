<?php
/**
 * delete_comment.php
 * ลบความคิดเห็น (ลบความคิดเห็นหลักย่อยทั้งหมดด้วย CASCADE)
 *
 * Method: POST
 * Body (JSON):
 *   - comment_id (required int)
 *   - user_id    (required int)
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method POST เท่านั้น']);
}

$body = get_json_body();

$commentId = isset($body['comment_id']) ? (int) $body['comment_id'] : 0;
$userId    = isset($body['user_id']) ? (int) $body['user_id'] : (isset($body['chef_id']) ? (int) $body['chef_id'] : 0);

if ($commentId <= 0 || $userId <= 0) {
    send_response(400, ['success' => false, 'message' => 'กรุณาระบุ comment_id และ user_id หรือ chef_id ให้ถูกต้อง']);
}

// เช็คข้อมูลผู้ใช้เพื่อตรวจสอบ role
$userStmt = mysqli_prepare($conn, "SELECT role FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($userStmt, 'i', $userId);
mysqli_stmt_execute($userStmt);
$uRow = mysqli_fetch_assoc(mysqli_stmt_get_result($userStmt));
mysqli_stmt_close($userStmt);

if (!$uRow) {
    send_response(404, ['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้']);
}

$isChefOrAdmin = in_array($uRow['role'], ['chef', 'admin'], true);

// ตรวจสอบสิทธิ์ความคิดเห็น
$checkStmt = mysqli_prepare($conn, "
    SELECT rr.id, rr.user_id, r.user_id AS recipe_author_id 
    FROM recipe_reviews rr
    LEFT JOIN recipes r ON rr.recipe_id = r.id
    WHERE rr.id = ? LIMIT 1
");
mysqli_stmt_bind_param($checkStmt, 'i', $commentId);
mysqli_stmt_execute($checkStmt);
$cRow = mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt));
mysqli_stmt_close($checkStmt);

if (!$cRow) {
    send_response(404, ['success' => false, 'message' => 'ไม่พบความคิดเห็นนี้']);
}

// อนุญาตลบเฉพาะความคิดเห็นของตนเองเท่านั้น
if ((int) $cRow['user_id'] !== $userId) {
    send_response(403, ['success' => false, 'message' => 'คุณไม่มีสิทธิ์ลบความคิดเห็นของผู้อื่น สามารถลบได้เฉพาะความคิดเห็นของคุณเองเท่านั้น']);
}

// ดำเนินการลบ
$delStmt = mysqli_prepare($conn, "DELETE FROM recipe_reviews WHERE id = ?");
mysqli_stmt_bind_param($delStmt, 'i', $commentId);
$executed = mysqli_stmt_execute($delStmt);
mysqli_stmt_close($delStmt);

if (!$executed) {
    send_response(500, ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบความคิดเห็น']);
}

send_response(200, [
    'success' => true,
    'message' => 'ลบความคิดเห็นเรียบร้อยแล้ว',
]);

mysqli_close($conn);
