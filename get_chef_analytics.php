<?php
/**
 * get_chef_analytics.php
 * API ดึงข้อมูลสถิติเชิงลึกของเชฟจากฐานข้อมูลจริง
 * 
 * Method: GET
 * Query params:
 *   - chef_id (required int) หรือ user_id
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method GET เท่านั้น']);
}

$chefId = isset($_GET['chef_id']) ? (int) $_GET['chef_id'] : (isset($_GET['user_id']) ? (int) $_GET['user_id'] : 3);

if ($chefId <= 0) {
    $chefId = 3;
}

// 1. นับจำนวนสูตรอาหารทั้งหมด และยอดเข้าชมรวม
$recipeStmt = db_prepare($conn, "SELECT COUNT(*) as total_recipes, COALESCE(SUM(view_count), 0) as total_views FROM recipes WHERE user_id = ?");
db_bind_param($recipeStmt, 'i', $chefId);
db_execute($recipeStmt);
$recipeRes = db_get_result($recipeStmt);
$recipeRow = db_fetch_assoc($recipeRes);
$totalRecipes = (int) ($recipeRow['total_recipes'] ?? 0);
$totalViews = (int) ($recipeRow['total_views'] ?? 0);
db_stmt_close($recipeStmt);

// 2. นับจำนวนผู้ติดตาม (Follower count)
$followerStmt = db_prepare($conn, "SELECT COUNT(*) as count FROM follows WHERE chef_id = ?");
db_bind_param($followerStmt, 'i', $chefId);
db_execute($followerStmt);
$followerRes = db_get_result($followerStmt);
$followerRow = db_fetch_assoc($followerRes);
$followerCount = (int) ($followerRow['count'] ?? 0);
db_stmt_close($followerStmt);

// 3. นับจำนวนการกดหัวใจ/บุ๊กมาร์กสะสมของเชฟคนนี้ (Total Favorites)
$favStmt = db_prepare($conn, "
    SELECT COUNT(*) as count
    FROM bookmarks b
    INNER JOIN recipes r ON b.recipe_id = r.id
    WHERE r.user_id = ?
");
db_bind_param($favStmt, 'i', $chefId);
db_execute($favStmt);
$favRes = db_get_result($favStmt);
$favRow = db_fetch_assoc($favRes);
$totalFavorites = (int) ($favRow['count'] ?? 0);
db_stmt_close($favStmt);

// 4. คำนวณคะแนนรีวิวเฉลี่ย
$ratingStmt = db_prepare($conn, "
    SELECT COALESCE(ROUND(AVG(rr.rating), 1), 5.0) as avg_rating
    FROM recipe_reviews rr
    INNER JOIN recipes r ON rr.recipe_id = r.id
    WHERE r.user_id = ? AND rr.rating IS NOT NULL
");
db_bind_param($ratingStmt, 'i', $chefId);
db_execute($ratingStmt);
$ratingRes = db_get_result($ratingStmt);
$ratingRow = db_fetch_assoc($ratingRes);
$avgRating = (float) ($ratingRow['avg_rating'] ?? 5.0);
db_stmt_close($ratingStmt);

send_response(200, [
    'success' => true,
    'analytics' => [
        'total_recipes'   => $totalRecipes,
        'total_views'     => $totalViews,
        'follower_count'  => $followerCount,
        'total_favorites' => $totalFavorites,
        'avg_rating'      => $avgRating,
    ]
]);

db_close($conn);
?>
