<?php
/**
 * get_chef_profile.php
 * ดึงข้อมูลโปรไฟล์ของเชฟ/ผู้เขียนสูตร พร้อมสถิติมัดรวม และรายการสูตรอาหารทั้งหมดที่เชฟคนนี้สร้าง
 *
 * Method: GET
 * Query params:
 *   - chef_id (required int) หรือ user_id
 *   - current_user_id (optional int) : เช็คบุ๊กมาร์กและการติดตามสำหรับผู้ใช้ที่กำลังดู
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method GET เท่านั้น']);
}

$chefId        = isset($_GET['chef_id']) ? (int) $_GET['chef_id'] : (isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0);
$currentUserId = isset($_GET['current_user_id']) ? (int) $_GET['current_user_id'] : null;

if ($chefId <= 0) {
    send_response(400, ['success' => false, 'message' => 'กรุณาระบุ chef_id']);
}

// 1. ข้อมูลผู้ใช้/เชฟ
$userStmt = mysqli_prepare($conn, "SELECT id, username, email, full_name, avatar_url, bio, role, created_at FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($userStmt, 'i', $chefId);
mysqli_stmt_execute($userStmt);
$chef = mysqli_fetch_assoc(mysqli_stmt_get_result($userStmt));
mysqli_stmt_close($userStmt);

if (!$chef) {
    send_response(404, ['success' => false, 'message' => 'ไม่พบข้อมูลเชฟในระบบ']);
}

// 2. คำนวณสถิติจำนวนสูตรทั้งหมด ยอดเข้าชมรวม และยอดกดชอบรวมทั้งหมด (Total Favorites)
$statStmt = mysqli_prepare($conn, "
    SELECT COUNT(DISTINCT r.id) AS recipe_count,
           COALESCE(SUM(r.view_count), 0) AS total_views,
           COUNT(DISTINCT b.id) AS total_favorites,
           COALESCE(ROUND(AVG(rr.rating), 1), 0.0) AS avg_rating
    FROM recipes r
    LEFT JOIN bookmarks b ON r.id = b.recipe_id
    LEFT JOIN recipe_reviews rr ON r.id = rr.recipe_id AND rr.rating IS NOT NULL
    WHERE r.user_id = ?
");
mysqli_stmt_bind_param($statStmt, 'i', $chefId);
mysqli_stmt_execute($statStmt);
$stat = mysqli_fetch_assoc(mysqli_stmt_get_result($statStmt));
mysqli_stmt_close($statStmt);

// 3. คำนวณจำนวนผู้ติดตาม (follower_count) และเช็คสถานะการติดตาม (is_following)
$followerStmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM follows WHERE chef_id = ?");
mysqli_stmt_bind_param($followerStmt, 'i', $chefId);
mysqli_stmt_execute($followerStmt);
mysqli_stmt_bind_result($followerStmt, $followerCount);
mysqli_stmt_fetch($followerStmt);
mysqli_stmt_close($followerStmt);

$isFollowing = false;
if ($currentUserId) {
    $followCheckStmt = mysqli_prepare($conn, "SELECT id FROM follows WHERE user_id = ? AND chef_id = ? LIMIT 1");
    mysqli_stmt_bind_param($followCheckStmt, 'ii', $currentUserId, $chefId);
    mysqli_stmt_execute($followCheckStmt);
    mysqli_stmt_store_result($followCheckStmt);
    $isFollowing = mysqli_stmt_num_rows($followCheckStmt) > 0;
    mysqli_stmt_close($followCheckStmt);
}

// 4. ดึงรายการสูตรอาหารทั้งหมดที่เชฟสร้าง
$sql = "
    SELECT r.id, r.title, r.description, r.image_url, r.prep_time, r.servings,
           r.is_featured, r.view_count, r.created_at,
           c.id AS category_id, c.name AS category_name, c.icon AS category_icon,
           COALESCE((SELECT ROUND(AVG(rr.rating), 1) FROM recipe_reviews rr WHERE rr.recipe_id = r.id AND rr.rating IS NOT NULL), 0.0) AS avg_rating,
           (SELECT COUNT(*) FROM recipe_reviews rr WHERE rr.recipe_id = r.id AND rr.rating IS NOT NULL) AS rating_count,
           (SELECT COUNT(*) FROM bookmarks b WHERE b.recipe_id = r.id) AS favorite_count
    FROM recipes r
    LEFT JOIN categories c ON r.category_id = c.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $chefId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$recipes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $rId = (int) $row['id'];

    // ดึงป้ายกำกับสายสุขภาพ
    $tagStmt = mysqli_prepare($conn, "SELECT dt.id, dt.name, dt.icon FROM recipe_dietary_tags rdt INNER JOIN dietary_tags dt ON rdt.tag_id = dt.id WHERE rdt.recipe_id = ? ORDER BY dt.sort_order ASC");
    mysqli_stmt_bind_param($tagStmt, 'i', $rId);
    mysqli_stmt_execute($tagStmt);
    $tagResult = mysqli_stmt_get_result($tagStmt);
    $dietaryTags = [];
    while ($tagRow = mysqli_fetch_assoc($tagResult)) {
        $dietaryTags[] = [
            'id'   => (int) $tagRow['id'],
            'name' => $tagRow['name'],
            'icon' => $tagRow['icon'],
        ];
    }
    mysqli_stmt_close($tagStmt);

    $recipes[] = [
        'id'            => $rId,
        'title'         => $row['title'],
        'description'   => $row['description'],
        'image_url'     => $row['image_url'],
        'prep_time'     => (int) $row['prep_time'],
        'servings'      => (int) $row['servings'],
        'is_featured'   => (bool) $row['is_featured'],
        'view_count'    => (int) $row['view_count'],
        'favorite_count' => (int) $row['favorite_count'],
        'created_at'    => $row['created_at'],
        'avg_rating'    => (float) $row['avg_rating'],
        'rating_count'  => (int) $row['rating_count'],
        'author'        => [
            'id'         => (int) $chef['id'],
            'full_name'  => $chef['full_name'],
            'avatar_url' => $chef['avatar_url'],
        ],
        'category'      => $row['category_id'] ? [
            'id'   => (int) $row['category_id'],
            'name' => $row['category_name'],
            'icon' => $row['category_icon'],
        ] : null,
        'dietary_tags'  => $dietaryTags,
        'is_bookmarked' => false,
    ];
}
mysqli_stmt_close($stmt);

// เช็คสถานะบุ๊กมาร์กหากมี current_user_id
if ($currentUserId && count($recipes) > 0) {
    $recipeIds = array_column($recipes, 'id');
    $placeholders = implode(',', array_fill(0, count($recipeIds), '?'));

    $bmSql = "SELECT recipe_id FROM bookmarks WHERE user_id = ? AND recipe_id IN ($placeholders)";
    $bmStmt = mysqli_prepare($conn, $bmSql);

    $bmTypes = 'i' . str_repeat('i', count($recipeIds));
    $bmParams = array_merge([$currentUserId], $recipeIds);
    $bmBindNames = [$bmTypes];
    foreach ($bmParams as $key => $value) {
        $bmBindNames[] = &$bmParams[$key];
    }
    call_user_func_array('mysqli_stmt_bind_param', array_merge([$bmStmt], $bmBindNames));
    mysqli_stmt_execute($bmStmt);
    $bmResult = mysqli_stmt_get_result($bmStmt);

    $bookmarkedIds = [];
    while ($bmRow = mysqli_fetch_assoc($bmResult)) {
        $bookmarkedIds[] = (int) $bmRow['recipe_id'];
    }
    mysqli_stmt_close($bmStmt);

    foreach ($recipes as &$r) {
        $r['is_bookmarked'] = in_array($r['id'], $bookmarkedIds, true);
    }
    unset($r);
}

send_response(200, [
    'success' => true,
    'chef'    => [
        'id'              => (int) $chef['id'],
        'username'        => $chef['username'],
        'full_name'       => $chef['full_name'],
        'avatar_url'      => $chef['avatar_url'],
        'bio'             => $chef['bio'],
        'role'            => $chef['role'],
        'recipe_count'    => (int) ($stat['recipe_count'] ?? 0),
        'total_views'     => (int) ($stat['total_views'] ?? 0),
        'total_favorites' => (int) ($stat['total_favorites'] ?? 0),
        'follower_count'  => (int) $followerCount,
        'is_following'    => (bool) $isFollowing,
        'avg_rating'      => (float) ($stat['avg_rating'] ?? 0.0),
        'recipes'         => $recipes,
    ],
]);

mysqli_close($conn);
