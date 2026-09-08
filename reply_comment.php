<?php
/**
 * reply_comment.php
 * API ตอบกลับความคิดเห็นโดยเชฟ หรือผู้ใช้งาน
 *
 * Method: POST
 * Body (JSON): { "comment_id", "chef_id", "reply_text" }
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method POST เท่านั้น']);
}

$body = get_json_body();

$commentId = isset($body['comment_id']) ? (int) $body['comment_id'] : 0;
$chefId    = isset($body['chef_id']) ? (int) $body['chef_id'] : (isset($body['user_id']) ? (int) $body['user_id'] : 3);
$replyText = trim($body['reply_text'] ?? $body['comment'] ?? '');

if ($commentId <= 0 || $chefId <= 0 || $replyText === '') {
    send_response(400, ['success' => false, 'message' => 'ข้อมูลความคิดเห็น ผู้ตอบ หรือคำตอบกลับไม่ถูกต้อง']);
}

// 1. ดึงข้อมูลความคิดเห็นต้นทาง (Parent comment) เพื่อหา recipe_id และ target_user_id
$parentStmt = db_prepare($conn, "SELECT recipe_id, user_id FROM recipe_reviews WHERE id = ? LIMIT 1");
db_bind_param($parentStmt, 'i', $commentId);
db_execute($parentStmt);
$parentRes = db_get_result($parentStmt);
$parentRow = db_fetch_assoc($parentRes);
db_stmt_close($parentStmt);

if (!$parentRow) {
    send_response(404, ['success' => false, 'message' => 'ไม่พบความคิดเห็นต้นทางที่ต้องการตอบกลับ']);
}

$recipeId     = (int) $parentRow['recipe_id'];
$targetUserId = (int) $parentRow['user_id'];

// 2. บันทึกคำตอบกลับลงในตาราง recipe_reviews โดยกำหนด parent_id = comment_id
$insertStmt = db_prepare(
    $conn,
    "INSERT INTO recipe_reviews (recipe_id, user_id, parent_id, comment) VALUES (?, ?, ?, ?)"
);
db_bind_param($insertStmt, 'iiis', $recipeId, $chefId, $commentId, $replyText);
$executed = db_execute($insertStmt);

if (!$executed) {
    db_stmt_close($insertStmt);
    send_response(500, ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกคำตอบกลับ']);
}

$replyId = db_insert_id($conn);
db_stmt_close($insertStmt);

// 3. ส่งการแจ้งเตือนหาเจ้าของความคิดเห็นเดิม (หากคนตอบไม่ใช่เจ้าของคอมเมนต์เอง)
if ($targetUserId !== $chefId && $targetUserId > 0) {
    // ดึงชื่อผู้ตอบและบทบาท
    $userStmt = db_prepare($conn, "SELECT full_name, role FROM users WHERE id = ? LIMIT 1");
    db_bind_param($userStmt, 'i', $chefId);
    db_execute($userStmt);
    $userRes = db_get_result($userStmt);
    $userRow = db_fetch_assoc($userRes);
    db_stmt_close($userStmt);

    $replierName = $userRow['full_name'] ?? 'เชฟ';
    $replierRole = $userRow['role'] ?? 'chef';

    // ดึงชื่อสูตรอาหาร
    $recipeStmt = db_prepare($conn, "SELECT title FROM recipes WHERE id = ? LIMIT 1");
    db_bind_param($recipeStmt, 'i', $recipeId);
    db_execute($recipeStmt);
    $recipeRes = db_get_result($recipeStmt);
    $recipeRow = db_fetch_assoc($recipeRes);
    db_stmt_close($recipeStmt);

    $recipeTitle = $recipeRow['title'] ?? 'สูตรอาหาร';

    $isChef = ($replierRole === 'chef' || $replierRole === 'admin');
    $notifTitle = $isChef ? "👨‍🍳 เชฟ{$replierName} ตอบกลับความคิดเห็นของคุณ!" : "💬 {$replierName} ตอบกลับความคิดเห็นของคุณ!";
    $notifBody  = "\"{$replyText}\" ในสูตรอาหาร: {$recipeTitle}";

    $notifStmt = db_prepare($conn, "INSERT INTO notifications (user_id, title, body) VALUES (?, ?, ?)");
    db_bind_param($notifStmt, 'iss', $targetUserId, $notifTitle, $notifBody);
    db_execute($notifStmt);
    db_stmt_close($notifStmt);
}

send_response(200, [
    'success'  => true,
    'message'  => 'ตอบกลับความคิดเห็นเรียบร้อยแล้ว',
    'reply_id' => $replyId,
]);

db_close($conn);
?>
