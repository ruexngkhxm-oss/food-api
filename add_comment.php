<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendJsonResponse(["status" => "ok"]);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$recipeId = intval($input['recipe_id'] ?? 1);
$authorName = trim($input['author_name'] ?? $input['user_name'] ?? 'ผู้ใช้งานทั่วไป');
$commentText = trim($input['comment_text'] ?? $input['comment'] ?? '');
$rating = intval($input['rating'] ?? 5);

if (empty($commentText)) {
    sendJsonResponse(["success" => false, "message" => "กรุณากรอกข้อความคอมเมนต์"], 400);
}

$db = getDb();
$stmt = $db->prepare("INSERT INTO comments (recipe_id, author_name, comment_text, rating) VALUES (:r, :a, :c, :rt)");
$stmt->execute([
    ':r' => $recipeId,
    ':a' => $authorName,
    ':c' => $commentText,
    ':rt' => $rating
]);

sendJsonResponse([
    "success" => true,
    "message" => "ส่งคอมเมนต์เรียบร้อยแล้ว",
    "data" => [
        "id" => $db->lastInsertId(),
        "recipe_id" => $recipeId,
        "author_name" => $authorName,
        "comment_text" => $commentText,
        "rating" => $rating
    ]
]);
?>
