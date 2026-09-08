<?php
/**
 * get_comments.php
 * ดึงรายการความคิดเห็น ตอบกลับ และคะแนนดาวของสูตรอาหาร (จัดโครงสร้างแบบตอบกลับย่อย)
 *
 * Method: GET
 * Query params:
 *   - recipe_id (required int)
 *   - user_id   (optional int - ใช้เช็คว่าผู้ใช้นี้เคยให้ดาวสูตรนี้หรือยัง)
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method GET เท่านั้น']);
}

$recipeId = isset($_GET['recipe_id']) ? (int) $_GET['recipe_id'] : 0;
$userId   = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if ($recipeId <= 0) {
    send_response(400, ['success' => false, 'message' => 'กรุณาระบุ recipe_id']);
}

$stmt = db_prepare($conn, "
    SELECT rr.id, rr.recipe_id, rr.parent_id, rr.rating, rr.comment, rr.created_at,
           u.id AS user_id, u.full_name, u.avatar_url, u.role
    FROM recipe_reviews rr
    INNER JOIN users u ON rr.user_id = u.id
    WHERE rr.recipe_id = ?
    ORDER BY rr.created_at ASC, rr.id ASC
");
db_bind_param($stmt, 'i', $recipeId);
db_execute($stmt);
$result = db_get_result($stmt);

$topLevelComments = [];
$repliesMap = [];

while ($row = db_fetch_assoc($result)) {
    $c = [
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
    ];

    if ($c['parent_id'] === null) {
        $topLevelComments[$c['id']] = $c;
    } else {
        $repliesMap[$c['parent_id']][] = $c;
    }
}
db_stmt_close($stmt);

// ประกบการตอบกลับย่อยใส่ topLevelComments
foreach ($topLevelComments as $id => &$topComment) {
    if (isset($repliesMap[$id])) {
        $topComment['replies'] = $repliesMap[$id];
    }
}
unset($topComment);

// คำนวณคะแนนดาวเฉลี่ยสำหรับสูตรนี้
$avgStmt = db_prepare($conn, "
    SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(rating) AS rating_count
    FROM recipe_reviews
    WHERE recipe_id = ? AND rating IS NOT NULL
");
db_bind_param($avgStmt, 'i', $recipeId);
db_execute($avgStmt);
$avgRes = db_get_result($avgStmt);
$avgRow = db_fetch_assoc($avgRes);
db_stmt_close($avgStmt);

$avgRating   = ($avgRow && $avgRow['avg_rating'] !== null) ? (float) $avgRow['avg_rating'] : 0.0;
$ratingCount = (int) ($avgRow['rating_count'] ?? 0);

// ตรวจสอบว่าผู้ใช้ปัจจุบันเคยให้ดาวสูตรนี้ไปแล้วหรือยัง
$userHasRated = false;
$userRating   = null;
if ($userId > 0) {
    $userRateStmt = db_prepare($conn, "SELECT rating FROM recipe_reviews WHERE recipe_id = ? AND user_id = ? AND rating IS NOT NULL LIMIT 1");
    db_bind_param($userRateStmt, 'ii', $recipeId, $userId);
    db_execute($userRateStmt);
    $userRateRes = db_get_result($userRateStmt);
    if ($userRateRow = db_fetch_assoc($userRateRes)) {
        $userHasRated = true;
        $userRating   = (int) $userRateRow['rating'];
    }
    db_stmt_close($userRateStmt);
}

send_response(200, [
    'success'        => true,
    'avg_rating'     => $avgRating,
    'rating_count'   => $ratingCount,
    'user_has_rated' => $userHasRated,
    'user_rating'    => $userRating,
    'count'          => count($topLevelComments),
    'comments'       => array_values($topLevelComments),
]);

db_close($conn);
?>
