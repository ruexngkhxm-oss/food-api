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

$chefId        = isset($_GET['chef_id']) ? (int) $_GET['chef_id'] : (isset($_GET['user_id']) ? (int) $_GET['user_id'] : 3);
$currentUserId = isset($_GET['current_user_id']) ? (int) $_GET['current_user_id'] : null;

if ($chefId <= 0) {
    $chefId = 3;
}

// 1. ข้อมูลผู้ใช้/เชฟ
$userStmt = db_prepare($conn, "SELECT id, username, email, full_name, avatar_url, bio, role, created_at FROM users WHERE id = ? LIMIT 1");
db_bind_param($userStmt, 'i', $chefId);
db_execute($userStmt);
$uRes = db_get_result($userStmt);
$chef = db_fetch_assoc($uRes);
db_stmt_close($userStmt);

if (!$chef) {
    send_response(404, ['success' => false, 'message' => 'ไม่พบข้อมูลเชฟในระบบ']);
}

// 2. คำนวณสถิติจำนวนสูตรทั้งหมด ยอดเข้าชมรวม และยอดกดชอบรวมทั้งหมด (Total Favorites)
$statStmt = db_prepare($conn, "
    SELECT COUNT(DISTINCT r.id) AS recipe_count,
           COALESCE(SUM(r.view_count), 0) AS total_views,
           COUNT(DISTINCT b.id) AS total_favorites,
           COALESCE(ROUND(AVG(rr.rating), 1), 0.0) AS avg_rating
    FROM recipes r
    LEFT JOIN bookmarks b ON r.id = b.recipe_id
    LEFT JOIN recipe_reviews rr ON r.id = rr.recipe_id AND rr.rating IS NOT NULL
    WHERE r.user_id = ?
");
db_bind_param($statStmt, 'i', $chefId);
db_execute($statStmt);
$statRes = db_get_result($statStmt);
$stat = db_fetch_assoc($statRes);
db_stmt_close($statStmt);

// 3. คำนวณจำนวนผู้ติดตาม (follower_count) และเช็คสถานะการติดตาม (is_following)
$followerStmt = db_prepare($conn, "SELECT COUNT(*) as count FROM follows WHERE chef_id = ?");
db_bind_param($followerStmt, 'i', $chefId);
db_execute($followerStmt);
$followerRes = db_get_result($followerStmt);
$followerRow = db_fetch_assoc($followerRes);
$followerCount = (int) ($followerRow['count'] ?? 0);
db_stmt_close($followerStmt);

$isFollowing = false;
if ($currentUserId) {
    $followCheckStmt = db_prepare($conn, "SELECT id FROM follows WHERE user_id = ? AND chef_id = ? LIMIT 1");
    db_bind_param($followCheckStmt, 'ii', $currentUserId, $chefId);
    db_execute($followCheckStmt);
    $fcRes = db_get_result($followCheckStmt);
    $isFollowing = db_num_rows($fcRes) > 0;
    db_stmt_close($followCheckStmt);
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

$stmt = db_prepare($conn, $sql);
db_bind_param($stmt, 'i', $chefId);
db_execute($stmt);
$result = db_get_result($stmt);

$recipes = [];
while ($row = db_fetch_assoc($result)) {
    $rId = (int) $row['id'];

    // ดึงป้ายกำกับสายสุขภาพ
    $tagStmt = db_prepare($conn, "SELECT dt.id, dt.name, dt.icon FROM recipe_dietary_tags rdt INNER JOIN dietary_tags dt ON rdt.tag_id = dt.id WHERE rdt.recipe_id = ? ORDER BY dt.sort_order ASC");
    db_bind_param($tagStmt, 'i', $rId);
    db_execute($tagStmt);
    $tagResult = db_get_result($tagStmt);
    $dietaryTags = [];
    while ($tagRow = db_fetch_assoc($tagResult)) {
        $dietaryTags[] = [
            'id'   => (int) $tagRow['id'],
            'name' => $tagRow['name'],
            'icon' => $tagRow['icon'],
        ];
    }
    db_stmt_close($tagStmt);

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
db_stmt_close($stmt);

// เช็คสถานะบุ๊กมาร์กหากมี current_user_id
if ($currentUserId && count($recipes) > 0) {
    foreach ($recipes as &$r) {
        $bmStmt = db_prepare($conn, "SELECT id FROM bookmarks WHERE user_id = ? AND recipe_id = ? LIMIT 1");
        db_bind_param($bmStmt, 'ii', $currentUserId, $r['id']);
        db_execute($bmStmt);
        $bmRes = db_get_result($bmStmt);
        $r['is_bookmarked'] = db_num_rows($bmRes) > 0;
        db_stmt_close($bmStmt);
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

db_close($conn);
?>
