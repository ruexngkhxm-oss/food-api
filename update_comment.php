<?php
/**
 * update_comment.php
 * แก้ไขความคิดเห็น และ/หรือ จำนวนดาวที่เคยให้ไป
 *
 * Method: POST
 * Body (JSON):
 *   - comment_id (required int)
 *   - user_id    (required int)
 *   - comment    (required string)
 *   - rating     (optional int 1-5)
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method POST เท่านั้น']);
}

$body = get_json_body();

$commentId = isset($body['comment_id']) ? (int) $body['comment_id'] : 0;
$userId    = isset($body['user_id']) ? (int) $body['user_id'] : (isset($body['chef_id']) ? (int) $body['chef_id'] : 3);
$comment   = trim($body['comment'] ?? '');
$rating    = isset($body['rating']) && (int) $body['rating'] >= 1 && (int) $body['rating'] <= 5 ? (int) $body['rating'] : null;

if ($commentId <= 0 || $userId <= 0) {
    send_response(400, ['success' => false, 'message' => 'กรุณาระบุ comment_id และ user_id หรือ chef_id ให้ถูกต้อง']);
}

if ($comment === '') {
    send_response(400, ['success' => false, 'message' => 'ข้อความความคิดเห็นต้องไม่เป็นค่าว่าง']);
}

// เช็คข้อมูลผู้ใช้เพื่อตรวจสอบ role
$userStmt = db_prepare($conn, "SELECT role FROM users WHERE id = ? LIMIT 1");
db_bind_param($userStmt, 'i', $userId);
db_execute($userStmt);
$uRes = db_get_result($userStmt);
$uRow = db_fetch_assoc($uRes);
db_stmt_close($userStmt);

$isChefOrAdmin = $uRow && in_array($uRow['role'], ['chef', 'admin'], true);

// ตรวจสอบสิทธิ์ว่าผู้ใช้เป็นเจ้าของความคิดเห็น หรือเป็นเชฟ/แอดมิน หรือเจ้าของสูตร
$checkStmt = db_prepare($conn, "
    SELECT rr.id, rr.recipe_id, rr.user_id, r.user_id AS recipe_author_id 
    FROM recipe_reviews rr
    LEFT JOIN recipes r ON rr.recipe_id = r.id
    WHERE rr.id = ? LIMIT 1
");
db_bind_param($checkStmt, 'i', $commentId);
db_execute($checkStmt);
$cRes = db_get_result($checkStmt);
$row = db_fetch_assoc($cRes);
db_stmt_close($checkStmt);

if (!$row) {
    send_response(404, ['success' => false, 'message' => 'ไม่พบความคิดเห็นนี้']);
}

// อนุญาตแก้ไขเฉพาะความคิดเห็นของตนเองเท่านั้น
if ((int) $row['user_id'] !== $userId) {
    send_response(403, ['success' => false, 'message' => 'คุณไม่มีสิทธิ์แก้ไขความคิดเห็นของผู้อื่น สามารถแก้ไขได้เฉพาะความคิดเห็นของคุณเองเท่านั้น']);
}

// อัปเดตข้อมูล
if ($rating !== null) {
    $updateStmt = db_prepare($conn, "UPDATE recipe_reviews SET comment = ?, rating = ? WHERE id = ?");
    db_bind_param($updateStmt, 'sii', $comment, $rating, $commentId);
} else {
    $updateStmt = db_prepare($conn, "UPDATE recipe_reviews SET comment = ? WHERE id = ?");
    db_bind_param($updateStmt, 'si', $comment, $commentId);
}

$executed = db_execute($updateStmt);
db_stmt_close($updateStmt);

if (!$executed) {
    send_response(500, ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการแก้ไขความคิดเห็น']);
}

send_response(200, [
    'success' => true,
    'message' => 'แก้ไขความคิดเห็นเรียบร้อยแล้ว',
]);

db_close($conn);
?>
