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

$chefId = isset($_GET['chef_id']) ? (int) $_GET['chef_id'] : (isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0);

if ($chefId <= 0) {
    send_response(400, ['success' => false, 'message' => 'กรุณาระบุ chef_id']);
}

// 1. นับจำนวนสูตรอาหารทั้งหมด และยอดเข้าชมรวม
$recipeStmt = mysqli_prepare($conn, "SELECT COUNT(*), COALESCE(SUM(view_count), 0) FROM recipes WHERE user_id = ?");
mysqli_stmt_bind_param($recipeStmt, 'i', $chefId);
mysqli_stmt_execute($recipeStmt);
mysqli_stmt_bind_result($recipeStmt, $totalRecipes, $totalViews);
mysqli_stmt_fetch($recipeStmt);
mysqli_stmt_close($recipeStmt);

// 2. นับจำนวนผู้ติดตาม (Follower count)
$followerStmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM follows WHERE chef_id = ?");
mysqli_stmt_bind_param($followerStmt, 'i', $chefId);
mysqli_stmt_execute($followerStmt);
mysqli_stmt_bind_result($followerStmt, $followerCount);
mysqli_stmt_fetch($followerStmt);
mysqli_stmt_close($followerStmt);

// 3. นับจำนวนการกดหัวใจ/บุ๊กมาร์กสะสมของเชฟคนนี้ (Total Favorites)
$favStmt = mysqli_prepare($conn, "
    SELECT COUNT(*) 
    FROM bookmarks b
    INNER JOIN recipes r ON b.recipe_id = r.id
    WHERE r.user_id = ?
");
mysqli_stmt_bind_param($favStmt, 'i', $chefId);
mysqli_stmt_execute($favStmt);
mysqli_stmt_bind_result($favStmt, $totalFavorites);
mysqli_stmt_fetch($favStmt);
mysqli_stmt_close($favStmt);

// 4. คำนวณคะแนนรีวิวเฉลี่ย
$ratingStmt = mysqli_prepare($conn, "
    SELECT COALESCE(ROUND(AVG(rr.rating), 1), 5.0) 
    FROM recipe_reviews rr
    INNER JOIN recipes r ON rr.recipe_id = r.id
    WHERE r.user_id = ? AND rr.rating IS NOT NULL
");
mysqli_stmt_bind_param($ratingStmt, 'i', $chefId);
mysqli_stmt_execute($ratingStmt);
mysqli_stmt_bind_result($ratingStmt, $avgRating);
mysqli_stmt_fetch($ratingStmt);
mysqli_stmt_close($ratingStmt);

send_response(200, [
    'success' => true,
    'analytics' => [
        'total_recipes'  => (int) $totalRecipes,
        'total_views'    => (int) $totalViews,
        'follower_count' => (int) $followerCount,
        'total_favorites' => (int) $totalFavorites,
        'avg_rating'     => (float) $avgRating,
    ]
]);

mysqli_close($conn);
