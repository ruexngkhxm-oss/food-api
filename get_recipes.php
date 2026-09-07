<?php
/**
 * get_recipes.php
 * ดึงรายการสูตรอาหาร รองรับค้นหา, กรองหมวดหมู่, และดึงเฉพาะสูตรแนะนำ/มาใหม่
 *
 * Method: GET
 * Query params (ทั้งหมด optional):
 *   - q            : ค้นหาจากชื่อเมนู หรือชื่อวัตถุดิบ
 *   - category_id  : กรองตามหมวดหมู่
 *   - featured     : 1 = ดึงเฉพาะสูตรแนะนำ
 *   - latest       : 1 = เรียงจากใหม่ไปเก่า (สูตรมาใหม่)
 *   - user_id      : ถ้าส่งมา จะแนบสถานะ is_bookmarked ของผู้ใช้คนนั้นในผลลัพธ์
 *   - limit        : จำนวนรายการสูงสุด (default 20, max 100)
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method GET เท่านั้น']);
}

$search        = trim($_GET['q'] ?? '');
$categoryId    = isset($_GET['category_id']) ? (int) $_GET['category_id'] : null;
$dietaryTagId  = isset($_GET['dietary_tag_id']) ? (int) $_GET['dietary_tag_id'] : (isset($_GET['tag_id']) ? (int) $_GET['tag_id'] : null);
$featuredOnly  = isset($_GET['featured']) && $_GET['featured'] == '1';
$latestFirst   = isset($_GET['latest']) && $_GET['latest'] == '1';
$userId        = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
$limit         = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 20;

// ----- สร้าง query แบบไดนามิกด้วย Prepared Statement -----
$sql = "SELECT r.id, r.title, r.description, r.image_url, r.prep_time, r.servings,
               r.is_featured, r.view_count, r.created_at,
               (SELECT COUNT(*) FROM bookmarks b WHERE b.recipe_id = r.id) AS favorite_count,
               COALESCE((SELECT ROUND(AVG(rr.rating), 1) FROM recipe_reviews rr WHERE rr.recipe_id = r.id AND rr.rating IS NOT NULL), 0.0) AS avg_rating,
               (SELECT COUNT(*) FROM recipe_reviews rr WHERE rr.recipe_id = r.id AND rr.rating IS NOT NULL) AS rating_count,
               u.id AS author_id, u.full_name AS author_name, u.avatar_url AS author_avatar,
               c.id AS category_id, c.name AS category_name, c.icon AS category_icon
        FROM recipes r
        INNER JOIN users u ON r.user_id = u.id
        LEFT JOIN categories c ON r.category_id = c.id
        WHERE 1 = 1";

$types  = '';
$params = [];

if ($search !== '') {
    $sql .= " AND (r.title LIKE ? OR EXISTS (
                SELECT 1 FROM recipe_ingredients ri
                WHERE ri.recipe_id = r.id AND ri.ingredient_name LIKE ?
              ))";
    $like = '%' . $search . '%';
    $types  .= 'ss';
    $params[] = $like;
    $params[] = $like;
}

if ($categoryId) {
    $sql .= " AND r.category_id = ?";
    $types  .= 'i';
    $params[] = $categoryId;
}

if ($dietaryTagId) {
    $sql .= " AND EXISTS (SELECT 1 FROM recipe_dietary_tags rdt WHERE rdt.recipe_id = r.id AND rdt.tag_id = ?)";
    $types  .= 'i';
    $params[] = $dietaryTagId;
}

if ($featuredOnly) {
    $sql .= " AND r.is_featured = 1";
}

$sql .= $latestFirst ? " ORDER BY r.created_at DESC" : " ORDER BY r.id ASC";
$sql .= " LIMIT ?";
$types  .= 'i';
$params[] = $limit;

$stmt = db_prepare($conn, $sql);

// bind_param แบบไดนามิก
$bindNames = [$types];
foreach ($params as $key => $value) {
    $bindNames[] = &$params[$key];
}
call_user_func_array('db_bind_param', array_merge([$stmt], $bindNames));

db_execute($stmt);
$result = db_get_result($stmt);

$recipes = [];
while ($row = db_fetch_assoc($result)) {
    $recipes[] = [
        'id'            => (int) $row['id'],
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
            'id'         => (int) $row['author_id'],
            'full_name'  => $row['author_name'],
            'avatar_url' => $row['author_avatar'],
        ],
        'category' => $row['category_id'] ? [
            'id'   => (int) $row['category_id'],
            'name' => $row['category_name'],
            'icon' => $row['category_icon'],
        ] : null,
        'dietary_tags'  => [],
        'is_bookmarked' => false,
    ];
}
db_stmt_close($stmt);

// ----- แนบป้ายกำกับสายสุขภาพ (dietary_tags) ให้กับสูตรอาหารทุกสูตร -----
if (count($recipes) > 0) {
    $recipeIds = array_column($recipes, 'id');
    $placeholders = implode(',', array_fill(0, count($recipeIds), '?'));

    $tagSql = "SELECT rdt.recipe_id, dt.id, dt.name, dt.icon
               FROM recipe_dietary_tags rdt
               INNER JOIN dietary_tags dt ON rdt.tag_id = dt.id
               WHERE rdt.recipe_id IN ($placeholders)
               ORDER BY dt.sort_order ASC";
    $tagStmt = db_prepare($conn, $tagSql);

    $tagTypes = str_repeat('i', count($recipeIds));
    $tagBindNames = [$tagTypes];
    foreach ($recipeIds as $key => $value) {
        $tagBindNames[] = &$recipeIds[$key];
    }
    call_user_func_array('db_bind_param', array_merge([$tagStmt], $tagBindNames));
    db_execute($tagStmt);
    $tagResult = db_get_result($tagStmt);

    $recipeTagsMap = [];
    while ($tagRow = db_fetch_assoc($tagResult)) {
        $rId = (int) $tagRow['recipe_id'];
        $recipeTagsMap[$rId][] = [
            'id'   => (int) $tagRow['id'],
            'name' => $tagRow['name'],
            'icon' => $tagRow['icon'],
        ];
    }
    db_stmt_close($tagStmt);

    foreach ($recipes as &$recipe) {
        if (isset($recipeTagsMap[$recipe['id']])) {
            $recipe['dietary_tags'] = $recipeTagsMap[$recipe['id']];
        }
    }
    unset($recipe);
}

// ----- ถ้าส่ง user_id มา ให้แนบสถานะบุ๊กมาร์กของผู้ใช้คนนั้น -----
if ($userId && count($recipes) > 0) {
    $recipeIds = array_column($recipes, 'id');
    $placeholders = implode(',', array_fill(0, count($recipeIds), '?'));

    $bmSql = "SELECT recipe_id FROM bookmarks WHERE user_id = ? AND recipe_id IN ($placeholders)";
    $bmStmt = db_prepare($conn, $bmSql);

    $bmTypes = 'i' . str_repeat('i', count($recipeIds));
    $bmParams = array_merge([$userId], $recipeIds);
    $bmBindNames = [$bmTypes];
    foreach ($bmParams as $key => $value) {
        $bmBindNames[] = &$bmParams[$key];
    }
    call_user_func_array('db_bind_param', array_merge([$bmStmt], $bmBindNames));
    db_execute($bmStmt);
    $bmResult = db_get_result($bmStmt);

    $bookmarkedIds = [];
    while ($bmRow = db_fetch_assoc($bmResult)) {
        $bookmarkedIds[] = (int) $bmRow['recipe_id'];
    }
    db_stmt_close($bmStmt);

    foreach ($recipes as &$recipe) {
        $recipe['is_bookmarked'] = in_array($recipe['id'], $bookmarkedIds, true);
    }
    unset($recipe);
}

send_response(200, [
    'success' => true,
    'count'   => count($recipes),
    'recipes' => $recipes,
]);

db_close($conn);
?>
