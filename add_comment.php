<?php
/**
 * add_comment.php
 * เพิ่มคอมเมนต์, ตอบกลับความคิดเห็น หรือให้คะแนนดาว 1-5 ดาว
 *
 * Method: POST
 * Body (JSON):
 *   - recipe_id (required int)
 *   - user_id   (required int)
 *   - comment   (required string)
 *   - rating    (optional int 1-5)
 *   - parent_id (optional int - รหัสความคิดเห็นหลักกรณีเป็นการตอบกลับ)
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method POST เท่านั้น']);
}

$body = get_json_body();

$recipeId = isset($body['recipe_id']) ? (int) $body['recipe_id'] : 0;
$userId   = isset($body['user_id']) ? (int) $body['user_id'] : 0;
$comment  = trim($body['comment'] ?? '');
$rating   = isset($body['rating']) && (int) $body['rating'] >= 1 && (int) $body['rating'] <= 5 ? (int) $body['rating'] : null;
$parentId = isset($body['parent_id']) && (int) $body['parent_id'] > 0 ? (int) $body['parent_id'] : null;

if ($recipeId <= 0 || $userId <= 0) {
    send_response(400, ['success' => false, 'message' => 'กรุณาระบุ recipe_id และ user_id ให้ถูกต้อง']);
}

if ($comment === '') {
    send_response(400, ['success' => false, 'message' => 'กรุณากรอกข้อความความคิดเห็น']);
}

// ตรวจสอบว่าผู้ใช้และสูตรอาหารมีอยู่จริงหรือไม่
$checkUser = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($checkUser, 'i', $userId);
mysqli_stmt_execute($checkUser);
if (mysqli_num_rows(mysqli_stmt_get_result($checkUser)) === 0) {
    mysqli_stmt_close($checkUser);
    send_response(404, ['success' => false, 'message' => 'ไม่พบผู้ใช้นี้ในระบบ']);
}
mysqli_stmt_close($checkUser);

$checkRecipe = mysqli_prepare($conn, "SELECT id FROM recipes WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($checkRecipe, 'i', $recipeId);
mysqli_stmt_execute($checkRecipe);
if (mysqli_num_rows(mysqli_stmt_get_result($checkRecipe)) === 0) {
    mysqli_stmt_close($checkRecipe);
    send_response(404, ['success' => false, 'message' => 'ไม่พบสูตรอาหารนี้ในระบบ']);
}
mysqli_stmt_close($checkRecipe);

// การตอบกลับความคิดเห็นย่อย ไม่สามารถให้คะแนนดาวได้
if ($parentId) {
    $rating = null;
}

// ป้องกันการปั้มดาว: ตรวจสอบว่าผู้ใช้คนนี้เคยให้คะแนนดาวสูตรนี้ไปแล้วหรือยัง
if ($rating !== null) {
    $checkRatedStmt = mysqli_prepare($conn, "SELECT id FROM recipe_reviews WHERE recipe_id = ? AND user_id = ? AND rating IS NOT NULL LIMIT 1");
    mysqli_stmt_bind_param($checkRatedStmt, 'ii', $recipeId, $userId);
    mysqli_stmt_execute($checkRatedStmt);
    mysqli_stmt_store_result($checkRatedStmt);
    if (mysqli_stmt_num_rows($checkRatedStmt) > 0) {
        $rating = null; // หากเคยให้ดาวไปแล้ว บันทึกข้อความคอมเมนต์อย่างเดียว ไม่ให้เพิ่มดาวซ้ำ
    }
    mysqli_stmt_close($checkRatedStmt);
}

// บันทึกคอมเมนต์/ตอบกลับลงตาราง recipe_reviews
$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO recipe_reviews (recipe_id, user_id, parent_id, rating, comment) VALUES (?, ?, ?, ?, ?)"
);
mysqli_stmt_bind_param($stmt, 'iiiis', $recipeId, $userId, $parentId, $rating, $comment);
$executed = mysqli_stmt_execute($stmt);

if (!$executed) {
    mysqli_stmt_close($stmt);
    send_response(500, ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกความคิดเห็น']);
}

$newCommentId = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

// หากเป็นการตอบกลับความคิดเห็น ให้ส่งการแจ้งเตือนหาเจ้าของความคิดเห็นเดิม
if ($parentId) {
    $parentStmt = mysqli_prepare($conn, "SELECT user_id FROM recipe_reviews WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($parentStmt, 'i', $parentId);
    mysqli_stmt_execute($parentStmt);
    $parentRow = mysqli_fetch_assoc(mysqli_stmt_get_result($parentStmt));
    mysqli_stmt_close($parentStmt);

    if ($parentRow && (int)$parentRow['user_id'] !== $userId) {
        $targetUserId = (int)$parentRow['user_id'];

        $userStmt = mysqli_prepare($conn, "SELECT full_name, role FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($userStmt, 'i', $userId);
        mysqli_stmt_execute($userStmt);
        $userRow = mysqli_fetch_assoc(mysqli_stmt_get_result($userStmt));
        mysqli_stmt_close($userStmt);

        $replierName = $userRow['full_name'] ?? 'ผู้ใช้งาน';
        $replierRole = $userRow['role'] ?? 'user';

        $recipeStmt = mysqli_prepare($conn, "SELECT title FROM recipes WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($recipeStmt, 'i', $recipeId);
        mysqli_stmt_execute($recipeStmt);
        $recipeRow = mysqli_fetch_assoc(mysqli_stmt_get_result($recipeStmt));
        mysqli_stmt_close($recipeStmt);

        $recipeTitle = $recipeRow['title'] ?? 'สูตรอาหาร';

        $isChef = ($replierRole === 'chef' || $replierRole === 'admin');
        $notifTitle = $isChef ? "👨‍🍳 เชฟ{$replierName} ตอบกลับความคิดเห็นของคุณ!" : "💬 {$replierName} ตอบกลับความคิดเห็นของคุณ!";
        $notifBody  = "\"{$comment}\" ในสูตรอาหาร: {$recipeTitle}";

        $notifStmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, title, body) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($notifStmt, 'iss', $targetUserId, $notifTitle, $notifBody);
        mysqli_stmt_execute($notifStmt);
        mysqli_stmt_close($notifStmt);
    }
}

// ดึงข้อมูลคอมเมนต์ที่เพิ่งเพิ่มส่งกลับไป
$getStmt = mysqli_prepare($conn, "
    SELECT rr.id, rr.recipe_id, rr.parent_id, rr.rating, rr.comment, rr.created_at,
           u.id AS user_id, u.full_name, u.avatar_url, u.role
    FROM recipe_reviews rr
    INNER JOIN users u ON rr.user_id = u.id
    WHERE rr.id = ? LIMIT 1
");
mysqli_stmt_bind_param($getStmt, 'i', $newCommentId);
mysqli_stmt_execute($getStmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($getStmt));
mysqli_stmt_close($getStmt);

send_response(201, [
    'success' => true,
    'message' => $parentId ? 'ตอบกลับความคิดเห็นเรียบร้อยแล้ว' : 'ส่งความคิดเห็นและคะแนนดาวเรียบร้อยแล้ว',
    'comment' => [
        'id'         => (int) $row['id'],
        'recipe_id'  => (int) $row['recipe_id'],
        'parent_id'  => $row['parent_id'] ? (int) $row['parent_id'] : null,
        'rating'     => $row['rating'] ? (int) $row['rating'] : null,
        'comment'    => $row['comment'],
        'created_at' => $row['created_at'],
        'author'     => [
            'id'         => (int) $row['user_id'],
            'full_name'  => $row['full_name'],
            'avatar_url' => $row['avatar_url'],
            'role'       => $row['role'],
        ],
        'replies'    => [],
    ],
]);

mysqli_close($conn);
